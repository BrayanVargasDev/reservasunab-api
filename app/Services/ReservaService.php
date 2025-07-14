<?php

namespace App\Services;

use App\Models\Espacio;
use App\Models\EspacioConfiguracion;
use App\Models\FranjaHoraria;
use App\Models\Reservas;
use App\Traits\ManageTimezone;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReservaService
{
    use ManageTimezone;

    const MINUTOS_USO_DEFAULT = 60;
    const DIAS_PREVIOS_APERTURA_DEFAULT = 1;
    const TIEMPO_CANCELACION_DEFAULT = 30;
    const HORA_APERTURA_DEFAULT = '08:00';

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
            ->select('hora_inicio', 'hora_fin', 'estado')
            ->get();

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

                    $reservaCoincidente = $reservasExistentes->first(function ($reserva) use ($horaActual, $horaFinSlot) {
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
                    $estilosColor = '#00a1cf';
                    $textColor = '#ffffff';

                    if ($reservaCoincidente) {
                        $disponible = false;
                        $estilosColor = '#ce013f';
                        $textColor = '#ffffff';
                    }

                    $slot = [
                        'hora_inicio' => $horaInicioSlot,
                        'hora_fin' => $horaFinSlotFormatted,
                        'disponible' => $disponible,
                        'valor' => $franja->valor,
                        'franja_id' => $franja->id, // Agregar ID de franja para debugging
                        'estilos' => [
                            'background_color' => $estilosColor,
                            'text_color' =>  $textColor,
                        ],
                        'novedad' => null,
                        'reserva' => $reservaCoincidente ? [
                            'estado' => $reservaCoincidente->estado,
                            'hora_inicio' => $this->createCarbonSafely($reservaCoincidente->hora_inicio, 'H:i:s')?->format('h:i A') ?? $reservaCoincidente->hora_inicio,
                            'hora_fin' => $this->createCarbonSafely($reservaCoincidente->hora_fin, 'H:i:s')?->format('h:i A') ?? $reservaCoincidente->hora_fin
                        ] : null
                    ];

                    if ($novedadCoincidente) {
                        $slot['novedad'] = [
                            'id' => $novedadCoincidente->id,
                            'descripcion' => $novedadCoincidente->descripcion,
                            'tipo' => $novedadCoincidente->tipo,
                            'hora_inicio' => $this->createCarbonSafely($novedadCoincidente->hora_inicio, 'H:i:s')?->format('h:i A') ?? $novedadCoincidente->hora_inicio,
                            'hora_fin' => $this->createCarbonSafely($novedadCoincidente->hora_fin, 'H:i:s')?->format('h:i A') ?? $novedadCoincidente->hora_fin
                        ];

                        if ($novedadCoincidente->tipo === 'mantenimiento' || $novedadCoincidente->tipo === 'cerrado') {
                            $slot['disponible'] = false;
                            $slot['estilos'] = [
                                'background_color' => '#edeef1',
                                'text_color' => '#757f8e',
                            ];
                        } else {
                            $slot['estilos'] = [
                                'background_color' => '#ffc408',
                                'text_color' => '#170f04',
                            ];
                        }
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
                // Crear fechas directamente en la zona horaria de la aplicación
                $fecha = Carbon::createFromFormat('Y-m-d', $data['fecha'], config('app.timezone'));
                $horaInicio = Carbon::createFromFormat('h:i A', $data['horaInicio'], config('app.timezone'));
                $horaFin = Carbon::createFromFormat('h:i A', $data['horaFin'], config('app.timezone'));

                Log::info('Iniciando reserva con zona horaria', [
                    'fecha_original' => $data['fecha'],
                    'hora_inicio_original' => $data['horaInicio'],
                    'hora_fin_original' => $data['horaFin'],
                    'fecha_procesada' => $fecha->format('Y-m-d H:i:s T'),
                    'hora_inicio_procesada' => $horaInicio->format('H:i:s T'),
                    'hora_fin_procesada' => $horaFin->format('H:i:s T'),
                    'timezone_config' => config('app.timezone')
                ]);

                // Guard: Verificar autenticación
                $usuario = Auth::user();
                if (!$usuario) {
                    throw new Exception('Usuario no autenticado.');
                }

                // Guard: Verificar conflictos de reserva
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

                if ($reservasConflicto->isNotEmpty()) {
                    Log::warning('Conflicto de reservas detectado', [
                        'espacio_id' => $data['base']['id'],
                        'fecha' => $fecha->toDateString(),
                        'hora_solicitada_inicio' => $horaInicio->format('H:i:s'),
                        'hora_solicitada_fin' => $horaFin->format('H:i:s'),
                        'reservas_conflicto' => $reservasConflicto->map(function ($r) {
                            return [
                                'id' => $r->id,
                                'hora_inicio' => $r->hora_inicio,
                                'hora_fin' => $r->hora_fin,
                                'estado' => $r->estado
                            ];
                        })->toArray()
                    ]);
                    throw new Exception('Ya existe una reserva para este espacio en el horario seleccionado.');
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

                Log::info('Reserva creada exitosamente', [
                    'reserva_id' => $reserva->id,
                    'resumen' => $resumenReserva,
                    'valor_calculado' => $valor,
                    'configuracion_usada' => $idConfiguracion,
                    'fecha_guardada_db' => $reserva->fecha
                ]);

                return $resumenReserva;
            });
        } catch (Throwable $th) {
            Log::error('Error al iniciar la reserva', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'data_received' => $data,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            throw new Exception('Error al iniciar la reserva: ' . $th->getMessage());
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

    private function obtenerValorReserva($data, $idConfiguracion, $horaInicio, $horaFin)
    {
        if (isset($data['valor'])) {
            return $data['valor'];
        }

        try {
            $configuracionCompleta = EspacioConfiguracion::with('franjas_horarias')
                ->find($idConfiguracion);

            if (!$configuracionCompleta || $configuracionCompleta->franjas_horarias->isEmpty()) {
                Log::warning('No se encontró configuración o franjas horarias', [
                    'configuracion_id' => $idConfiguracion
                ]);
                return null;
            }

            // Convertir las horas a strings para comparación simple (sin zona horaria)
            $horaInicioReserva = $horaInicio->format('H:i:s');
            $horaFinReserva = $horaFin->format('H:i:s');

            Log::info('Buscando franja para la reserva', [
                'hora_inicio_reserva' => $horaInicioReserva,
                'hora_fin_reserva' => $horaFinReserva,
                'configuracion_id' => $idConfiguracion,
                'total_franjas' => $configuracionCompleta->franjas_horarias->count()
            ]);

            $franjaCoincidente = $configuracionCompleta->franjas_horarias->first(function ($franja) use ($horaInicioReserva, $horaFinReserva) {
                try {
                    // Manejar diferentes formatos de hora que puedan venir de la base de datos
                    $franjaInicioStr = null;
                    $franjaFinStr = null;

                    // Si hora_inicio es un objeto Carbon/DateTime
                    if ($franja->hora_inicio instanceof \Carbon\Carbon) {
                        $franjaInicioStr = $franja->hora_inicio->format('H:i:s');
                    } elseif ($franja->hora_inicio instanceof \DateTime) {
                        $franjaInicioStr = $franja->hora_inicio->format('H:i:s');
                    } else {
                        // Si es una cadena, intentar varios formatos
                        $franjaInicioStr = $franja->hora_inicio;
                    }

                    if ($franja->hora_fin instanceof \Carbon\Carbon) {
                        $franjaFinStr = $franja->hora_fin->format('H:i:s');
                    } elseif ($franja->hora_fin instanceof \DateTime) {
                        $franjaFinStr = $franja->hora_fin->format('H:i:s');
                    } else {
                        $franjaFinStr = $franja->hora_fin;
                    }

                    // Normalizar el formato de hora a H:i:s
                    if (strlen($franjaInicioStr) === 5) { // formato H:i
                        $franjaInicioStr .= ':00';
                    }
                    if (strlen($franjaFinStr) === 5) { // formato H:i
                        $franjaFinStr .= ':00';
                    }

                    // Comparación simple de strings de tiempo
                    $coincide = $horaInicioReserva >= $franjaInicioStr && $horaFinReserva <= $franjaFinStr;

                    Log::info('Comparando franja', [
                        'franja_id' => $franja->id,
                        'franja_inicio' => $franjaInicioStr,
                        'franja_fin' => $franjaFinStr,
                        'reserva_inicio' => $horaInicioReserva,
                        'reserva_fin' => $horaFinReserva,
                        'coincide' => $coincide,
                        'valor_franja' => $franja->valor
                    ]);

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
            
            Log::info('Resultado de búsqueda de valor', [
                'franja_encontrada' => $franjaCoincidente ? $franjaCoincidente->id : null,
                'valor_obtenido' => $valor
            ]);

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

    private function construirNombreCompleto($persona)
    {
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
}
