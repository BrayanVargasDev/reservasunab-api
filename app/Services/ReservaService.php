<?php

namespace App\Services;

use App\Mail\ConfirmacionReservaEmail;
use App\Models\Beneficiario;
use App\Models\Espacio;
use App\Models\EspacioConfiguracion;
use App\Models\EspacioTipoUsuarioConfig;
use App\Models\Elemento;
use App\Models\FranjaHoraria;
use App\Models\Reservas;
use App\Models\Movimientos;
use App\Models\Mensualidades;
use App\Models\Pago;
use App\Models\Usuario;
use App\Traits\ManageTimezone;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

    private $api_key;
    private $url_pagos;
    private $entity_code;
    private $service_code;
    private $url_redirect_base = 'https://reservasunab.wgsoluciones.com/pagos/reservas';
    private $session_token;
    private $activarAgregarElementos;
    private CronJobsService $cron_service;

    public function __construct(CronJobsService $cron_service)
    {
        $this->api_key = config('app.key_pagos');
        $this->url_pagos = config('app.url_pagos');
        $this->entity_code = config('app.entity_code');
        $this->service_code = config('app.service_code');
        $this->activarAgregarElementos = config('app.activar_agregar_elementos', false);
        $this->session_token = null;
        $this->cron_service = $cron_service;
    }

    public function getSessionToken()
    {
        $url = "$this->url_pagos/getSessionToken";

        $data = [
            'EntityCode' => $this->entity_code,
            'ApiKey' => $this->api_key,
        ];

        try {
            $response = Http::post($url, $data);

            if (!$response->successful()) {
                throw new Exception('Error retrieving session token: ' . $response->body());
            }

            $this->session_token = $response->json()['SessionToken'];
            return $this->session_token;
        } catch (Throwable $th) {
            throw new Exception('Error retrieving session token: ' . $th->getMessage());
        }
    }

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
        } catch (Exception $e) {
            Log::warning('Error al crear Carbon desde formato', [
                'value' => $value,
                'format' => $format,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }

    public function getAllReservas(?string $search = '', int $per_page = 10)
    {
        $search = strtolower($search ?? '');
        $usuario = Auth::user();

        $rolNombre = optional($usuario->rol)->nombre;
        $esAdministrador = is_string($rolNombre) && strtolower($rolNombre) === 'administrador';

        $query = Reservas::query()
            ->withTrashed()
            ->with([
                'espacio',
                'espacio.sede:id,nombre',
                'espacio.categoria:id,nombre',
                'usuarioReserva:id_usuario,email',
                'configuracion',
                'configuracion.franjas_horarias',
                'usuarioReserva.persona:id_persona,id_usuario,primer_nombre,segundo_nombre,primer_apellido,segundo_apellido,numero_documento',
                'aprobadoPor',
                'aprobadoPor.persona'
            ]);

        if (!$esAdministrador) {
            // Filtrar por categorías permitidas si el usuario no es admin
            $categoriasPermitidas = $usuario->obtenerCategoriasPermitidas();
            if (!empty($categoriasPermitidas)) {
                $query->whereHas('espacio', function ($espacioQuery) use ($categoriasPermitidas) {
                    $espacioQuery->whereIn('id_categoria', $categoriasPermitidas);
                });
            } else {
                // Si no tiene categorías permitidas, solo ver sus propias reservas
                $query->where('id_usuario', $usuario->id_usuario);
            }
        }

        $query->orderBy('creado_en', 'desc');

        if ($search) {
            $query->search($search);
        }

        $reservas = $query->paginate($per_page);

        // Agregar bandera puede_cancelar a cada reserva sin alterar la consulta base
        $reservas->getCollection()->transform(function ($reserva) {
            try {
                $reserva->puede_cancelar = $reserva->puedeSerCancelada();
            } catch (\Throwable $e) {
                // En caso de error en el cálculo, por seguridad marcamos como no cancelable
                $reserva->puede_cancelar = false;
                Log::warning('Error calculando puede_cancelar para la reserva', [
                    'reserva_id' => $reserva->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
            return $reserva;
        });

        return $reservas;
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
                'novedades',
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
            ->select('*')
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
                'elementos',
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

        try {
            /** @var \App\Models\Usuario|null $usuario */
            $usuario = Auth::user();
            $tieneActiva = $usuario ? (bool) $usuario->tieneMensualidadActiva($espacio->id, $carbon) : false;
            $valorMensualDescuento = 0.0;
            try {
                if ($usuario && (bool)($espacio->pago_mensual ?? false)) {
                    $valorMensualDescuento = (float) $this->calcularValorMensualidadParaUsuario($espacio, $usuario);
                }
            } catch (\Throwable $e) {
                // noop
            }
            $espacio->usuario_mensualidad_activa = $tieneActiva || ($valorMensualDescuento === 0.0 && (bool)($espacio->pago_mensual ?? false));
            $espacio->mensualidad = $espacio->usuario_mensualidad_activa ? $usuario->mensualidadActivaMasActual() : null;
            $espacio->puede_agregar_elementos = $espacio->elementos && $espacio->elementos->isNotEmpty();
        } catch (\Throwable $th) {
            $espacio->usuario_mensualidad_activa = false;
            Log::warning('Error calculando usuario_mensualidad_activa', [
                'espacio_id' => $espacio->id ?? null,
                'fecha' => $fechaConsulta,
                'error' => $th->getMessage(),
            ]);
        }

        try {
            $this->cron_service->procesarNovedades($espacio->id);
        } catch (\Throwable $th) {
            Log::warning('Error procesando novedades para espacio en getEspacioDetalles', [
                'espacio_id' => $espacio->id ?? null,
                'fecha' => $fechaConsulta,
                'error' => $th->getMessage(),
            ]);
        }


        $espacio->disponibilidad = $this->construirDisponibilidad($espacio, $fechaConsulta);
        return $espacio;
    }

    public function construirDisponibilidad($espacio, string $fechaConsulta): array
    {
        $disponibilidad = [];
        $configuracion = $espacio->configuracion;

        if (!$configuracion) {
            return $disponibilidad;
        }

        $franjasHorarias = $configuracion->franjas_horarias;
        $novedades = $espacio->novedades
            ->whereNull('eliminado_en')
            ->sortBy('fecha')
            ->sortBy('hora_inicio');

        $reservasExistentes = Reservas::where('id_espacio', $espacio->id)
            ->whereDate('fecha', $fechaConsulta)
            ->whereNull('eliminado_en')
            ->select('hora_inicio', 'hora_fin', 'estado', 'id_usuario', 'id')
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

        $aplicaSinMensualidad = false;
        $usuarioTieneMensualidad = false;
        $requiereMensualidad = (bool) ($espacio->pago_mensual ?? false);
        try {
            /** @var \App\Models\Usuario|null $usuarioActual */
            $usuarioActual = Auth::user();
            $fechaCarbon = Carbon::createFromFormat('Y-m-d', $fechaConsulta);
            $esEstudiante = $usuarioActual && is_array($usuarioActual->tipos_usuario)
                ? in_array('estudiante', $usuarioActual->tipos_usuario)
                : false;

            if ($usuarioActual) {
                $usuarioTieneMensualidad = (bool) $usuarioActual->tieneMensualidadActiva($espacio->id, $fechaCarbon);
            }

            if ($requiereMensualidad && $esEstudiante) {
                $usuarioTieneMensualidad = true; // tratar como cubierto
                $aplicaSinMensualidad = false;
            } else {
                $valorMensualConDesc = 0.0;

                try {
                    if ($usuarioActual) {
                        $valorMensualConDesc = (float) $this->calcularValorMensualidadParaUsuario($espacio, $usuarioActual);
                    }
                } catch (\Throwable $e) {
                    // no
                }

                if ($requiereMensualidad && $valorMensualConDesc === 0.0) {
                    $usuarioTieneMensualidad = true;
                    $aplicaSinMensualidad = false;
                } else {
                    $aplicaSinMensualidad = $requiereMensualidad && (!$usuarioActual || !$usuarioTieneMensualidad);
                }
            }
        } catch (\Throwable $th) {
            $aplicaSinMensualidad = false;
            $usuarioTieneMensualidad = false;
            Log::warning('Fallo validando mensualidad en disponibilidad', [
                'espacio_id' => $espacio->id ?? null,
                'fecha' => $fechaConsulta,
                'error' => $th->getMessage(),
            ]);
        }

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
                            $reservaInicio = $this->createCarbonSafely($reserva->hora_inicio, 'Y-m-d H:i:s');
                            $reservaFin = $this->createCarbonSafely($reserva->hora_fin, 'Y-m-d H:i:s');

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
                    $idMiReserva = null;
                    if ($usuarioActual) {
                        $reservaUsuario = $reservasCoincidentes->first(function ($reserva) use ($usuarioActual) {
                            return $reserva->id_usuario == $usuarioActual->id_usuario;
                        });
                        if ($reservaUsuario) {
                            $miReserva = true;
                            $idMiReserva = $reservaUsuario->id ?? null;
                        }
                    }

                    $novedadCoincidente = $novedades->first(function ($novedad) use ($horaActual, $horaFinSlot) {
                        try {
                            if (!$novedad->hora_inicio || !$novedad->hora_fin) {
                                return false;
                            }

                            $novedadInicio = $this->createCarbonSafely($novedad->hora_inicio, 'Y-m-d H:i:s');
                            $novedadFin = $this->createCarbonSafely($novedad->hora_fin, 'Y-m-d H:i:s');

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
                    $slotInicioParaLimite = $fechaHoraReserva->copy();
                    $reservaPasada = $fechaHoraReserva->copy()->addMinutes(10)->lessThan($ahora);

                    try {
                        $limiteMinutosRaw = $espacio->getRawOriginal('tiempo_limite_reserva');
                        $despuesHoraRaw = $espacio->getRawOriginal('despues_hora');

                        if ($limiteMinutosRaw !== null && $despuesHoraRaw !== null) {
                            $limiteMinutos = (int) $limiteMinutosRaw;
                            $despuesHora = (bool) $despuesHoraRaw;

                            if ($limiteMinutos > 0) {
                                if ($despuesHora) {
                                    $momentoLimite = $slotInicioParaLimite->copy()->addMinutes($limiteMinutos);
                                    if ($ahora->greaterThan($momentoLimite)) {
                                        $disponible = false;
                                    }
                                } else {
                                    $momentoLimite = $slotInicioParaLimite->copy()->subMinutes($limiteMinutos);
                                    if ($ahora->greaterThanOrEqualTo($momentoLimite)) {
                                        $disponible = false;
                                    }
                                }
                            }
                        }
                    } catch (\Throwable $th) {
                        Log::warning('Fallo validando tiempo_limite_reserva en disponibilidad', [
                            'espacio_id' => $espacio->id ?? null,
                            'error' => $th->getMessage(),
                        ]);
                    }

                    // Aplicar descuento por tipo de usuario y asegurar valor numérico
                    [$valorConDescuento, $porcentajeAplicado] = $this->aplicarDescuentoPorTipoUsuario($franja->valor, $espacio->id, 'disponibilidad');
                    if ($requiereMensualidad && $usuarioTieneMensualidad) {
                        $valorConDescuento = 0;
                    }

                    $slot = [
                        'hora_inicio' => $horaInicioSlot,
                        'hora_fin' => $horaFinSlotFormatted,
                        'disponible' => $disponible,
                        'valor' => (float) $valorConDescuento,
                        'porcentaje_descuento' => (float) ($porcentajeAplicado ?? 0),
                        'valor_base' => $franja->valor, // Mantener el valor base para referencia
                        'franja_id' => $franja->id, // Agregar ID de franja para debugging
                        'mi_reserva' => $miReserva,
                        'reserva_pasada' => $reservaPasada,
                        'novedad' => false,
                        'id_reserva' => $miReserva ? $idMiReserva : null,
                        'reservada' => $numeroReservasEnSlot >= $reservasSimultaneasPermitidas,
                        'reservas_actuales' => $numeroReservasEnSlot,
                        'reservas_maximas' => $reservasSimultaneasPermitidas,
                        'cubierta_por_mensualidad' => $requiereMensualidad && $usuarioTieneMensualidad,
                    ];

                    if ($novedadCoincidente) {
                        $slot['novedad'] = true;
                        $slot['disponible'] = false;
                        $slot['novedad_desc'] = $novedadCoincidente->descripcion ?? 'Novedad en el espacio';
                    }

                    if ($aplicaSinMensualidad && !$reservaPasada) {
                        $slot['disponible'] = false;
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

        usort($disponibilidad, function ($a, $beneficiario) {
            return strtotime($a['hora_inicio']) <=> strtotime($beneficiario['hora_inicio']);
        });

        return $disponibilidad;
    }

    public function iniciarReserva($data)
    {
        try {
            // Preparar y validar, sin realizar escrituras en BD (solo lectura)
            $fecha = Carbon::createFromFormat('Y-m-d', $data['fecha'], config('app.timezone'));
            $horaInicio = Carbon::createFromFormat('h:i A', $data['horaInicio'], config('app.timezone'));
            $horaFin = Carbon::createFromFormat('h:i A', $data['horaFin'], config('app.timezone'));

            $usuario = Auth::user();
            if (!$usuario) {
                throw new Exception('Usuario no autenticado.');
            }

            $espacioId = $data['base']['id'] ?? null;
            if (!$espacioId) {
                throw new Exception('Espacio no especificado.');
            }

            $this->validarTipoUsuarioParaReserva($espacioId, $usuario->tipos_usuario);
            $this->validarTiempoAperturaReserva($espacioId, $usuario->tipos_usuario, $fecha);
            $this->validarLimitesReservasPorCategoria($espacioId, $usuario->id_usuario, $usuario->tipos_usuario, $fecha);

            $espacio = Espacio::with(['sede', 'elementos'])->find($espacioId);
            if (!$espacio) {
                throw new Exception('Espacio no encontrado.');
            }

            $reservasSimultaneasPermitidas = $espacio->reservas_simultaneas ?? 1;

            $reservasConflicto = Reservas::where('id_espacio', $espacioId)
                ->whereDate('fecha', $fecha)
                ->whereIn('estado', ['inicial', 'completada', 'confirmada', 'pagada'])
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
                Log::warning('Límite de reservas simultáneas alcanzado (preview)', [
                    'espacio_id' => $espacioId,
                    'fecha' => $fecha->toDateString(),
                    'hora_solicitada_inicio' => $horaInicio->format('H:i:s'),
                    'hora_solicitada_fin' => $horaFin->format('H:i:s'),
                    'reservas_actuales' => $numeroReservasEnHorario,
                    'reservas_maximas' => $reservasSimultaneasPermitidas,
                ]);
                $mensaje = $reservasSimultaneasPermitidas === 1
                    ? 'Horario ocupado.'
                    : "Límite de reservas: {$reservasSimultaneasPermitidas}. Ocupadas: {$numeroReservasEnHorario}.";
                throw new Exception($mensaje);
            }

            // Sólo lectura: obtener configuración efectiva sin crear copias
            $configuracionEfectiva = $this->obtenerConfiguracionEspacio($espacioId, $fecha);
            if (!$configuracionEfectiva) {
                throw new Exception('Sin configuración.');
            }

            $duracionMinutos = $horaInicio->diffInMinutes($horaFin);
            $valoresReserva = $this->obtenerValorReserva(null, $configuracionEfectiva->id, $horaInicio, $horaFin);
            $valor = $valoresReserva ? ($valoresReserva['valor_descuento'] ?? 0) : 0;

            // Mensualidad: si el espacio se paga con mensualidad y el usuario tiene una activa para la fecha, la reserva del espacio queda cubierta (valor 0)
            $coberturaMensualidad = $this->calcularCoberturaMensualidad($espacio, $usuario->id_usuario, $fecha);

            if ($coberturaMensualidad['pago_mensual'] && $coberturaMensualidad['tiene_mensualidad_activa']) {
                $valor = 0;
                if ($valoresReserva) {
                    $valoresReserva['valor_descuento'] = 0;
                    $valoresReserva['valor_real'] = 0;
                }
            }

            $requiereAprobacion = (bool) ($espacio->aprobar_reserva ?? false);
            $estadoPreview = (is_array($usuario->tipos_usuario) && in_array('estudiante', $usuario->tipos_usuario)) ? 'completada' : 'inicial';
            if ($requiereAprobacion) {
                $estadoPreview = 'pendienteap';
            }

            // Usuario/persona
            $usuarioConPersona = Usuario::with('persona')->find($usuario->id_usuario) ?: $usuario;
            $nombreCompleto = $this->construirNombreCompleto($usuarioConPersona->persona ?? null);

            // Cálculo de flags informativos
            $fechaHoraReserva = Carbon::createFromFormat('Y-m-d H:i:s', $fecha->format('Y-m-d') . ' ' . $horaInicio->format('H:i:s'));
            $esPasada = $fechaHoraReserva->copy()->addMinutes(10)->isPast();

            $tiempoCancelacion = $configuracionEfectiva->tiempo_cancelacion ?? self::TIEMPO_CANCELACION_DEFAULT;
            $limiteCancelacion = $fechaHoraReserva->copy()->subMinutes($tiempoCancelacion);
            $puedeCancelar = Carbon::now()->lessThan($limiteCancelacion);

            $jugadoresEntrada = isset($data['jugadores']) && is_array($data['jugadores']) ? $data['jugadores'] : [];

            $saldoFavorUsuario = $this->obtenerSaldoFavorUsuario(Auth::id());
            $pagar_con_saldo = $saldoFavorUsuario >= $valor;

            $resumenReserva = [
                'id' => null,
                'id_espacio' => $espacio->id,
                'id_configuracion_base' => $configuracionEfectiva->id,
                'id_configuracion' => $configuracionEfectiva->id,
                'nombre_espacio' => $espacio->nombre,
                'duracion' => $duracionMinutos,
                'sede' => $espacio->sede->nombre ?? null,
                'fecha' => $fecha->format('Y-m-d'),
                'hora_inicio' => $horaInicio->format('h:i A'),
                'hora_fin' => $horaFin->format('h:i A'),
                'valor' => $valoresReserva ? (float)($valoresReserva['valor_real'] ?? 0) : 0,
                'valor_descuento' => (float)$valor,
                'valor_elementos' => 0.0,
                'valor_total_reserva' => (float)$valor,
                'porcentaje_descuento' => $this->obtenerPorcentajeDescuento($espacio->id, $usuario->id_usuario),
                'estado' => $valor > 0 ? 'inicial' : $estadoPreview,
                'necesita_aprobacion' => $requiereAprobacion,
                'reserva_aprobada' => false,
                'usuario_reserva' => $nombreCompleto ?: 'Usuario sin nombre',
                'codigo_usuario' => $usuarioConPersona->persona->numero_documento ?? 'Sin código',
                'agrega_jugadores' => (bool) ($espacio->agregar_jugadores ?? false),
                'permite_externos' => (bool) ($espacio->permite_externos ?? false),
                'minimo_jugadores' => $espacio->minimo_jugadores ?? null,
                'maximo_jugadores' => $espacio->maximo_jugadores ?? null,
                'jugadores' => $jugadoresEntrada,
                'total_jugadores' => count($jugadoresEntrada),
                'es_pasada' => $esPasada,
                'puede_cancelar' => $puedeCancelar,
                'pagar_con_saldo' => $pagar_con_saldo,
                // info mensualidad
                'requiere_mensualidad' => (bool) ($coberturaMensualidad['pago_mensual']),
                'cubierta_por_mensualidad' => (bool) ($coberturaMensualidad['tiene_mensualidad_activa']),
                'mensualidad' => $coberturaMensualidad['mensualidad'],
                'puede_agregar_jugadores' => ($espacio->agregar_jugadores ?? false) &&
                    (($espacio->maximo_jugadores ?? 0) == 0 || count($jugadoresEntrada) < ($espacio->maximo_jugadores ?? 0)),
                'pago' => null,
                'puede_agregar_elementos' => $espacio->elementos && $espacio->elementos->isNotEmpty(),
            ];

            return $resumenReserva;
        } catch (Throwable $th) {
            Log::error('Error al iniciar la reserva (preview)', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'data_received' => $data,
                'error' => $th->getMessage(),
            ]);
            throw new Exception($th->getMessage());
        }
    }

    public function confirmarReserva(array $data)
    {
        DB::beginTransaction();
        try {
            $usuario = Auth::user();
            if (!$usuario) {
                throw new Exception('Usuario no autenticado.');
            }

            $reservaExistenteId = $data['id'] ?? null;
            if ($this->activarAgregarElementos && $reservaExistenteId) {
                $reserva = Reservas::with(['jugadores'])
                    ->lockForUpdate()
                    ->find($reservaExistenteId);

                if (!$reserva) {
                    throw new Exception('Reserva no encontrada.');
                }
                if ((int)$reserva->id_usuario !== (int)$usuario->id_usuario) {
                    throw new Exception('No tienes permisos para modificar esta reserva.');
                }
                if (!empty($data['id_espacio']) && (int)$data['id_espacio'] !== (int)$reserva->id_espacio) {
                    throw new Exception('El espacio enviado no coincide con la reserva.');
                }

                $detalles = isset($data['detalles']) && is_array($data['detalles']) ? $data['detalles'] : [];
                if (!empty($detalles)) {
                    $idsElementos = collect($detalles)->pluck('id')->filter()->unique()->values();
                    if ($idsElementos->isNotEmpty()) {
                        $elementos = Elemento::whereIn('id', $idsElementos)
                            ->get()
                            ->keyBy('id');

                        $existentes = DB::table('reservas_detalles')
                            ->where('id_reserva', $reserva->id)
                            ->whereIn('id_elemento', $idsElementos)
                            ->get()
                            ->keyBy('id_elemento');

                        $inserts = [];
                        foreach ($detalles as $detalle) {
                            $idElem = (int)($detalle['id'] ?? 0);
                            $cant = (int)($detalle['cantidad_seleccionada'] ?? 0);
                            if ($idElem <= 0 || $cant <= 0) {
                                continue;
                            }
                            $elem = $elementos->get($idElem);
                            if (!$elem) {
                                throw new Exception('Elemento no válido.');
                            }

                            if ($existentes->has($idElem)) {
                                DB::table('reservas_detalles')
                                    ->where('id_reserva', $reserva->id)
                                    ->where('id_elemento', $idElem)
                                    ->update([
                                        'cantidad' => DB::raw('cantidad + ' . (int)$cant),
                                    ]);
                            } else {
                                $inserts[] = [
                                    'id_reserva' => $reserva->id,
                                    'id_elemento' => $idElem,
                                    'cantidad' => $cant,
                                ];
                            }
                        }

                        if (!empty($inserts)) {
                            DB::table('reservas_detalles')->insert($inserts);
                        }
                    }
                }

                if (!empty($data['jugadores']) && is_array($data['jugadores'])) {
                    // En confirmación de reserva existente, no fallar por jugadores inexistentes
                    $this->agregarJugadores($reserva->id, $data['jugadores'], false);
                }

                DB::commit();
                return $this->getMiReserva($reserva->id);
            }

            $espacioId = $data['id_espacio'] ?? ($data['base']['id'] ?? null);
            if (!$espacioId) {
                throw new Exception('Espacio no especificado.');
            }

            $fechaStr = $data['fecha'] ?? null;
            $horaInicioStr = $data['hora_inicio'] ?? null;
            $horaFinStr = $data['hora_fin'] ?? null;
            $duracion = isset($data['duracion']) ? (int)$data['duracion'] : null;

            if (!$fechaStr || !$horaInicioStr) {
                throw new Exception('Fecha u hora de inicio no válidas.');
            }

            $fecha = Carbon::createFromFormat('Y-m-d', $fechaStr, config('app.timezone'));
            $horaInicio = Carbon::createFromFormat('h:i A', $horaInicioStr, config('app.timezone'));
            if ($horaFinStr) {
                $horaFin = Carbon::createFromFormat('h:i A', $horaFinStr, config('app.timezone'));
            } elseif ($duracion) {
                $horaFin = $horaInicio->copy()->addMinutes($duracion);
            } else {
                throw new Exception('Hora fin o duración requerida.');
            }

            $this->validarTipoUsuarioParaReserva($espacioId, $usuario->tipos_usuario);
            $this->validarTiempoAperturaReserva($espacioId, $usuario->tipos_usuario, $fecha);
            $this->validarLimitesReservasPorCategoria($espacioId, $usuario->id_usuario, $usuario->tipos_usuario, $fecha);

            $espacio = Espacio::with(['sede'])->find($espacioId);
            if (!$espacio) {
                throw new Exception('Espacio no encontrado.');
            }

            $reservasSimultaneasPermitidas = $espacio->reservas_simultaneas ?? 1;
            $reservasConflicto = Reservas::where('id_espacio', $espacioId)
                ->whereDate('fecha', $fecha)
                ->whereIn('estado', ['inicial', 'completada', 'confirmada', 'pendienteap', 'pagada'])
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
                ->lockForUpdate()
                ->get();

            if ($reservasConflicto->count() >= $reservasSimultaneasPermitidas) {
                throw new Exception('Horario ocupado al confirmar.');
            }

            $configBaseId = $data['id_configuracion_base'] ?? null;
            $configFecha = EspacioConfiguracion::where('id_espacio', $espacioId)
                ->whereDate('fecha', $fecha)
                ->first();

            if ($configFecha) {
                $idConfiguracion = $configFecha->id;
            } else {
                if (!$configBaseId) {
                    $configEfectiva = $this->obtenerConfiguracionEspacio($espacioId, $fecha);
                    if (!$configEfectiva) {
                        throw new Exception('Sin configuración.');
                    }
                    $configBaseId = $configEfectiva->id;
                }

                $configBase = EspacioConfiguracion::with('franjas_horarias')->find($configBaseId);
                if (!$configBase) {
                    throw new Exception('Configuración base no encontrada.');
                }

                if ($configBase->fecha && $configBase->fecha->toDateString() === $fecha->toDateString()) {
                    $idConfiguracion = $configBase->id;
                } else {
                    $idConfiguracion = $this->copiarConfiguracion($configBase->toArray(), $fecha);
                }
            }

            try {
                $configActual = EspacioConfiguracion::find($idConfiguracion);
                $necesitaCopiaPorFecha = !$configActual || is_null($configActual->fecha) || $configActual->fecha->toDateString() !== $fecha->toDateString();

                if ($necesitaCopiaPorFecha) {
                    $configPorFecha = EspacioConfiguracion::where('id_espacio', $espacioId)
                        ->whereDate('fecha', $fecha)
                        ->first();

                    if ($configPorFecha) {
                        $idConfiguracion = $configPorFecha->id;
                    } else {
                        $fuente = $configActual ?: EspacioConfiguracion::with('franjas_horarias')
                            ->where('id_espacio', $espacioId)
                            ->whereNull('fecha')
                            ->where('dia_semana', $fecha->dayOfWeekIso)
                            ->first();

                        if (!$fuente) {
                            throw new Exception('Sin configuración para copiar.');
                        }

                        if (!$fuente->relationLoaded('franjas_horarias')) {
                            $fuente->load('franjas_horarias');
                        }

                        $idConfiguracion = $this->copiarConfiguracion($fuente->toArray(), $fecha);
                    }
                }
            } catch (\Throwable $th) {
                Log::error('Error asegurando configuración por fecha al confirmar', [
                    'espacio_id' => $espacioId,
                    'fecha' => $fecha->toDateString(),
                    'config_id_inicial' => $idConfiguracion ?? null,
                    'error' => $th->getMessage(),
                ]);
                throw $th;
            }

            $valoresReserva = $this->obtenerValorReserva(null, $idConfiguracion, $horaInicio, $horaFin);
            $valorReserva = $valoresReserva ? (float)($valoresReserva['valor_descuento'] ?? 0) : 0.0;

            try {
                $cobertura = $this->calcularCoberturaMensualidad($espacio, (int)$usuario->id_usuario, $fecha);
                if ($cobertura['pago_mensual'] && $cobertura['tiene_mensualidad_activa']) {
                    $valorReserva = 0.0;
                    if ($valoresReserva) {
                        $valoresReserva['valor_descuento'] = 0.0;
                        $valoresReserva['valor_real'] = 0.0;
                    } else {
                        $valoresReserva = [
                            'valor_real' => 0.0,
                            'valor_descuento' => 0.0,
                        ];
                    }
                }
            } catch (\Throwable $th) {
                Log::warning('Fallo validando mensualidad en confirmarReserva', [
                    'espacio_id' => $espacioId,
                    'usuario_id' => $usuario->id_usuario ?? null,
                    'fecha' => $fecha->toDateString(),
                    'error' => $th->getMessage(),
                ]);
            }

            $detalles = isset($data['detalles']) && is_array($data['detalles']) ? $data['detalles'] : [];
            $valorElementos = 0.0;
            $detallesData = [];
            if (!empty($detalles)) {
                $idsElementos = collect($detalles)->pluck('id')->filter()->unique()->values();
                $elementos = Elemento::whereIn('id', $idsElementos)->get()->keyBy('id');

                foreach ($detalles as $detalle) {
                    $idElem = (int)($detalle['id'] ?? 0);
                    $cant = (int)($detalle['cantidad_seleccionada'] ?? 0);
                    if ($idElem <= 0 || $cant <= 0) {
                        continue;
                    }
                    $elem = $elementos->get($idElem);
                    if (!$elem) {
                        throw new Exception('Elemento no válido.');
                    }

                    $valorUnit = null;
                    $tipos = (array)($usuario->tipos_usuario ?? []);

                    if (in_array('estudiante', $tipos) && $elem->valor_estudiante !== null) $valorUnit = (float)$elem->valor_estudiante;
                    elseif (in_array('egresado', $tipos) && $elem->valor_egresado !== null) $valorUnit = (float)$elem->valor_egresado;
                    elseif (in_array('administrativo', $tipos) && $elem->valor_administrativo !== null) $valorUnit = (float)$elem->valor_administrativo;
                    elseif ($elem->valor_externo !== null) $valorUnit = (float)$elem->valor_externo;
                    else $valorUnit = 0.0;

                    $valorElementos += $valorUnit * $cant;

                    $detallesData[] = [
                        'id_elemento' => $idElem,
                        'cantidad' => $cant,
                        'valor_unitario' => $valorUnit
                    ];
                }
            }

            $margen = 0.01;
            if (isset($data['valor_descuento'])) {
                $valorDescEnviado = (float)$data['valor_descuento'];
                if (abs($valorDescEnviado - $valorReserva) > $margen) {
                    throw new Exception('Valor de reserva inválido.');
                }
            }
            if (isset($data['valor_elementos'])) {
                $valorElemEnviado = (float)$data['valor_elementos'];
                if (abs($valorElemEnviado - $valorElementos) > $margen) {
                    throw new Exception('Valor de elementos inválido.');
                }
            }

            $valorTotal = $valorReserva + $valorElementos;
            if (isset($data['valor_total_reserva'])) {
                $valorTotalEnviado = (float)$data['valor_total_reserva'];
                if (abs($valorTotalEnviado - $valorTotal) > $margen) {
                    throw new Exception('Valor total de la reserva inválido.');
                }
            }

            $estadoReserva = 'inicial';
            if ((float)$valorTotal <= 0) {
                $requiereAprobacion = (bool) ($espacio->aprobar_reserva ?? false);
                $estadoReserva = $requiereAprobacion ? 'pendienteap' : 'completada';
            }

            $reserva = Reservas::create([
                'id_usuario' => $usuario->id_usuario,
                'id_espacio' => $espacioId,
                'fecha' => $fecha,
                'id_configuracion' => $idConfiguracion,
                'estado' => $estadoReserva,
                'hora_inicio' => $horaInicio->format('H:i:s'),
                'hora_fin' => $horaFin->format('H:i:s'),
                'check_in' => false,
                'codigo' => Ulid::generate(),
                'precio_base' => $valoresReserva['valor_real'] ?? 0,
                'precio_espacio' => $valoresReserva['valor_descuento'] ?? 0,
                'precio_elementos' => $valorElementos,
                'precio_total' => $valorTotal,
                'porcentaje_aplicado' => $valoresReserva['porcentaje_aplicado'] ?? 0,
            ]);

            $jugadores = isset($data['jugadores']) && is_array($data['jugadores']) ? $data['jugadores'] : [];
            if (!empty($jugadores)) {
                $jugadoresData = [];
                $ahora = now();
                foreach ($jugadores as $jug) {
                    if (is_array($jug)) {
                        $idUsuarioJugador = $jug['id_usuario'] ?? null;
                        $idBeneficiarioJugador = $jug['id_beneficiario'] ?? null;
                    } else {
                        $idUsuarioJugador = (int) $jug;
                        $idBeneficiarioJugador = null;
                    }

                    if (!is_null($idUsuarioJugador) && $idUsuarioJugador < 0) {
                        $idBeneficiarioJugador = abs($idUsuarioJugador);
                        $idUsuarioJugador = null;
                    }

                    if (!is_null($idBeneficiarioJugador)) {
                        $beneficiarioExiste = Beneficiario::where('id', $idBeneficiarioJugador)
                            ->where('id_usuario', $usuario->id_usuario)
                            ->exists();
                        if (!$beneficiarioExiste) {
                            continue;
                        }
                    }

                    if ($idUsuarioJugador && $idUsuarioJugador == $usuario->id_usuario) {
                        continue;
                    }

                    $jugadoresData[] = [
                        'id_reserva' => $reserva->id,
                        'id_usuario' => $idUsuarioJugador,
                        'id_beneficiario' => $idBeneficiarioJugador,
                        'creado_en' => $ahora,
                        'actualizado_en' => $ahora,
                    ];
                }
                if (!empty($jugadoresData)) {
                    DB::table('reservas_jugadores')->insert($jugadoresData);
                }
            }

            if (!empty($detallesData)) {
                foreach ($detallesData as &$detalle) {
                    $detalle['id_reserva'] = $reserva->id;
                }

                DB::table('reservas_detalles')->insert($detallesData);
            }

            try {
                if ((float)($valorTotal ?? 0) <= 0) {
                    $this->cron_service->procesarReporteReservasMensualidades($reserva->id);
                    $reserva->load(['espacio.sede', 'usuarioReserva:id_usuario,email']);
                    Mail::to($reserva->usuarioReserva->email)
                        ->send(new ConfirmacionReservaEmail(
                            $reserva,
                            $valoresReserva['valor_real'] ?? 0,
                            $valoresReserva['valor_descuento'] ?? 0,
                        ));
                }
            } catch (Throwable $mailTh) {
                Log::warning('Error enviando correo de confirmación', ['reserva_id' => $reserva->id, 'error' => $mailTh->getMessage()]);
            }

            DB::commit();

            $ingresos = (float) Movimientos::where('id_usuario', $usuario->id_usuario)
                ->where('tipo', Movimientos::TIPO_INGRESO)
                ->sum('valor');
            $egresos = (float) Movimientos::where('id_usuario', $usuario->id_usuario)
                ->where('tipo', Movimientos::TIPO_EGRESO)
                ->sum('valor');
            $saldoFavor = $ingresos - $egresos;
            $puedePagarConSaldo = $valorTotal > 0 && $saldoFavor > 0 && $valorTotal <= $saldoFavor;

            $resumen = $this->getMiReserva($reserva->id);
            if (is_array($resumen)) {
                $resumen['pagar_con_saldo'] = $puedePagarConSaldo;
                $resumen['valor_elementos'] = $valorElementos;
                $resumen['valor_total_reserva'] = $valorTotal;
            }
            return $resumen;
        } catch (Throwable $th) {
            DB::rollBack();
            Log::error('Error al confirmar la reserva', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'data_received' => $data,
                'error' => $th->getMessage(),
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
            try {
                if ($data && isset($data['base']['id'])) {
                    $espacioTmp = Espacio::find($data['base']['id']);
                    if ($espacioTmp && $espacioTmp->aprobar_reserva) {
                        return [
                            'valor_real' => 0,
                            'valor_descuento' => 0,
                            'porcentaje_aplicado' => 0
                        ];
                    }
                }
            } catch (Exception $e) {
                Log::warning('Error verificando aprobación en valor explícito', ['error' => $e->getMessage()]);
            }
            return [
                'valor_real' => $data['valor'],
                'valor_descuento' => $data['valor'],
                'porcentaje_aplicado' => $data['porcentaje_descuento'] ?? 0
            ];
        }

        try {
            $configuracionCompleta = EspacioConfiguracion::with('franjas_horarias')
                ->find($idConfiguracion);

            if (!$configuracionCompleta || $configuracionCompleta->franjas_horarias->isEmpty()) {
                return null;
            }

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

            $valorReal = $franjaCoincidente ? $franjaCoincidente->valor : null;

            if ($valorReal === null) {
                return null;
            }

            try {
                $espacioAsociado = Espacio::find($configuracionCompleta->id_espacio);
                if ($espacioAsociado && $espacioAsociado->aprobar_reserva) {
                    return [
                        'valor_real' => 0,
                        'valor_descuento' => 0,
                        'porcentaje_aplicado' => 0
                    ];
                }
            } catch (Exception $e) {
                Log::warning('Error verificando aprobar_reserva del espacio', [
                    'configuracion_id' => $idConfiguracion,
                    'error' => $e->getMessage()
                ]);
            }

            $valoresDescuento = $this->aplicarDescuentoPorTipoUsuario($valorReal, $configuracionCompleta->id_espacio, 'obtenerValorReserva');

            return [
                'valor_real' => $valorReal,
                'valor_descuento' => $valoresDescuento[0],
                'porcentaje_aplicado' => $valoresDescuento[1],
            ];
        } catch (Exception $e) {
            Log::error('Error al obtener valor de franja', [
                'configuracion_id' => $idConfiguracion,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    public function aplicarDescuentoPorTipoUsuario($valorBase, $espacioId, ?string $contexto = null)
    {
        try {
            $usuario = Auth::user();

            if (!$usuario || !$usuario->tipos_usuario || empty($usuario->tipos_usuario)) {
                return [$valorBase, 0];
            }

            $configTipoUsuario = EspacioTipoUsuarioConfig::where('id_espacio', $espacioId)
                ->whereIn('tipo_usuario', $usuario->tipos_usuario)
                ->whereNull('eliminado_en')
                ->orderBy('porcentaje_descuento', 'desc')
                ->first();

            if (!$configTipoUsuario || !$configTipoUsuario->porcentaje_descuento) {
                return [$valorBase, 0];
            }

            $porcentajeDescuento = $configTipoUsuario->porcentaje_descuento;
            $descuento = ($valorBase * $porcentajeDescuento) / 100;
            $valorFinal = $valorBase - $descuento;

            $valorFinal = max(0, $valorFinal);

            return [$valorFinal, $porcentajeDescuento];
        } catch (Exception $e) {
            Log::error('Error al aplicar descuento por tipo de usuario', [
                'error' => $e->getMessage(),
                'espacio_id' => $espacioId,
                'valor_base' => $valorBase,
                'trace' => $e->getTraceAsString()
            ]);

            return [$valorBase, 0];
        }
    }

    /**
     * Calcula el valor de la mensualidad de un espacio aplicando el mejor descuento por tipo de usuario.
     * Reglas:
     * - Si el usuario es estudiante, el valor es 0.
     * - Si no, se toma el valor base del espacio y se aplica el porcentaje de descuento configurado.
     */
    public function calcularValorMensualidadParaUsuario(Espacio $espacio, ?Usuario $usuario = null): float
    {
        try {
            $usuario = $usuario ?: Auth::user();
            $valorBase = (float) ($espacio->valor_mensualidad ?? 0);
            if ($valorBase <= 0) {
                return 0.0;
            }

            $tipos = (array) ($usuario?->tipos_usuario ?? []);

            if (in_array('estudiante', $tipos, true)) {
                return 0.0;
            }

            [$valorConDescuento] = $this->aplicarDescuentoPorTipoUsuario($valorBase, $espacio->id, 'mensualidad');
            return (float) $valorConDescuento;
        } catch (\Throwable $th) {
            Log::warning('Fallo calculando valor de mensualidad con descuento', [
                'espacio_id' => $espacio->id ?? null,
                'error' => $th->getMessage(),
            ]);
            return (float) ($espacio->valor_mensualidad ?? 0);
        }
    }

    public function construirNombreCompleto($persona)
    {
        if (!$persona) {
            return 'Usuario sin nombre';
        }

        $partes = array_filter([
            trim($persona->primer_nombre ?? ''),
            trim($persona->segundo_nombre ?? ''),
            trim($persona->primer_apellido ?? ''),
            trim($persona->segundo_apellido ?? '')
        ]);

        return implode(' ', $partes);
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

    private function validarTipoUsuarioParaReserva(int $espacioId, array $tiposUsuario): void
    {
        $configsPermitidas = EspacioTipoUsuarioConfig::where('id_espacio', $espacioId)
            ->whereIn('tipo_usuario', $tiposUsuario)
            ->whereNull('eliminado_en')
            ->exists();

        if (!$configsPermitidas) {
            $tiposStr = implode(', ', $tiposUsuario);
            throw new Exception("No permitido para los tipos de usuario: {$tiposStr}.");
        }
    }

    private function validarTiempoAperturaReserva(int $espacioId, array $tiposUsuario, Carbon $fechaReserva): void
    {
        $configuracionEspacio = $this->obtenerConfiguracionEspacio($espacioId, $fechaReserva);

        if (!$configuracionEspacio) {
            throw new Exception("Sin configuración.");
        }

        // Obtener la configuración con menor retraso de reserva de todos los tipos del usuario
        $configTipoUsuario = EspacioTipoUsuarioConfig::where('id_espacio', $espacioId)
            ->whereIn('tipo_usuario', $tiposUsuario)
            ->whereNull('eliminado_en')
            ->orderBy('retraso_reserva', 'asc')
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
                "Disponible para reservar desde el {$fechaFormateada} {$horaFormateada}."
            );
        }
    }

    private function validarLimitesReservasPorCategoria(
        int $espacioId,
        int $usuarioId,
        array $tiposUsuario,
        Carbon $fechaReserva
    ): void {
        $espacio = Espacio::with('categoria')->find($espacioId);

        if (!$espacio || !$espacio->categoria) {
            throw new Exception("Sin categoría.");
        }

        $estadosActivos = ['inicial', 'pendienteap', 'completada', 'confirmada', 'pagada'];

        foreach ($tiposUsuario as $tipoUsuario) {
            $campoLimite = "reservas_{$tipoUsuario}";
            $limiteReservas = $espacio->categoria->{$campoLimite} ?? 0;

            if ($limiteReservas <= 0) {
                continue;
            }

            $baseQuery = Reservas::whereHas('espacio', function ($query) use ($espacio) {
                $query->where('id_categoria', $espacio->categoria->id);
            })
                ->whereDate('fecha', $fechaReserva)
                ->whereIn('estado', $estadosActivos)
                ->whereNull('eliminado_en');

            $propias = (clone $baseQuery)
                ->where('id_usuario', $usuarioId)
                ->count();

            $comoJugador = (clone $baseQuery)
                ->whereHas('jugadores', function ($q) use ($usuarioId) {
                    $q->where('id_usuario', $usuarioId);
                })
                ->count();

            $total = $propias + $comoJugador;

            if ($total >= $limiteReservas) {
                throw new Exception("No puedes reservar, ya has reservado o fuiste incluido en otra reserva y se supera el límite.");
            }
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
            'usuarioReserva:id_usuario,email,tipos_usuario',
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
            ->whereIn('estado', ['inicial', 'completada', 'confirmada', 'pagada'])
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

    public function getInfoDescuentoUsuario(int $espacioId, int $usuarioId): array
    {
        try {
            $usuario = $usuarioId ? Usuario::find($usuarioId) : Auth::user();

            if (!$usuario || !$usuario->tipos_usuario || empty($usuario->tipos_usuario)) {
                return [
                    'tiene_descuento' => false,
                    'porcentaje_descuento' => 0,
                    'tipos_usuario' => null,
                    'mensaje' => 'Usuario no encontrado o sin tipos de usuario'
                ];
            }

            // Obtener la configuración con mayor descuento de todos los tipos del usuario
            $configTipoUsuario = EspacioTipoUsuarioConfig::where('id_espacio', $espacioId)
                ->whereIn('tipo_usuario', $usuario->tipos_usuario)
                ->whereNull('eliminado_en')
                ->orderBy('porcentaje_descuento', 'desc')
                ->first();

            if (!$configTipoUsuario) {
                $tiposStr = implode(', ', $usuario->tipos_usuario);
                return [
                    'tiene_descuento' => false,
                    'porcentaje_descuento' => 0,
                    'tipos_usuario' => $usuario->tipos_usuario,
                    'mensaje' => "No hay configuración para usuarios tipos '{$tiposStr}' en este espacio"
                ];
            }

            $porcentajeDescuento = $configTipoUsuario->porcentaje_descuento ?? 0;

            return [
                'tiene_descuento' => $porcentajeDescuento > 0,
                'porcentaje_descuento' => $porcentajeDescuento,
                'tipos_usuario' => $usuario->tipos_usuario,
                'tipo_usuario_descuento' => $configTipoUsuario->tipo_usuario,
                'mensaje' => $porcentajeDescuento > 0
                    ? "Descuento del {$porcentajeDescuento}% aplicable como {$configTipoUsuario->tipo_usuario}"
                    : "Sin descuento disponible",
                'retraso_reserva' => $configTipoUsuario->retraso_reserva ?? 0
            ];
        } catch (Exception $e) {
            Log::error('Error al obtener información de descuento', [
                'error' => $e->getMessage(),
                'espacio_id' => $espacioId,
                'usuario_id' => $usuarioId,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'tiene_descuento' => false,
                'porcentaje_descuento' => 0,
                'tipos_usuario' => null,
                'mensaje' => 'Error al obtener información de descuento',
                'error' => $e->getMessage()
            ];
        }
    }

    public function getMisReservas(int $usuarioId, string | null $search = '')
    {
        $query = Reservas::with([
            'pago',
            'usuarioReserva',
            'usuarioReserva.persona' => function ($q) {
                $q->select('id_persona', 'id_usuario', 'primer_nombre', 'primer_apellido', 'numero_documento');
            },
            'espacio:id,nombre,id_sede,id_categoria,aprobar_reserva',
            'espacio.sede:id,nombre',
            'espacio.categoria:id,nombre',
            'espacio.imagen:id_espacio,ubicacion',
            'configuracion:id,id_espacio,tiempo_cancelacion',
        ])->where('id_usuario', $usuarioId);

        $query->when($search, function ($q) use ($search) {
            $q->where(function ($subQ) use ($search) {
                $subQ->orWhere('codigo', 'like', "%{$search}%");

                $subQ->orWhereHas('pago', function ($pagoQ) use ($search) {
                    $pagoQ->whereRaw('LOWER(codigo) LIKE ?', ['%' . strtolower($search) . '%']);
                });
                $subQ->orWhereHas('espacio', function ($espacioQ) use ($search) {
                    $espacioQ->whereRaw('LOWER(nombre) LIKE ?', ['%' . strtolower($search) . '%']);
                });
                $subQ->orWhereHas('espacio.sede', function ($sedeQ) use ($search) {
                    $sedeQ->whereRaw('LOWER(nombre) LIKE ?', ['%' . strtolower($search) . '%']);
                });
                $subQ->orWhereHas('espacio.categoria', function ($catQ) use ($search) {
                    $catQ->whereRaw('LOWER(nombre) LIKE ?', ['%' . strtolower($search) . '%']);
                });

                $fechaNormalizada = null;
                if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $search, $m)) {
                    $fechaNormalizada = $m[3] . '-' . $m[2] . '-' . $m[1];
                } elseif (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{2})$/', $search, $m)) {
                    $anio = (int)$m[3] < 50 ? '20' . $m[3] : '19' . $m[3];
                    $fechaNormalizada = $anio . '-' . $m[2] . '-' . $m[1];
                } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $search, $m)) {
                    $fechaNormalizada = $m[1] . '-' . $m[2] . '-' . $m[3];
                }
                if ($fechaNormalizada) {
                    $subQ->orWhereDate('fecha', $fechaNormalizada);
                }
                $subQ->orWhere('hora_inicio', 'like', "%{$search}%");
            });
        });

        $query->orderBy('fecha', 'desc')
            ->orderBy('hora_inicio', 'desc');

        $reservas = $query->get();

        $saldoFavorUsuario = $this->obtenerSaldoFavorUsuario($usuarioId);

        $reservas->each(function ($reserva) use ($saldoFavorUsuario) {
            $reserva->es_pasada = $this->esReservaPasada($reserva);
            $reserva->puede_cancelar = $reserva->puedeSerCancelada();
            $reserva->porcentaje_descuento = $this->obtenerPorcentajeDescuento($reserva->id_espacio, $reserva->id_usuario);
            $reserva->necesita_aprobacion = (bool) ($reserva->espacio->aprobar_reserva ?? false);
            $reserva->reserva_aprobada = $reserva->estado === 'aprobada';
            $reserva->pagar_con_saldo = $this->puedePagarConSaldoReserva($reserva, $saldoFavorUsuario);

            // Mensualidad: banderas en listado
            try {
                $fechaReserva = $reserva->fecha instanceof Carbon ? $reserva->fecha : Carbon::parse($reserva->fecha);
                $espacio = $reserva->relationLoaded('espacio') ? $reserva->espacio : Espacio::find($reserva->id_espacio);
                $cobertura = $this->calcularCoberturaMensualidad($espacio, (int)$reserva->id_usuario, $fechaReserva);
                $reserva->requiere_mensualidad = (bool) $cobertura['pago_mensual'];
                $reserva->cubierta_por_mensualidad = (bool) $cobertura['tiene_mensualidad_activa'];
                $reserva->mensualidad = $cobertura['mensualidad'];
                if ($reserva->cubierta_por_mensualidad) {
                    // Si está cubierta, no aplica pago con saldo para la parte del espacio
                    $reserva->pagar_con_saldo = false;
                }
            } catch (\Throwable $th) {
                // noop
            }
        });

        return $reservas;
    }

    public function getMisReservasCancelables(int $usuarioId, int $perPage = 10)
    {
        $query = Reservas::where('id_usuario', $usuarioId)
            // ->puedeCancelar()
            // ->whereIn('estado', ['inicial', 'pagada', 'completada', 'confirmada', 'aprobada'])
            ->whereNull('eliminado_en');

        $query->orderBy('fecha', 'asc')
            ->orderBy('hora_inicio', 'asc')
            ->with([
                'pago',
                'espacio:id,nombre,id_sede,id_categoria',
                'espacio.sede:id,nombre',
                'espacio.categoria:id,nombre',
                'espacio.imagen:id_espacio,ubicacion',
                'configuracion:id,id_espacio,tiempo_cancelacion',
            ]);

        return $query->paginate($perPage);
    }

    public function getMiReserva(int $id_reserva)
    {
        $resumenReserva = null;

        try {
            $reserva = Reservas::withTrashed()->with([
                'espacio:id,nombre,id_sede,agregar_jugadores,permite_externos,minimo_jugadores,maximo_jugadores,aprobar_reserva,pago_mensual,valor_mensualidad',
                'espacio.sede:id,nombre',
                'espacio.elementos:id',
                'usuarioReserva',
                'configuracion',
                'configuracion.franjas_horarias',
                'pago',
                'jugadores',
                'jugadores.usuario',
                'jugadores.usuario.persona',
                'jugadores.usuario.persona.tipoDocumento',
                'jugadores.beneficiario',
                'jugadores.beneficiario.tipoDocumento',
                'usuarioReserva.persona:id_persona,id_usuario,primer_nombre,segundo_nombre,primer_apellido,segundo_apellido,numero_documento',
                'detalles.elemento'
            ])->find($id_reserva);

            if (!$reserva) {
                return null;
            }

            if (!$reserva->relationLoaded('pago')) {
                $reserva->load('pago');
            }

            if (!$reserva->pago) {
                $pago = Pago::whereHas('detalles', function ($q) use ($reserva) {
                    $q->where('tipo_concepto', 'reserva')
                        ->where('id_concepto', $reserva->id);
                })->with('detalles')->first();

                if ($pago) {
                    $reserva->setRelation('pago', $pago);
                } else {
                    //
                }
            }

            $fecha = $reserva->fecha instanceof Carbon ? $reserva->fecha : Carbon::parse($reserva->fecha);
            $horaInicio = $reserva->hora_inicio instanceof Carbon ? $reserva->hora_inicio : Carbon::createFromFormat('H:i:s', $reserva->hora_inicio);
            $horaFin = $reserva->hora_fin instanceof Carbon ? $reserva->hora_fin : Carbon::createFromFormat('H:i:s', $reserva->hora_fin);

            $duracionMinutos = $horaInicio->diffInMinutes($horaFin);
            $nombreCompleto = $this->construirNombreCompleto($reserva->usuarioReserva->persona ?? null);

            if ($reserva->pago && $reserva->pago->estado != 'OK') {
                DB::beginTransaction();
                try {

                    $url = $this->url_pagos . "/getTransactionInformation";

                    if (!$this->session_token) {
                        $this->getSessionToken();
                    }

                    $response = Http::post($url, [
                        'SessionToken' => $this->session_token,
                        'EntityCode' => $this->entity_code,
                        'TicketId' => $reserva->pago->ticket_id ?? null,
                    ]);

                    if (!$response->successful()) {
                        Log::warning('Error al obtener información de pago', [
                            'ticket_id' => $reserva->pago->ticket_id ?? null,
                            'response' => $response->body()
                        ]);
                        DB::rollBack();
                    } else {
                        $pagoData = $response->json();
                        if ($reserva->pago) {
                            $reserva->pago->estado = $pagoData['TranState'] ?? 'desconocido';
                            $reserva->pago->save();
                        }
                        $reserva->estado = $this->getReservaEstadoByPagoEstado($pagoData['TranState']);
                        $reserva->save();
                    }
                    DB::commit();
                } catch (Throwable $th) {
                    DB::rollBack();
                    Log::error('Error obteniendo información de la reserva: ' . $th->getMessage());
                }
            }

            $jugadores = [];

            if ($reserva->jugadores->isNotEmpty()) {
                foreach ($reserva->jugadores as $jugador) {
                    $jugadorInfo = [
                        'id' => $jugador->id,
                        'id_usuario' => $jugador->id_usuario,
                        'id_beneficiario' => $jugador->id_beneficiario,
                    ];

                    if ($jugador->id_usuario && $jugador->usuario && $jugador->usuario->persona) {
                        $jugadorInfo['nombre'] = trim($jugador->usuario->persona->primer_nombre . ' ' . $jugador->usuario->persona->segundo_nombre);
                        $jugadorInfo['apellido'] = trim($jugador->usuario->persona->primer_apellido . ' ' . $jugador->usuario->persona->segundo_apellido);
                        $jugadorInfo['email'] = $jugador->usuario->email;
                        $jugadorInfo['ldap_uid'] = $jugador->usuario->ldap_uid ?? null;
                        $jugadorInfo['documento'] = $jugador->usuario->persona->numero_documento ?? null;
                        $jugadorInfo['codigo_tipo_documento'] = $jugador->usuario->persona->tipoDocumento->codigo ?? null;
                        $jugadorInfo['es_beneficiario'] = false;
                    } elseif ($jugador->id_beneficiario && $jugador->beneficiario) {
                        $jugadorInfo['nombre'] = $jugador->beneficiario->nombre;
                        $jugadorInfo['apellido'] = $jugador->beneficiario->apellido;
                        $jugadorInfo['email'] = null;
                        $jugadorInfo['ldap_uid'] = null;
                        $jugadorInfo['documento'] = $jugador->beneficiario->documento;
                        $jugadorInfo['codigo_tipo_documento'] = optional($jugador->beneficiario->tipoDocumento)->codigo;
                        $jugadorInfo['es_beneficiario'] = true;
                    } else {
                        $jugadorInfo['nombre'] = '';
                        $jugadorInfo['apellido'] = '';
                        $jugadorInfo['email'] = null;
                        $jugadorInfo['ldap_uid'] = null;
                        $jugadorInfo['documento'] = null;
                        $jugadorInfo['codigo_tipo_documento'] = null;
                        $jugadorInfo['es_beneficiario'] = false;
                    }

                    $jugadores[] = $jugadorInfo;
                }
            }

            $detalles = [];
            if ($reserva->relationLoaded('detalles') && $reserva->detalles) {
                foreach ($reserva->detalles as $d) {

                    $detalles[] = [
                        'id' => $d->id_elemento,
                        'nombre' => $d->elemento?->nombre,
                        'cantidad' => (int) $d->cantidad,
                        'valor' => (float) $d->valor_unitario,
                        'cantidad_seleccionada' => (int) $d->cantidad,
                    ];
                }
            }

            $valorElementos = 0.0;
            if (!empty($detalles)) {
                $usuario = $reserva->usuarioReserva;
                $tipos = (array)($usuario->tipos_usuario ?? []);

                foreach ($detalles as $detalle) {
                    $cantidad = (int)$detalle['cantidad'];
                    $valorUnit = (float)$detalle['valor'];

                    $valorElementos += $valorUnit * $cantidad;
                }
            }

            $pagoResumen = null;
            if ($reserva->pago) {
                $pagoResumen = [
                    'codigo' => $reserva->pago->codigo ?? null,
                    'valor' => (float) ($reserva->pago->valor ?? 0),
                    'estado' => $reserva->pago->estado ?? null,
                    'detalles' => $reserva->pago->relationLoaded('detalles') && $reserva->pago->detalles
                        ? $reserva->pago->detalles->map(function ($d) {
                            return [
                                'tipo_concepto' => $d->tipo_concepto,
                                'id_concepto' => $d->id_concepto,
                                'cantidad' => (int) $d->cantidad,
                                'total' => (float) $d->total,
                            ];
                        })->values()->all() : [],
                ];
            }

            if (!$pagoResumen) {
                $pagoResumen = Movimientos::where('id_reserva', $reserva->id)
                    ->where('tipo', 'egreso')
                    ->where('valor', $reserva->precio_total)
                    ->where('id_usuario', $reserva->id_usuario)
                    ->whereNull('eliminado_en')
                    ->first();
            }

            $coberturaMensualidad = $this->calcularCoberturaMensualidad($reserva->espacio, (int)$reserva->id_usuario, $fecha);

            $resumenReserva = [
                'id' => $reserva->id,
                'id_espacio' => $reserva->id_espacio,
                'id_configuracion' => $reserva->id_configuracion,
                'id_configuracion_base' => $reserva->id_configuracion,
                'nombre_espacio' => $reserva->espacio->nombre ?? null,
                'duracion' => $duracionMinutos,
                'sede' => $reserva->espacio->sede->nombre ?? null,
                'fecha' => $fecha->format('Y-m-d'),
                'hora_inicio' => $horaInicio->format('h:i A'),
                'hora_fin' => $horaFin->format('h:i A'),
                'valor' => (float)$reserva->precio_base,
                'valor_descuento' => (float)$reserva->precio_espacio,
                'valor_elementos' => (float)$reserva->precio_elementos,
                'valor_total_reserva' => (float)$reserva->precio_total,
                'porcentaje_descuento' => $this->obtenerPorcentajeDescuento($reserva->id_espacio, $reserva->id_usuario),
                'estado' => $reserva->estado,
                'usuario_reserva' => $nombreCompleto ?: 'Usuario sin nombre',
                'codigo_usuario' => $reserva->usuarioReserva->persona->numero_documento ?? 'Sin código',
                'agrega_jugadores' => $reserva->espacio->agregar_jugadores ?? false,
                'permite_externos' => $reserva->espacio->permite_externos ?? false,
                'minimo_jugadores' => $reserva->espacio->minimo_jugadores ?? null,
                'maximo_jugadores' => $reserva->espacio->maximo_jugadores ?? null,
                'jugadores' => $jugadores,
                'detalles' => $detalles,
                'total_jugadores' => count($jugadores),
                'es_pasada' => $this->esReservaPasada($reserva),
                'puede_cancelar' => $reserva->puedeSerCancelada(),
                'puede_agregar_jugadores' => ($reserva->espacio->agregar_jugadores ?? false) &&
                    (($reserva->espacio->maximo_jugadores ?? 0) == 0 ||
                        count($jugadores) < ($reserva->espacio->maximo_jugadores ?? 0)),
                'necesita_aprobacion' => (bool) ($reserva->espacio->aprobar_reserva ?? false),
                'reserva_aprobada' => $reserva->estado === 'aprobada',
                // alias con la falta de ortografía que el front espera
                'reserva_aprovada' => $reserva->estado === 'aprobada',
                'pagar_con_saldo' => $this->puedePagarConSaldoReserva($reserva),
                // info mensualidad
                'requiere_mensualidad' => (bool) ($coberturaMensualidad['pago_mensual']),
                'cubierta_por_mensualidad' => (bool) ($coberturaMensualidad['tiene_mensualidad_activa']),
                'mensualidad' => $coberturaMensualidad['mensualidad'],
                // Información de pago con detalles (polimórfica por tipo_concepto)
                'pago' => $pagoResumen,
                'creado_en' => Carbon::parse($reserva->creado_en)->format('d/m/Y h:i A'),
                'puede_agregar_elementos' => $reserva->espacio->elementos && $reserva->espacio->elementos->isNotEmpty(),
            ];

            return $resumenReserva;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getReservaEstadoByPagoEstado($estado)
    {
        if ($estado === 'OK') {
            return 'pagada';
        }

        return 'inicial';
    }

    public function agregarJugadores(int $idReserva, array $jugadoresIds, bool $strict = true)
    {
        try {
            $reserva = Reservas::with(['jugadores', 'usuarioReserva'])->find($idReserva);

            if (!$reserva) {
                throw new Exception('La reserva no existe.');
            }

            $usuarioAutenticado = Auth::id();
            if ($reserva->id_usuario !== $usuarioAutenticado) {
                throw new Exception('No tienes permisos para agregar jugadores a esta reserva.');
            }

            $fechaHoraReserva = Carbon::parse($reserva->fecha->format('Y-m-d') . ' ' . $reserva->hora_inicio->format('H:i:s'));
            if ($fechaHoraReserva->isPast()) {
                throw new Exception('No se pueden agregar jugadores a una reserva que ya ha pasado.');
            }

            $jugadoresData = [];
            $idsUsuariosAValidar = [];
            $idsBeneficiariosAValidar = [];
            $itemsNormalizados = [];
            foreach ($jugadoresIds as $item) {
                $usuario = null;
                $beneficiario = null;
                if (is_array($item)) {
                    $usuario = isset($item['id_usuario']) ? (int)$item['id_usuario'] : null;
                    $beneficiario = isset($item['id_beneficiario']) ? (int)$item['id_beneficiario'] : null;
                } else {
                    $val = (int)$item;
                    if ($val < 0) {
                        $beneficiario = abs($val);
                    } else {
                        $usuario = $val;
                    }
                }

                if (!is_null($usuario) && $usuario == $reserva->id_usuario) {
                    $usuario = null;
                }

                if (!is_null($usuario)) {
                    $idsUsuariosAValidar[] = $usuario;
                }
                if (!is_null($beneficiario)) {
                    $idsBeneficiariosAValidar[] = $beneficiario;
                }

                $itemsNormalizados[] = ['id_usuario' => $usuario, 'id_beneficiario' => $beneficiario];
            }

            if (!empty($idsUsuariosAValidar)) {
                $idsUsuariosAValidar = array_values(array_unique(array_filter($idsUsuariosAValidar, fn($v) => $v > 0)));
                $usuariosExistentes = Usuario::whereIn('id_usuario', $idsUsuariosAValidar)
                    ->pluck('id_usuario')->all();
                if ($strict && count($usuariosExistentes) !== count($idsUsuariosAValidar)) {
                    throw new Exception('Uno o más usuarios no existen.');
                }
                if (!$strict) {
                    $itemsNormalizados = array_values(array_filter($itemsNormalizados, function ($it) use ($usuariosExistentes) {
                        return is_null($it['id_usuario']) || in_array($it['id_usuario'], $usuariosExistentes);
                    }));
                }
            }
            if (!empty($idsBeneficiariosAValidar)) {
                $idsBeneficiariosAValidar = array_values(array_unique(array_filter($idsBeneficiariosAValidar, fn($v) => $v > 0)));
                $beneficiariosExistentes = \App\Models\Beneficiario::where('id_usuario', $reserva->id_usuario)
                    ->whereIn('id', $idsBeneficiariosAValidar)
                    ->pluck('id')->all();
                if ($strict && count($beneficiariosExistentes) !== count($idsBeneficiariosAValidar)) {
                    throw new Exception('Uno o más beneficiarios no existen.');
                }
                if (!$strict) {
                    $itemsNormalizados = array_values(array_filter($itemsNormalizados, function ($it) use ($beneficiariosExistentes) {
                        return is_null($it['id_beneficiario']) || in_array($it['id_beneficiario'], $beneficiariosExistentes);
                    }));
                }
            }

            $existentesUsuarios = $reserva->jugadores->pluck('id_usuario')->filter()->toArray();
            $existentesBeneficiarios = $reserva->jugadores->pluck('id_beneficiario')->filter()->toArray();

            foreach ($itemsNormalizados as $norm) {
                $idUsuario = $norm['id_usuario'];
                $idBeneficiario = $norm['id_beneficiario'];

                if ($idUsuario && ($idUsuario == $reserva->id_usuario || in_array($idUsuario, $existentesUsuarios))) {
                    continue;
                }
                if ($idBeneficiario && in_array($idBeneficiario, $existentesBeneficiarios)) {
                    continue;
                }

                $jugadoresData[] = [
                    'id_reserva' => $idReserva,
                    'id_usuario' => $idUsuario,
                    'id_beneficiario' => $idBeneficiario,
                    'creado_en' => now(),
                    'actualizado_en' => now(),
                ];
            }

            if (empty($jugadoresData)) {
                if ($strict) {
                    throw new Exception('Los jugadores ya están agregados a la reserva o no son válidos.');
                }
                return $this->getMiReserva($idReserva);
            }

            DB::table('reservas_jugadores')->insert($jugadoresData);

            return $this->getMiReserva($idReserva);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function cancelarReserva(int $idReserva, int $usuarioAuthId): array
    {
        DB::beginTransaction();
        try {
            $reserva = Reservas::with(['pago', 'usuarioReserva', 'movimientos'])
                ->lockForUpdate()
                ->find($idReserva);

            if (!$reserva) {
                throw new Exception('Reserva no encontrada');
            }

            if (method_exists($reserva, 'trashed') && $reserva->trashed()) {
                throw new Exception('La reserva ya fue cancelada');
            }

            $usuarioAuth = Usuario::find($usuarioAuthId);
            $puedeCancelarTerceros = $usuarioAuth && method_exists($usuarioAuth, 'tienePermiso')
                ? $usuarioAuth->tienePermiso('cancelar_reservas')
                : false;
            $esPropietario = $reserva->id_usuario === $usuarioAuthId;

            if (!$esPropietario && !$puedeCancelarTerceros) {
                throw new Exception('No tienes permisos para cancelar esta reserva');
            }

            if (!$reserva->puedeSerCancelada()) {
                throw new Exception('La reserva no puede ser cancelada según la política de tiempos');
            }

            if (in_array($reserva->estado, ['cancelada', 'rechazada'])) {
                throw new Exception('La reserva ya está cancelada/rechazada');
            }

            $tienePagoOk = $reserva->pago && strtoupper($reserva->pago->estado) === 'OK';
            $valorMovimiento = (float)($reserva->pago->valor ?? $reserva->precio_total ?? 0);

            $reserva->estado = 'cancelada';
            $reserva->cancelado_por = $usuarioAuthId;
            $reserva->save();


            $tieneMovimientoAsociados = count($reserva->movimientos) > 0;

            $movimiento = null;
            if (($tienePagoOk || $tieneMovimientoAsociados) && $valorMovimiento > 0) {
                $movimiento = Movimientos::create([
                    'id_usuario' => $reserva->id_usuario,
                    'id_reserva' => $reserva->id,
                    'id_movimiento_principal' => null,
                    'fecha' => now(),
                    'valor' => $valorMovimiento,
                    'tipo' => Movimientos::TIPO_INGRESO,
                    'creado_por' => $usuarioAuthId,
                ]);
            }

            $reserva->delete();
            DB::commit();

            if (!$reserva->codigo_evento) {
                Log::warning("No se envía cancelación de reserva con id {$reserva->id} porque no tiene código de evento");
            } else if (!$reserva->usuarioReserva->ldap_uid) {
                Log::warning("No se envía cancelación de reserva con id {$reserva->id} porque el suaurio no tiene banner");
            } else {
                $this->cron_service->enviarCancelacionReserva($reserva->id, $reserva->usuarioReserva->ldap_uid, $reserva->codigo_evento);
            }


            return [
                'exito' => true,
                'mensaje' => 'Reserva cancelada correctamente',
                'creo_movimiento' => $movimiento !== null,
                'movimiento' => $movimiento,
                'cancelado_por' => $usuarioAuthId,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error cancelando reserva', [
                'reserva_id' => $idReserva,
                'usuario_auth' => $usuarioAuthId,
                'error' => $e->getMessage(),
            ]);
            return [
                'exito' => false,
                'mensaje' => $e->getMessage(),
            ];
        }
    }

    private function esReservaPasada($reserva)
    {
        if (!$reserva->fecha || !$reserva->hora_inicio) {
            return false;
        }

        $fechaHoraReserva = Carbon::parse(
            $reserva->fecha->format('Y-m-d') . ' ' . $reserva->hora_inicio->format('H:i:s')
        );
        return $fechaHoraReserva->isPast();
    }

    private function obtenerPorcentajeDescuento($espacioId, $usuarioId = null)
    {
        try {
            $usuario = $usuarioId ? Usuario::find($usuarioId) : Auth::user();

            if (!$usuario || !$usuario->tipos_usuario || empty($usuario->tipos_usuario)) {
                return 0;
            }

            $configTipoUsuario = EspacioTipoUsuarioConfig::where('id_espacio', $espacioId)
                ->whereIn('tipo_usuario', $usuario->tipos_usuario)
                ->whereNull('eliminado_en')
                ->orderBy('porcentaje_descuento', 'desc')
                ->first();

            return $configTipoUsuario ? ($configTipoUsuario->porcentaje_descuento ?? 0) : 0;
        } catch (Exception $e) {
            Log::error('Error al obtener porcentaje de descuento', [
                'error' => $e->getMessage(),
                'espacio_id' => $espacioId,
                'usuario_id' => $usuarioId,
            ]);
            return 0;
        }
    }

    private function obtenerSaldoFavorUsuario(int $usuarioId): float
    {
        try {
            $ingresos = (float) Movimientos::where('id_usuario', $usuarioId)
                ->where('tipo', Movimientos::TIPO_INGRESO)
                ->sum('valor');
            $egresos = (float) Movimientos::where('id_usuario', $usuarioId)
                ->where('tipo', Movimientos::TIPO_EGRESO)
                ->sum('valor');
            return $ingresos - $egresos;
        } catch (\Throwable $th) {
            Log::warning('Error calculando saldo a favor', [
                'usuario_id' => $usuarioId,
                'error' => $th->getMessage(),
            ]);
            return 0.0;
        }
    }

    private function puedePagarConSaldoReserva($reserva, ?float $saldoFavor = null): bool
    {
        try {
            if (!$reserva) {
                return false;
            }
            $usuarioId = $reserva->id_usuario ?? null;
            if (!$usuarioId) {
                return false;
            }

            // Si está cubierta por mensualidad, no requiere pago del espacio con saldo
            try {
                $fecha = $reserva->fecha instanceof Carbon ? $reserva->fecha : Carbon::parse((string)$reserva->fecha);
                $espacio = $reserva->relationLoaded('espacio') ? $reserva->espacio : Espacio::find($reserva->id_espacio);
                $cobertura = $this->calcularCoberturaMensualidad($espacio, (int)$usuarioId, $fecha);
                if ($cobertura['pago_mensual'] && $cobertura['tiene_mensualidad_activa']) {
                    return false;
                }
            } catch (\Throwable $th) {
                // continuar flujo normal
            }

            // Calcular el valor a pagar según franja/configuración
            $horaInicio = $reserva->hora_inicio instanceof Carbon
                ? $reserva->hora_inicio
                : Carbon::createFromFormat('H:i:s', (string)$reserva->hora_inicio);
            $horaFin = $reserva->hora_fin instanceof Carbon
                ? $reserva->hora_fin
                : Carbon::createFromFormat('H:i:s', (string)$reserva->hora_fin);

            $valores = $this->obtenerValorReserva(null, $reserva->id_configuracion, $horaInicio, $horaFin);
            $valorAPagar = $valores ? (float)($valores['valor_descuento'] ?? 0) : 0.0;
            if ($valorAPagar <= 0) {
                return false;
            }

            // Obtener saldo si no se pasa
            if ($saldoFavor === null) {
                $saldoFavor = $this->obtenerSaldoFavorUsuario((int)$usuarioId);
            }

            return $saldoFavor > 0 && $valorAPagar <= $saldoFavor;
        } catch (\Throwable $th) {
            Log::warning('Error validando pago con saldo', [
                'reserva_id' => $reserva->id ?? null,
                'error' => $th->getMessage(),
            ]);
            return false;
        }
    }

    private function getMensualidadActivaParaFecha(int $usuarioId, Carbon $fecha, int $espacioId): ?Mensualidades
    {
        $fecha = $fecha->copy()->startOfDay();

        try {
            return Mensualidades::where('id_usuario', $usuarioId)
                ->where('estado', 'activa')
                ->where('id_espacio', $espacioId)
                ->whereDate('fecha_inicio', '<=', $fecha->toDateString())
                ->whereDate('fecha_fin', '>=', $fecha->toDateString())
                ->whereNull('eliminado_en')
                ->orderByDesc('fecha_fin')
                ->first();
        } catch (\Throwable $th) {
            Log::warning('Error consultando mensualidad activa', [
                'usuario_id' => $usuarioId,
                'fecha' => $fecha->toDateString(),
                'error' => $th->getMessage(),
            ]);
            return null;
        }
    }

    private function calcularCoberturaMensualidad($espacio, int $usuarioId, Carbon $fecha): array
    {
        $pagoMensual = (bool) ($espacio->pago_mensual ?? false);
        try {
            $usuario = Usuario::find($usuarioId);
            $esEstudiante = $usuario && is_array($usuario->tipos_usuario)
                ? in_array('estudiante', $usuario->tipos_usuario)
                : false;
            if ($pagoMensual && $esEstudiante) {
                return [
                    'pago_mensual' => true,
                    'tiene_mensualidad_activa' => true,
                    'mensualidad' => null,
                ];
            }
            if ($pagoMensual) {
                try {
                    $valorMensual = (float) $this->calcularValorMensualidadParaUsuario($espacio, $usuario);
                    if ($valorMensual === 0.0) {
                        return [
                            'pago_mensual' => true,
                            'tiene_mensualidad_activa' => true,
                            'mensualidad' => null,
                        ];
                    }
                } catch (\Throwable $e) {
                    // noop
                }
            }
        } catch (\Throwable $th) {
            // continuar flujo normal
        }

        $mensualidad = $pagoMensual ? $this->getMensualidadActivaParaFecha($usuarioId, $fecha, $espacio->id) : null;
        return [
            'pago_mensual' => $pagoMensual,
            'tiene_mensualidad_activa' => $mensualidad !== null,
            'mensualidad' => $mensualidad ? [
                'id' => $mensualidad->id,
                'fecha_inicio' => optional($mensualidad->fecha_inicio)->format('Y-m-d'),
                'fecha_fin' => optional($mensualidad->fecha_fin)->format('Y-m-d'),
                'estado' => $mensualidad->estado,
            ] : null,
        ];
    }

    public function aprobar_reserva(int $reservaId): bool
    {
        try {
            $reserva = Reservas::find($reservaId);
            if (!$reserva || $reserva->estado !== 'pendienteap') {
                return false;
            }
            $reserva->estado = 'completada';
            $reserva->aprobado_por = Auth::id();
            $reserva->aprobado_en = Carbon::now();
            $reserva->save();
            return true;
        } catch (\Throwable $th) {
            Log::warning('Error aprobando reserva', [
                'reserva_id' => $reservaId,
                'error' => $th->getMessage(),
            ]);
            return false;
        }
    }
}
