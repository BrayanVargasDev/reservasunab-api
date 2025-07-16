<?php

namespace App\Services;

use App\Models\Espacio;
use App\Models\EspacioConfiguracion;
use App\Models\EspacioTipoUsuarioConfig;
use App\Models\FranjaHoraria;
use App\Models\Reservas;
use App\Traits\ManageTimezone;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Uid\Ulid;
use Throwable;

class ReservaService
{
    use ManageTimezone;

    const MINUTOS_USO_DEFAULT = 60;
    const DIAS_PREVIOS_APERTURA_DEFAULT = 1;
    const TIEMPO_CANCELACION_DEFAULT = 30;
    const HORA_APERTURA_DEFAULT = '08:00';
    const PERMITIR_MULTIPLES_RESERVAS_POR_DIA = false;

    /**
     * Crear un objeto Carbon de manera segura desde un formato específico
     */
    private function createCarbonSafely($value, $format, $default = null)
    {
        try {
            if (empty($value)) {
                return $default;
            }
            return Carbon::createFromFormat($format, $value);
        } catch (\Exception $e) {
            Log::warning('Error al crear Carbon desde formato', [
                'value' => $value,
                'format' => $format,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }

    public function getAll(string $search = '', int $per_page = 10)
    {
        return [
            'data' => [],
            'per_page' => $per_page,
            'current_page' => 1,
            'total' => 0,
        ];
    }

    public function getAllEspacios(
        string $fecha = '',
        string $grupo = '',
        string $sede  = '',
        string $categoria = ''
    ) {
        $carbon = $fecha ? Carbon::createFromFormat('Y-m-d', $fecha) : now();

        $fechaConsulta = $carbon->toDateString();
        $diaSemana = $carbon->dayOfWeekIso;

        $filtroConfiguraciones = function ($q) use ($fecha, $fechaConsulta, $diaSemana) {
            if (!$fecha) {
                return $q->whereNull('fecha')->where('dia_semana', $diaSemana);
            }

            $q->where(function ($query) use ($fechaConsulta, $diaSemana) {
                $query->whereDate('fecha', $fechaConsulta)
                    ->orWhere(function ($subQuery) use ($fechaConsulta, $diaSemana) {
                        $subQuery->whereNull('fecha')
                            ->where('dia_semana', $diaSemana)
                            ->whereHas('espacio', function ($espacioQuery) use ($fechaConsulta) {
                                $espacioQuery->whereDoesntHave('configuraciones', function ($c) use ($fechaConsulta) {
                                    $c->whereDate('fecha', $fechaConsulta);
                                });
                            });
                    });
            });
        };

        return Espacio::query()
            ->filtros($sede, $categoria, $grupo)
            ->whereHas('configuraciones', function ($q) use ($filtroConfiguraciones) {
                $filtroConfiguraciones($q);
                $q->whereHas('franjas_horarias', function ($franjaQuery) {
                    $franjaQuery->where('activa', true);
                });
            })
            ->with([
                'imagen',
                'sede:id,nombre',
                'categoria:id,nombre,id_grupo',
                'categoria.grupo:id,nombre',
                'configuraciones' => function ($q) use ($filtroConfiguraciones) {
                    $filtroConfiguraciones($q);
                    $q->select(
                        'id',
                        'id_espacio',
                        'fecha',
                        'dia_semana',
                        'minutos_uso',
                        'hora_apertura',
                        'dias_previos_apertura',
                    )
                        ->with([
                            'franjas_horarias' => fn($q) =>
                            $q->where('activa', true)->orderBy('hora_inicio')
                        ]);
                }
            ])
            ->orderBy('nombre')
            ->select(['id', 'nombre', 'id_sede', 'id_categoria'])
            ->get();
    }


    public function getEspacioDetalles(int $id, string $fecha = '')
    {
        $carbon = $fecha ? Carbon::createFromFormat('Y-m-d', $fecha) : now();
        $fechaConsulta = $carbon->toDateString();
        $diaSemana = $carbon->dayOfWeekIso;

        $espacio = Espacio::query()
            ->with([
                'imagen',
                'sede:id,nombre',
                'categoria:id,nombre,id_grupo',
                'categoria.grupo:id,nombre',
                'tipo_usuario_config',
                'novedades' => function ($q) use ($fechaConsulta) {
                    $q->whereDate('fecha', $fechaConsulta);
                }
            ])
            ->find($id);

        if (!$espacio) {
            return $espacio;
        }

        $configuracion = $espacio->configuraciones()
            ->whereDate('fecha', $fechaConsulta)
            ->with([
                'franjas_horarias' => fn($q) =>
                $q->where('eliminado_en', null)->orderBy('hora_inicio')
            ])
            ->first();

        if (!$configuracion) {
            $configuracion = $espacio->configuraciones()
                ->whereNull('fecha')
                ->where('dia_semana', $diaSemana)
                ->with([
                    'franjas_horarias' => fn($q) =>
                    $q->where('eliminado_en', null)->orderBy('hora_inicio')
                ])
                ->first();
        }

        $espacio->configuracion = $configuracion;

        $espacio->disponibilidad = $this->construirDisponibilidad($espacio, $fechaConsulta);
        return $espacio;
    }

    private function construirDisponibilidad($espacio, string $fechaConsulta): array
    {
        $disponibilidad = [];
        $configuracion = $espacio->configuracion;

        if (!$configuracion) {
            return $disponibilidad;
        }

        $franjasHorarias = $configuracion->franjas_horarias;
        $novedades = $espacio->novedades;

        $reservasExistentes = Reservas::where('id_espacio', $espacio->id)
            ->whereDate('fecha', $fechaConsulta)
            ->whereIn('estado', ['inicial', 'completada', 'confirmada'])
            ->whereNull('eliminado_en')
            ->select('hora_inicio', 'hora_fin', 'estado', 'id_usuario')
            ->get();

        $reservasSimultaneasPermitidas = $espacio->reservas_simultaneas ?? 1;

        if ($franjasHorarias->isEmpty()) {
            Log::warning('No hay franjas horarias configuradas para el espacio', [
                'espacio_id' => $espacio->id,
                'configuracion_id' => $configuracion->id ?? null
            ]);
            return $disponibilidad;
        }

        $minutosUso = $configuracion->minutos_uso ?? self::MINUTOS_USO_DEFAULT;

        foreach ($franjasHorarias as $franja) {
            try {
                if (!$franja->hora_inicio || !$franja->hora_fin) {
                    continue;
                }

                $franjaInicio = $this->createCarbonSafely($franja->hora_inicio->format('H:i'), 'H:i');
                $franjaFin = $this->createCarbonSafely($franja->hora_fin->format('H:i'), 'H:i');

                if (!$franjaInicio || !$franjaFin) {
                    continue;
                }

                $horaActual = $franjaInicio->copy();

                while ($horaActual->lessThan($franjaFin)) {
                    $horaInicioSlot = $horaActual->format('h:i A');
                    $horaFinSlot = $horaActual->copy()->addMinutes($minutosUso);

                    if ($horaFinSlot->greaterThan($franjaFin)) {
                        break;
                    }

                    $horaFinSlotFormatted = $horaFinSlot->format('h:i A');

                    $reservasCoincidentes = $reservasExistentes->filter(function ($reserva) use ($horaActual, $horaFinSlot) {
                        try {
                            $reservaInicio = $this->createCarbonSafely($reserva->hora_inicio, 'H:i:s');
                            $reservaFin = $this->createCarbonSafely($reserva->hora_fin, 'H:i:s');

                            if (!$reservaInicio || !$reservaFin) {
                                return false;
                            }

                            $noHayConflicto = (
                                $horaFinSlot->lessThanOrEqualTo($reservaInicio) ||
                                $horaActual->greaterThanOrEqualTo($reservaFin)
                            );

                            return !$noHayConflicto;
                        } catch (Exception $e) {
                            Log::error('Error al procesar reserva existente', [
                                'error' => $e->getMessage(),
                                'reserva_id' => $reserva->id ?? 'unknown'
                            ]);
                            return false;
                        }
                    });

                    $numeroReservasEnSlot = $reservasCoincidentes->count();

                    $usuarioActual = Auth::user();
                    $miReserva = false;
                    if ($usuarioActual) {
                        $miReserva = $reservasCoincidentes->contains('id_usuario', $usuarioActual->id_usuario);
                    }

                    $novedadCoincidente = $novedades->first(function ($novedad) use ($horaActual, $horaFinSlot) {
                        try {
                            if (!$novedad->hora_inicio || !$novedad->hora_fin) {
                                return false;
                            }

                            $novedadInicio = $this->createCarbonSafely($novedad->hora_inicio, 'H:i:s');
                            $novedadFin = $this->createCarbonSafely($novedad->hora_fin, 'H:i:s');

                            if (!$novedadInicio || !$novedadFin) {
                                return false;
                            }

                            $noHaySolapamiento = (
                                $horaFinSlot->lessThanOrEqualTo($novedadInicio) ||
                                $horaActual->greaterThanOrEqualTo($novedadFin)
                            );

                            return !$noHaySolapamiento;
                        } catch (Exception $e) {
                            Log::error('Error al procesar novedad', ['error' => $e->getMessage()]);
                            return false;
                        }
                    });

                    $disponible = true;
                    $usuarioActual = Auth::user();
                    $fechaHoraReserva = null;
                    $reservaPasada = false;

                    if ($numeroReservasEnSlot >= $reservasSimultaneasPermitidas) {
                        $disponible = false;
                    }

                    $fechaHoraReserva = Carbon::createFromFormat(
                        'Y-m-d H:i:s',
                        $fechaConsulta . ' ' . Carbon::createFromFormat('h:i A', $horaInicioSlot)->format('H:i:s'),
                        config('app.timezone')
                    );
                    $ahora = Carbon::now();
                    $reservaPasada = $fechaHoraReserva->addMinutes(10)->lessThan($ahora);

                    $slot = [
                        'hora_inicio' => $horaInicioSlot,
                        'hora_fin' => $horaFinSlotFormatted,
                        'disponible' => $disponible,
                        'valor' => $franja->valor,
                        'franja_id' => $franja->id, // Agregar ID de franja para debugging
                        'mi_reserva' => $miReserva,
                        'reserva_pasada' => $reservaPasada,
                        'novedad' => false,
                        'reservada' => $numeroReservasEnSlot > 0,
                        'reservas_actuales' => $numeroReservasEnSlot,
                        'reservas_maximas' => $reservasSimultaneasPermitidas,
                    ];

                    if ($novedadCoincidente) {
                        $slot['novedad'] = true;
                        $slot['disponible'] = false;
                        $slot['novedad_desc'] = $novedadCoincidente->tipo;
                    }

                    $disponibilidad[] = $slot;
                    $horaActual->addMinutes($minutosUso);
                }
            } catch (Exception $e) {
                Log::error('Error al procesar franja horaria', [
                    'error' => $e->getMessage(),
                    'franja_id' => $franja->id ?? null,
                    'espacio_id' => $espacio->id
                ]);
                continue;
            }
        }

        usort($disponibilidad, function ($a, $b) {
            return strtotime($a['hora_inicio']) <=> strtotime($b['hora_inicio']);
        });

        return $disponibilidad;
    }

    public function iniciarReserva($data)
    {
        try {
            return DB::transaction(function () use ($data) {
                $fecha = Carbon::createFromFormat('Y-m-d', $data['fecha'], config('app.timezone'));
                $horaInicio = Carbon::createFromFormat('h:i A', $data['horaInicio'], config('app.timezone'));
                $horaFin = Carbon::createFromFormat('h:i A', $data['horaFin'], config('app.timezone'));

                $usuario = Auth::user();
                if (!$usuario) {
                    throw new Exception('Usuario no autenticado.');
                }

                $this->validarTipoUsuarioParaReserva($data['base']['id'], $usuario->tipo_usuario);

                $this->validarTiempoAperturaReserva($data['base']['id'], $usuario->tipo_usuario, $fecha);

                $this->validarLimitesReservasPorCategoria($data['base']['id'], $usuario->id_usuario, $usuario->tipo_usuario, $fecha);

                $espacio = Espacio::find($data['base']['id']);
                if (!$espacio) {
                    throw new Exception('Espacio no encontrado.');
                }

                $reservasSimultaneasPermitidas = $espacio->reservas_simultaneas ?? 1;

                $reservasConflicto = Reservas::where('id_espacio', $data['base']['id'])
                    ->whereDate('fecha', $fecha)
                    ->whereIn('estado', ['inicial', 'completada', 'confirmada'])
                    ->whereNull('eliminado_en')
                    ->where(function ($query) use ($horaInicio, $horaFin) {
                        $query->where(function ($q) use ($horaInicio, $horaFin) {
                            $q->whereTime('hora_inicio', '>=', $horaInicio->format('H:i:s'))
                                ->whereTime('hora_inicio', '<', $horaFin->format('H:i:s'));
                        })->orWhere(function ($q) use ($horaInicio, $horaFin) {
                            $q->whereTime('hora_fin', '>', $horaInicio->format('H:i:s'))
                                ->whereTime('hora_fin', '<=', $horaFin->format('H:i:s'));
                        })->orWhere(function ($q) use ($horaInicio, $horaFin) {
                            $q->whereTime('hora_inicio', '<=', $horaInicio->format('H:i:s'))
                                ->whereTime('hora_fin', '>=', $horaFin->format('H:i:s'));
                        });
                    })
                    ->get();

                $numeroReservasEnHorario = $reservasConflicto->count();

                if ($numeroReservasEnHorario >= $reservasSimultaneasPermitidas) {
                    Log::warning('Límite de reservas simultáneas alcanzado', [
                        'espacio_id' => $data['base']['id'],
                        'fecha' => $fecha->toDateString(),
                        'hora_solicitada_inicio' => $horaInicio->format('H:i:s'),
                        'hora_solicitada_fin' => $horaFin->format('H:i:s'),
                        'reservas_actuales' => $numeroReservasEnHorario,
                        'reservas_maximas' => $reservasSimultaneasPermitidas,
                        'reservas_conflicto' => $reservasConflicto->map(function ($r) {
                            return [
                                'id' => $r->id,
                                'hora_inicio' => $r->hora_inicio,
                                'hora_fin' => $r->hora_fin,
                                'estado' => $r->estado,
                                'id_usuario' => $r->id_usuario
                            ];
                        })->toArray()
                    ]);

                    $mensaje = $reservasSimultaneasPermitidas === 1
                        ? 'Ya existe una reserva para este espacio en el horario seleccionado.'
                        : "Se ha alcanzado el límite de reservas simultáneas para este horario. Máximo permitido: {$reservasSimultaneasPermitidas}, actual: {$numeroReservasEnHorario}.";

                    throw new Exception($mensaje);
                }

                $configuracion = $data['base']['configuracion'];
                $idConfiguracion = $this->obtenerIdConfiguracion($configuracion, $data['base']['id'], $fecha);

                $estado = ($usuario->tipo_usuario === 'estudiante') ? 'completada' : 'inicial';

                $reserva = Reservas::create([
                    'id_usuario' => $usuario->id_usuario,
                    'id_espacio' => $data['base']['id'],
                    'fecha' => $fecha,
                    'id_configuracion' => $idConfiguracion,
                    'estado' => $estado,
                    'hora_inicio' => $horaInicio->format('H:i:s'),
                    'hora_fin' => $horaFin->format('H:i:s'),
                    'check_in' => false,
                    'codigo' => Ulid::generate(),
                ]);

                $reserva->load([
                    'espacio:id,nombre,id_sede',
                    'espacio.sede:id,nombre',
                    'usuarioReserva:id_usuario,email',
                    'usuarioReserva.persona:id_persona,id_usuario,primer_nombre,segundo_nombre,primer_apellido,segundo_apellido,numero_documento'
                ]);

                $duracionMinutos = $horaInicio->diffInMinutes($horaFin);
                $valor = $this->obtenerValorReserva($data, $idConfiguracion, $horaInicio, $horaFin);
                $nombreCompleto = $this->construirNombreCompleto($reserva->usuarioReserva->persona);

                $resumenReserva = [
                    'id' => $reserva->id,
                    'nombre_espacio' => $reserva->espacio->nombre,
                    'duracion' => $duracionMinutos,
                    'sede' => $reserva->espacio->sede->nombre,
                    'fecha' => $fecha->format('Y-m-d'),
                    'hora_inicio' => $horaInicio->format('h:i A'),
                    'valor' => $valor,
                    'estado' => $estado,
                    'usuario_reserva' => $nombreCompleto ?: 'Usuario sin nombre',
                    'codigo_usuario' => $reserva->usuarioReserva->persona->numero_documento ?? 'Sin código',
                    'agrega_jugadores' => false
                ];

                return $resumenReserva;
            });
        } catch (Throwable $th) {
            Log::error('Error al iniciar la reserva', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'data_received' => $data,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            throw new Exception($th->getMessage());
        }
    }

    private function obtenerIdConfiguracion($configuracion, $espacioId, $fecha)
    {
        if (!is_null($configuracion['fecha'])) {
            return $configuracion['id'];
        }

        $configuracionExistente = EspacioConfiguracion::where('id_espacio', $espacioId)
            ->whereDate('fecha', $fecha)
            ->first();

        if ($configuracionExistente) {
            return $configuracionExistente->id;
        }

        return $this->copiarConfiguracion($configuracion, $fecha);
    }

    public function obtenerValorReserva($data, $idConfiguracion, $horaInicio, $horaFin)
    {
        if (isset($data['valor'])) {
            return $data['valor'];
        }

        try {
            $configuracionCompleta = EspacioConfiguracion::with('franjas_horarias')
                ->find($idConfiguracion);

            if (!$configuracionCompleta || $configuracionCompleta->franjas_horarias->isEmpty()) {
                return null;
            }

            // Convertir las horas a string H:i:s format
            if ($horaInicio instanceof \Carbon\Carbon || $horaInicio instanceof \DateTime) {
                $horaInicioReserva = $horaInicio->format('H:i:s');
            } else {
                $horaInicioReserva = $horaInicio;
            }

            if ($horaFin instanceof \Carbon\Carbon || $horaFin instanceof \DateTime) {
                $horaFinReserva = $horaFin->format('H:i:s');
            } else {
                $horaFinReserva = $horaFin;
            }

            // Asegurar que las horas tengan el formato H:i:s
            if (strlen($horaInicioReserva) === 5) {
                $horaInicioReserva .= ':00';
            }
            if (strlen($horaFinReserva) === 5) {
                $horaFinReserva .= ':00';
            }

            $franjaCoincidente = $configuracionCompleta->franjas_horarias->first(function ($franja) use ($horaInicioReserva, $horaFinReserva) {
                try {
                    $franjaInicioStr = null;
                    $franjaFinStr = null;

                    if ($franja->hora_inicio instanceof \Carbon\Carbon) {
                        $franjaInicioStr = $franja->hora_inicio->format('H:i:s');
                    } elseif ($franja->hora_inicio instanceof \DateTime) {
                        $franjaInicioStr = $franja->hora_inicio->format('H:i:s');
                    } else {
                        $franjaInicioStr = $franja->hora_inicio;
                    }

                    if ($franja->hora_fin instanceof \Carbon\Carbon) {
                        $franjaFinStr = $franja->hora_fin->format('H:i:s');
                    } elseif ($franja->hora_fin instanceof \DateTime) {
                        $franjaFinStr = $franja->hora_fin->format('H:i:s');
                    } else {
                        $franjaFinStr = $franja->hora_fin;
                    }

                    if (strlen($franjaInicioStr) === 5) {
                        $franjaInicioStr .= ':00';
                    }
                    if (strlen($franjaFinStr) === 5) {
                        $franjaFinStr .= ':00';
                    }

                    $coincide = $horaInicioReserva >= $franjaInicioStr && $horaFinReserva <= $franjaFinStr;
                    return $coincide;
                } catch (Exception $e) {
                    Log::warning('Error al procesar franja para valor', [
                        'franja_id' => $franja->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    return false;
                }
            });

            $valor = $franjaCoincidente ? $franjaCoincidente->valor : null;

            return $valor;
        } catch (Exception $e) {
            Log::error('Error al obtener valor de franja', [
                'configuracion_id' => $idConfiguracion,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    public function construirNombreCompleto($persona)
    {
        if (!$persona) {
            return 'Usuario sin nombre';
        }

        return trim(
            ($persona->primer_nombre ?? '') . ' ' .
                ($persona->segundo_nombre ?? '') . ' ' .
                ($persona->primer_apellido ?? '') . ' ' .
                ($persona->segundo_apellido ?? '')
        );
    }

    private function copiarConfiguracion($configuracionOriginal, $fecha)
    {
        try {
            $nuevaConfiguracion = EspacioConfiguracion::create([
                'id_espacio' => $configuracionOriginal['id_espacio'],
                'fecha' => $fecha,
                'dia_semana' => null,
                'minutos_uso' => $configuracionOriginal['minutos_uso'],
                'dias_previos_apertura' => $configuracionOriginal['dias_previos_apertura'],
                'hora_apertura' => $configuracionOriginal['hora_apertura'],
                'tiempo_cancelacion' => $configuracionOriginal['tiempo_cancelacion'],
            ]);

            $this->copiarFranjasHorarias($configuracionOriginal['franjas_horarias'], $nuevaConfiguracion->id);
            return $nuevaConfiguracion->id;
        } catch (Throwable $th) {
            Log::error('Error al copiar configuración', [
                'error' => $th->getMessage(),
                'configuracion_original_id' => $configuracionOriginal['id'] ?? null,
                'fecha' => $fecha->toDateString()
            ]);
            throw new Exception('Error al copiar configuración: ' . $th->getMessage());
        }
    }

    private function copiarFranjasHorarias($franjasOriginales, $nuevaConfiguracionId)
    {
        try {
            foreach ($franjasOriginales as $franjaOriginal) {
                FranjaHoraria::create([
                    'id_config' => $nuevaConfiguracionId,
                    'hora_inicio' => $franjaOriginal['hora_inicio'],
                    'hora_fin' => $franjaOriginal['hora_fin'],
                    'valor' => $franjaOriginal['valor'],
                    'activa' => $franjaOriginal['activa'],
                ]);
            }
        } catch (Throwable $th) {
            Log::error('Error al copiar franjas horarias', [
                'error' => $th->getMessage(),
                'nueva_configuracion_id' => $nuevaConfiguracionId
            ]);
            throw new Exception('Error al copiar franjas horarias: ' . $th->getMessage());
        }
    }

    private function usuarioTieneReservaEnFecha($usuarioId, $fecha)
    {
        return Reservas::where('id_usuario', $usuarioId)
            ->whereDate('fecha', $fecha)
            ->whereIn('estado', ['inicial', 'completada', 'confirmada'])
            ->whereNull('eliminado_en')
            ->with(['espacio:id,nombre'])
            ->first();
    }

    public function getReservasUsuario($usuarioId, $fechaInicio = null, $fechaFin = null)
    {
        $query = Reservas::where('id_usuario', $usuarioId)
            ->whereIn('estado', ['inicial', 'completada', 'confirmada'])
            ->whereNull('eliminado_en')
            ->with([
                'espacio:id,nombre,id_sede',
                'espacio.sede:id,nombre'
            ])
            ->orderBy('fecha', 'desc')
            ->orderBy('hora_inicio', 'desc');

        if ($fechaInicio) {
            $query->whereDate('fecha', '>=', $fechaInicio);
        }

        if ($fechaFin) {
            $query->whereDate('fecha', '<=', $fechaFin);
        }

        return $query->get();
    }

    private function validarTipoUsuarioParaReserva(int $espacioId, string $tipoUsuario): void
    {
        $configTipoUsuario = EspacioTipoUsuarioConfig::where('id_espacio', $espacioId)
            ->where('tipo_usuario', $tipoUsuario)
            ->whereNull('eliminado_en')
            ->first();

        if (!$configTipoUsuario) {
            throw new Exception("Los usuarios <strong>{$tipoUsuario}s</strong> no tienen permitido reservar este espacio.");
        }
    }

    private function validarTiempoAperturaReserva(int $espacioId, string $tipoUsuario, Carbon $fechaReserva): void
    {
        $configuracionEspacio = $this->obtenerConfiguracionEspacio($espacioId, $fechaReserva);

        if (!$configuracionEspacio) {
            throw new Exception("No se encontró configuración para este espacio.");
        }

        $configTipoUsuario = EspacioTipoUsuarioConfig::where('id_espacio', $espacioId)
            ->where('tipo_usuario', $tipoUsuario)
            ->whereNull('eliminado_en')
            ->first();

        $minutosRetraso = $configTipoUsuario ? $configTipoUsuario->retraso_reserva : 0;

        $diasPreviosApertura = $configuracionEspacio->dias_previos_apertura ?? self::DIAS_PREVIOS_APERTURA_DEFAULT;
        $horaApertura = $configuracionEspacio->hora_apertura ?? self::HORA_APERTURA_DEFAULT;

        $fechaAperturaReserva = $fechaReserva->copy()->subDays($diasPreviosApertura);

        $horaAperturaConRetraso = Carbon::createFromFormat('H:i', $horaApertura)
            ->addMinutes($minutosRetraso);

        $fechaHoraApertura = $fechaAperturaReserva->copy()
            ->setTime($horaAperturaConRetraso->hour, $horaAperturaConRetraso->minute, 0);

        $ahora = Carbon::now();

        if ($ahora->lessThan($fechaHoraApertura)) {
            $fechaFormateada = $fechaHoraApertura->format('d/m/Y');
            $horaFormateada = $fechaHoraApertura->format('h:i A');

            throw new Exception(
                "Las reservas para el {$fechaReserva->format('d/m/Y')} " .
                    "estarán disponibles a partir del {$fechaFormateada} a las {$horaFormateada}."
            );
        }
    }

    private function validarLimitesReservasPorCategoria(int $espacioId, int $usuarioId, string $tipoUsuario, Carbon $fechaReserva): void
    {
        $espacio = Espacio::with('categoria')->find($espacioId);

        if (!$espacio || !$espacio->categoria) {
            throw new Exception("No se pudo obtener la información de la categoría del espacio.");
        }

        $campoLimite = "reservas_{$tipoUsuario}";
        $limiteReservas = $espacio->categoria->{$campoLimite} ?? 0;

        if ($limiteReservas <= 0) {
            throw new Exception("Los usuarios <strong>{$tipoUsuario}s</strong> no tienen permitido reservar espacios de la categoría <strong>{$espacio->categoria->nombre}</strong>.");
        }

        $reservasExistentes = Reservas::whereHas('espacio', function ($query) use ($espacio) {
            $query->where('id_categoria', $espacio->categoria->id);
        })
            ->where('id_usuario', $usuarioId)
            ->whereDate('fecha', $fechaReserva)
            ->whereIn('estado', ['inicial', 'completada', 'confirmada'])
            ->whereNull('eliminado_en')
            ->count();

        if ($reservasExistentes >= $limiteReservas) {
            $mensaje = $limiteReservas === 1
                ? "Ya tienes una reserva para esta fecha en la categoría <strong>{$espacio->categoria->nombre}</strong>. Solo se permite <strong>{$limiteReservas} reserva</strong> por día por usuario en esta categoría."
                : "Ya tienes <strong>{$reservasExistentes}</strong> reservas para esta fecha en la categoría <strong>{$espacio->categoria->nombre}</strong>. Solo se permiten <strong>{$limiteReservas} reservas</strong> por día por usuario en esta categoría.";

            throw new Exception($mensaje);
        }
    }

    private function obtenerConfiguracionEspacio(int $espacioId, Carbon $fecha)
    {
        $fechaConsulta = $fecha->toDateString();
        $diaSemana = $fecha->dayOfWeekIso;

        $configuracion = EspacioConfiguracion::where('id_espacio', $espacioId)
            ->whereDate('fecha', $fechaConsulta)
            ->first();

        if (!$configuracion) {
            $configuracion = EspacioConfiguracion::where('id_espacio', $espacioId)
                ->whereNull('fecha')
                ->where('dia_semana', $diaSemana)
                ->first();
        }

        return $configuracion;
    }

    public function getReservaById(int $id): Reservas
    {
        return Reservas::with([
            'espacio:id,nombre,id_sede',
            'espacio.sede:id,nombre',
            'configuracion',
            'configuracion.franjas_horarias',
            'usuarioReserva:id_usuario,email',
            'usuarioReserva.persona:id_persona,id_usuario,primer_nombre,segundo_nombre,primer_apellido,segundo_apellido,numero_documento,tipo_documento_id',
            'usuarioReserva.persona.tipoDocumento'
        ])->find($id);
    }

    public function getInfoReservasSimultaneas(int $espacioId, string $fecha, string $horaInicio, string $horaFin): array
    {
        $espacio = Espacio::find($espacioId);
        if (!$espacio) {
            return [
                'error' => 'Espacio no encontrado'
            ];
        }

        $reservasSimultaneasPermitidas = $espacio->reservas_simultaneas ?? 1;

        $horaInicioCarbon = Carbon::createFromFormat('h:i A', $horaInicio);
        $horaFinCarbon = Carbon::createFromFormat('h:i A', $horaFin);

        $reservasEnHorario = Reservas::where('id_espacio', $espacioId)
            ->whereDate('fecha', $fecha)
            ->whereIn('estado', ['inicial', 'completada', 'confirmada'])
            ->whereNull('eliminado_en')
            ->where(function ($query) use ($horaInicioCarbon, $horaFinCarbon) {
                $query->where(function ($q) use ($horaInicioCarbon, $horaFinCarbon) {
                    $q->whereTime('hora_inicio', '>=', $horaInicioCarbon->format('H:i:s'))
                        ->whereTime('hora_inicio', '<', $horaFinCarbon->format('H:i:s'));
                })->orWhere(function ($q) use ($horaInicioCarbon, $horaFinCarbon) {
                    $q->whereTime('hora_fin', '>', $horaInicioCarbon->format('H:i:s'))
                        ->whereTime('hora_fin', '<=', $horaFinCarbon->format('H:i:s'));
                })->orWhere(function ($q) use ($horaInicioCarbon, $horaFinCarbon) {
                    $q->whereTime('hora_inicio', '<=', $horaInicioCarbon->format('H:i:s'))
                        ->whereTime('hora_fin', '>=', $horaFinCarbon->format('H:i:s'));
                });
            })
            ->with(['usuarioReserva:id_usuario,email', 'usuarioReserva.persona:id_persona,id_usuario,primer_nombre,primer_apellido'])
            ->get();

        $numeroReservasActuales = $reservasEnHorario->count();
        $cuposDisponibles = max(0, $reservasSimultaneasPermitidas - $numeroReservasActuales);

        return [
            'espacio_nombre' => $espacio->nombre,
            'reservas_simultaneas_permitidas' => $reservasSimultaneasPermitidas,
            'reservas_actuales' => $numeroReservasActuales,
            'cupos_disponibles' => $cuposDisponibles,
            'disponible' => $cuposDisponibles > 0,
            'reservas_existentes' => $reservasEnHorario->map(function ($reserva) {
                return [
                    'id' => $reserva->id,
                    'usuario' => $this->construirNombreCompleto($reserva->usuarioReserva->persona ?? null),
                    'hora_inicio' => $reserva->hora_inicio,
                    'hora_fin' => $reserva->hora_fin,
                    'estado' => $reserva->estado
                ];
            })->toArray()
        ];
    }
}
