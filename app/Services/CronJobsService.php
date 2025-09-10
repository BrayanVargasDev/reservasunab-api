<?php

namespace App\Services;

use App\Mail\ReporteFallosReservaMensualidades;
use Illuminate\Support\Facades\Log;
use App\Models\Reservas;
use App\Models\Espacio;
use App\Models\EspacioNovedad;
use App\Models\Mensualidades;
use App\Models\Pago;
use App\Models\PagoConsulta;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Servicio central para la lógica de los cron jobs.
 * Sustituye los Logs por lógica real cuando se defina cada proceso.
 */
class CronJobsService
{

    const TIME_OUT = 30;

    private $unab_host = null;
    private $unab_endpoint = null;
    private $usuario_unab = null;
    private $password_unab = null;
    private $tarea = null;

    public function __construct()
    {
        $this->unab_host = config('app.unab_host');
        $this->unab_endpoint = config('app.unab_endpoint');
        $this->usuario_unab = config('app.unab_usuario');
        $this->password_unab = config('app.unab_password');
    }

    /**
     * Procesa reservas que requieren pago y que llevan >= 30 minutos sin un pago exitoso (OK).
     * Criterios:
     *  - Reserva NO pasada (fecha futura o fecha hoy y hora_inicio >= ahora)
     *  - Tiene pago asociado cuyo estado está en pendiente / expirado (no OK)
     *  - Fue creada hace 30+ minutos y todavía no hay pago en estado OK
     *  - Se elimina vía soft-delete y se marca estado cancelada para trazabilidad
     * Se ejecuta cada minuto desde el scheduler.
     */
    public function procesarReservasSinPago(): void
    {
        $inicio = microtime(true);
        $limite = Carbon::now()->subMinutes(30);

        Log::channel('cronjobs')->info('[CRON] Inicio procesarReservasSinPago', [
            'limite_creado_en' => $limite->toDateTimeString(),
        ]);

        $estadosPendientes = [
            'PENDING',
            'PENDIENTE',
            'EXPIRADO',
            'EXPIRED'
        ];

        $totalEvaluadas = 0;
        $totalCanceladas = 0;

        Reservas::query()
            ->with(['pago'])
            ->whereNull('eliminado_en')
            ->where('creado_en', '<=', $limite)
            ->where(function ($q) {
                $q->whereDate('fecha', '>', Carbon::today())
                    ->orWhere(function ($q2) {
                        $q2->whereDate('fecha', Carbon::today())
                            ->whereTime('hora_inicio', '>=', Carbon::now()->format('H:i:s'));
                    });
            })
            ->whereHas('pago', function ($q) use ($estadosPendientes) {
                $q->whereNull('pagos.eliminado_en')
                    ->whereRaw('UPPER(pagos.estado) <> ?', ['OK'])
                    ->where(function ($q2) use ($estadosPendientes) {
                        $upperList = array_map(fn($v) => strtoupper($v), $estadosPendientes);
                        $placeholders = implode(',', array_fill(0, count($upperList), '?'));
                        $q2->whereRaw('UPPER(pagos.estado) IN (' . $placeholders . ')', $upperList);
                    });
            })
            ->orderBy('id')
            ->chunkById(200, function ($reservas) use (&$totalEvaluadas, &$totalCanceladas) {
                foreach ($reservas as $reserva) {
                    $totalEvaluadas++;
                    try {
                        if ($reserva->pago && strtoupper($reserva->pago->estado) === 'OK') {
                            continue;
                        }
                        DB::transaction(function () use ($reserva, &$totalCanceladas) {
                            $reserva->estado = 'cancelada';
                            $reserva->save();
                            $reserva->delete();
                            $totalCanceladas++;
                            Log::channel('cronjobs')->info('[CRON] Reserva cancelada por falta de pago', [
                                'reserva_id' => $reserva->id,
                                'pago_estado' => $reserva->pago->estado ?? null,
                                'creado_en' => $reserva->creado_en?->toDateTimeString(),
                            ]);
                        });
                    } catch (\Throwable $e) {
                        Log::channel('cronjobs')->error('[CRON] Error cancelando reserva sin pago', [
                            'reserva_id' => $reserva->id ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $duracion = round((microtime(true) - $inicio) * 1000, 1);
        Log::channel('cronjobs')->info('[CRON] Fin procesarReservasSinPago', [
            'evaluadas' => $totalEvaluadas,
            'canceladas' => $totalCanceladas,
            'ms' => $duracion,
        ]);
    }

    /**
     * Procesa novedades de espacios consultando el sistema UNAB.
     * Criterios:
     *  - Consulta todos los espacios que tengan códigos configurados
     *  - Consulta novedades para los próximos 30 días
     *  - Inserta novedades que no existan (mismo espacio, fecha, hora_inicio, hora_fin)
     *  - No inserta novedades que estén dentro de la ventana de apertura (configuración del espacio)
     * Se ejecuta diariamente a medianoche desde el scheduler.
     */
    public function procesarNovedades(): void
    {
        $inicio = microtime(true);
        Log::channel('cronjobs')->info('[CRON] Inicio procesarNovedades (tarea 2)');

        $hoy = Carbon::today();
        $totalEspacios = 0;
        $totalSinCodigos = 0;
        $totalConsultados = 0;
        $totalNovedadesInsertadas = 0;
        $totalNovedadesSaltadasPorApertura = 0;
        $totalNovedadesDuplicadas = 0;
        $errores = 0;

        $fechaInicioConsulta = $hoy->copy();
        $fechaFinConsulta = $hoy->copy()->addDays(30);

        $urlBase = 'https://' . rtrim($this->unab_host, '/') . '/' . ltrim($this->unab_endpoint, '/');

        Espacio::query()
            ->with(['edificio:id,codigo', 'configuraciones' => function ($q) {
                $q->whereNull('eliminado_en');
            }])
            ->whereNull('eliminado_en')
            ->orderBy('id')
            ->chunkById(30, function ($espacios) use (&$totalEspacios, &$totalSinCodigos, &$totalConsultados, &$totalNovedadesInsertadas, &$totalNovedadesSaltadasPorApertura, &$totalNovedadesDuplicadas, &$errores, $fechaInicioConsulta, $fechaFinConsulta, $urlBase, $hoy) {
                foreach ($espacios as $espacio) {
                    $totalEspacios++;
                    try {
                        $codigoEdificio = $espacio->edificio?->codigo;
                        $codigoEspacio = $espacio->codigo;
                        if (!$codigoEdificio || !$codigoEspacio) {
                            $totalSinCodigos++;
                            Log::channel('cronjobs')->warning('[CRON] Espacio sin códigos requeridos para consulta novedades', [
                                'espacio_id' => $espacio->id,
                                'codigo_edificio' => $codigoEdificio,
                                'codigo_espacio' => $codigoEspacio,
                            ]);
                            continue;
                        }

                        $datosPayload = [
                            'tarea' => '2',
                            'edificio' => $codigoEdificio,
                            'espacio' => $codigoEspacio,
                            'fecha_inicio' => $fechaInicioConsulta->format('d/m/Y'),
                            'fecha_fin' => $fechaFinConsulta->format('d/m/Y'),
                        ];

                        $totalConsultados++;
                        $response = null;
                        try {
                            $response = Http::timeout(30)
                                ->connectTimeout(5)
                                ->withBasicAuth($this->usuario_unab, $this->password_unab)
                                ->withHeaders([
                                    'Content-Type' => 'application/json',
                                    'Accept' => 'application/json',
                                    'Connection' => 'keep-alive'
                                ])
                                ->post($urlBase, $datosPayload);
                        } catch (\Throwable $httpEx) {
                            $errores++;
                            Log::channel('cronjobs')->error('[CRON] Error HTTP consultando novedades', [
                                'espacio_id' => $espacio->id,
                                'error' => $httpEx->getMessage(),
                            ]);
                            continue;
                        }

                        if (!$response->ok()) {
                            $errores++;
                            Log::channel('cronjobs')->error('[CRON] Respuesta HTTP no OK novedades', [
                                'espacio_id' => $espacio->id,
                                'status' => $response->status(),
                                'body' => $response->body(),
                            ]);
                            continue;
                        }

                        $json = $response->json();

                        if (!is_array($json)) {
                            $errores++;
                            Log::channel('cronjobs')->error('[CRON] Respuesta inesperada (no JSON array)', [
                                'espacio_id' => $espacio->id,
                                'body' => $response->body(),
                            ]);
                            continue;
                        }

                        $resp = $this->normalizarRespuestaServicio($json);
                        if (!$resp) {
                            $errores++;
                            Log::channel('cronjobs')->error('[CRON] Respuesta del servicio inválida', [
                                'espacio_id' => $espacio->id,
                                'body' => $response->body(),
                            ]);
                            continue;
                        }

                        if (strtolower((string)$resp['estado']) !== 'success') {
                            $errores++;
                            Log::channel('cronjobs')->error('[CRON] Error del servicio en novedades', [
                                'espacio_id' => $espacio->id,
                                'mensaje' => $resp['mensaje'] ?? null,
                            ]);
                            continue;
                        }

                        $datos = is_array($resp['datos'] ?? null) ? $resp['datos'] : [];
                        if (empty($datos)) {
                            Log::channel('cronjobs')->info('[CRON] Sin datos de novedades para espacio', [
                                'espacio_id' => $espacio->id,
                            ]);
                            continue;
                        }

                        $maxDiasPrevios = 0;
                        try {
                            $maxDiasPrevios = (int) ($espacio->configuraciones->max('dias_previos_apertura') ?? 0);
                        } catch (\Throwable $e) {
                            Log::channel('cronjobs')->warning('[CRON] Error obteniendo dias_previos_apertura', [
                                'espacio_id' => $espacio->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                        $limiteNoBloqueo = $hoy->copy()->addDays($maxDiasPrevios);

                        foreach ($datos as $item) {
                            try {
                                $fechaInicioRaw = $item['fecha_inicio'] ?? null;
                                $fechaFinRaw = $item['fecha_fin'] ?? null;
                                $horaInicioRaw = $item['hora_inicio'] ?? null;
                                $horaFinRaw = $item['hora_fin'] ?? null;
                                if (!$fechaInicioRaw || !$fechaFinRaw || !$horaInicioRaw || !$horaFinRaw) {
                                    continue;
                                }

                                $fechaInicio = Carbon::createFromFormat('d/m/Y', $fechaInicioRaw);
                                $fechaFin = Carbon::createFromFormat('d/m/Y', $fechaFinRaw);
                                if ($fechaInicio->greaterThan($fechaFin)) {
                                    continue;
                                }

                                $horaInicio = $this->normalizarHora4($horaInicioRaw);
                                $horaFin = $this->normalizarHora4($horaFinRaw);
                                if (!$horaInicio || !$horaFin) {
                                    continue;
                                }

                                $diasFlags = [
                                    1 => $item['lunes'] ?? 0,
                                    2 => $item['martes'] ?? 0,
                                    3 => $item['miercoles'] ?? 0,
                                    4 => $item['jueves'] ?? 0,
                                    5 => $item['viernes'] ?? 0,
                                    6 => $item['sabado'] ?? 0,
                                    7 => $item['domingo'] ?? 0,
                                ];

                                $cursor = $fechaInicio->copy();
                                while ($cursor->lessThanOrEqualTo($fechaFin)) {
                                    $dia = $cursor->dayOfWeekIso; // 1..7
                                    if (($diasFlags[$dia] ?? 0) == 1) {
                                        if ($cursor->lessThanOrEqualTo($limiteNoBloqueo)) {
                                            $totalNovedadesSaltadasPorApertura++;
                                        } else {
                                            $existe = EspacioNovedad::query()
                                                ->where('id_espacio', $espacio->id)
                                                ->whereDate('fecha', $cursor->toDateString())
                                                ->whereTime('hora_inicio', $horaInicio)
                                                ->whereTime('hora_fin', '<=', $horaFin)
                                                ->exists();
                                            if ($existe) {
                                                $totalNovedadesDuplicadas++;
                                            } else {
                                                EspacioNovedad::create([
                                                    'id_espacio' => $espacio->id,
                                                    'fecha' => $cursor->toDateString(),
                                                    'fecha_fin' => $cursor->toDateString(),
                                                    'hora_inicio' => $cursor->toDateString() . ' ' . $horaInicio,
                                                    'hora_fin' => $cursor->toDateString() . ' ' . $horaFin,
                                                    'descripcion' => 'PROGRAMACIÓN ACADÉMICA',
                                                ]);
                                                $totalNovedadesInsertadas++;
                                            }
                                        }
                                    }
                                    $cursor->addDay();
                                }
                            } catch (\Throwable $inner) {
                                $errores++;
                                Log::channel('cronjobs')->error('[CRON] Error procesando item novedad', [
                                    'espacio_id' => $espacio->id,
                                    'item' => $item,
                                    'error' => $inner->getMessage(),
                                ]);
                            }
                        }
                    } catch (\Throwable $e) {
                        $errores++;
                        Log::channel('cronjobs')->error('[CRON] Error general procesando espacio en job dos', [
                            'espacio_id' => $espacio->id ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $duracion = round((microtime(true) - $inicio) * 1000, 1);
        Log::channel('cronjobs')->info('[CRON] Fin procesarNovedades', [
            'espacios_totales' => $totalEspacios,
            'espacios_sin_codigos' => $totalSinCodigos,
            'consultados' => $totalConsultados,
            'novedades_insertadas' => $totalNovedadesInsertadas,
            'novedades_saltadas_apertura' => $totalNovedadesSaltadasPorApertura,
            'novedades_duplicadas' => $totalNovedadesDuplicadas,
            'errores' => $errores,
            'ms' => $duracion,
        ]);
    }

    public function procesarReporteReservasMensualidades(): void
    {
        $inicio = microtime(true);
        Log::channel('cronjobs')->info('[CRON] Inicio procesarReporteReservasMensualidades (tarea 3)');

        $endpoint = 'https://' . rtrim($this->unab_host, '/') . '/' . ltrim($this->unab_endpoint, '/');
        $fechaHoy = now();

        $maxFallos = 5;
        $reportadasOk = 0;
        $fallidas = 0;
        $omitidasPorFallos = 0;
        $omitidasGimnasio = 0;

        $reservasFallosMax = [];
        $mensualidadesFallosMax = [];

        $reservas = Reservas::query()
            ->with([
                'pago',
                'detalles.elemento',
                'espacio.edificio',
                'espacio.categoria',
                'usuarioReserva.persona.tipoDocumento',
                'usuarioReserva.persona.ciudadExpedicion',
                'usuarioReserva.persona.ciudadResidencia',
                'usuarioReserva.persona.personaFacturacion.tipoDocumento',
                'usuarioReserva.persona.personaFacturacion.ciudadExpedicion',
                'usuarioReserva.persona.personaFacturacion.ciudadExpedicion.departamento',
                'usuarioReserva.persona.personaFacturacion.ciudadResidencia',
                'usuarioReserva.persona.personaFacturacion.ciudadResidencia.departamento',
            ])
            ->where('reportado', false)
            ->where(function ($q) use ($maxFallos) {
                $q->whereNull('fallos_reporte')->orWhere('fallos_reporte', '<', $maxFallos);
            })
            ->whereNull('eliminado_en')
            ->limit(300)
            ->get();

        $mensualidades = Mensualidades::query()
            ->with([
                'usuario.persona.tipoDocumento',
                'usuario.persona.ciudadExpedicion',
                'usuario.persona.ciudadResidencia',
                'usuario.persona.personaFacturacion.tipoDocumento',
                'usuario.persona.personaFacturacion.ciudadExpedicion',
                'usuario.persona.personaFacturacion.ciudadExpedicion.departamento',
                'usuario.persona.personaFacturacion.ciudadResidencia',
                'usuario.persona.personaFacturacion.ciudadResidencia.departamento',
                'espacio.edificio',
            ])
            ->where('reportado', false)
            ->where(function ($q) use ($maxFallos) {
                $q->whereNull('fallos_reporte')->orWhere('fallos_reporte', '<', $maxFallos);
            })
            ->limit(300)
            ->get();

        $coleccionProcesar = [];

        foreach ($reservas as $reserva) {
            try {
                $espacio = $reserva->espacio;
                if (!$espacio || !$espacio->edificio?->codigo || !$espacio->codigo) {
                    $this->marcarFallo($reserva, 'Espacio sin códigos de edificio o id de espacio');
                    $fallidas++;
                    continue;
                }

                $esGimnasio = false;
                try {
                    $nombreEspacio = strtoupper((string)($espacio->nombre ?? ''));
                    $nombreCategoria = strtoupper((string)($espacio->categoria->nombre ?? ''));
                    if (($espacio->pago_mensual ?? false) === true || str_contains($nombreEspacio, 'GIMNASIO') || str_contains($nombreCategoria, 'GIMNASIO')) {
                        $esGimnasio = true;
                    }
                } catch (\Throwable $e) {
                    // Silencioso
                }

                if ($esGimnasio) {
                    $omitidasGimnasio++;
                    Log::channel('cronjobs')->info('[CRON] Reserva omitida por ser de gimnasio', [
                        'reserva_id' => $reserva->id,
                        'espacio_id' => $espacio->id,
                    ]);
                    continue;
                }

                $usuario = $reserva->usuarioReserva;
                if (!$usuario) {
                    $this->marcarFallo($reserva, 'Reserva sin usuario asociado');
                    $fallidas++;
                    continue;
                }

                $detalles = $reserva->detalles;
                $tieneElementos = $detalles && $detalles->count() > 0;
                $elementoNombres = $tieneElementos ? $detalles->pluck('elemento.nombre')->filter()->implode(',') : null;

                $pago = $reserva->pago; // puede ser null

                $medioPago = null;
                if ($pago) {
                    $consultaPago = PagoConsulta::where('codigo', $pago->codigo)->first();

                    if (!$consultaPago && strtoupper($pago->estado) !== 'OK') {
                        $this->marcarFallo($reserva, 'Pago asociado sin registro en PagoConsulta');
                        $fallidas++;
                        continue;
                    }

                    $medioPago = $consultaPago ? ($consultaPago->medio_pago === 'PSE' ? 0 : 1) : null;
                }

                $hayPagoOk = $pago && strtoupper($pago->estado) === 'OK';

                $tipoReserva = $tieneElementos ? 'TODO' : 'ESPACIO';

                // Horas
                $horaInicio = $reserva->hora_inicio instanceof Carbon ? $reserva->hora_inicio->format('Hi') : (Carbon::parse($reserva->hora_inicio)->format('Hi'));
                $horaFinCarbon = $reserva->hora_fin instanceof Carbon ? $reserva->hora_fin : Carbon::parse($reserva->hora_fin);
                // Ajustar a HH:59 debe ser hora fin real menos 1 minuto
                $horaFinCarbon->subMinute();
                $horaFin = $horaFinCarbon->format('Hi');

                // Rol mapping
                $rolDb = strtolower((string)($usuario->tipos_usuario[0] ?? ''));
                $rol = $rolDb === 'egresado' ? 'GRADUADO' : strtoupper($rolDb ?: 'ESTUDIANTE');

                // Calcular totales y flags de día
                [$totalPagar, $precioEspacio, $precioElementos] = $this->calcularTotalReserva($reserva, $usuario);
                $flags = $this->flagsDiaSemanaParaFecha(Carbon::parse($reserva->fecha));

                $payload = [
                    'tarea' => '3',
                    'numberDoc' => "522",
                    'fechaTransac' => $fechaHoy->format('d/m/Y'),
                    'canalVenta' => 'RESERVA_EN_LINEA',
                    'formaPago' => 'PAGO_ONLINE',
                    'descuentoTotal' => 0.00,
                    'totalPagar' => round((float)$totalPagar, 2),
                    'Ecollect' => $hayPagoOk ? [
                        'ticketId' => $pago->ticket_id,
                        'paymentId' => $pago->codigo,
                        'medioPagoEcollect' => (string)($medioPago ?? '0'),
                        'svrcode' => 21000,
                    ] : [
                        'ticketId' => null,
                        'paymentId' => null,
                        'medioPagoEcollect' => null,
                        'svrcode' => 21000,
                    ],
                    'DatosReserva' => $this->construirDatosReserva($usuario, $rol),
                    'Reserva' => [[
                        'codReserva' => (string)$reserva->codigo,
                        'tipoReserva' => $tipoReserva,
                        'edificio' => $espacio->edificio->codigo,
                        'espacio' => $espacio->codigo,
                        'elemento' => $elementoNombres ?: null,
                        'lunes' => $flags['lunes'],
                        'martes' => $flags['martes'],
                        'miercoles' => $flags['miercoles'],
                        'jueves' => $flags['jueves'],
                        'viernes' => $flags['viernes'],
                        'sabado' => $flags['sabado'],
                        'domingo' => $flags['domingo'],
                        'fechaInicioReserva' => Carbon::parse($reserva->fecha)->format('d/m/Y'),
                        'fechaFinReserva' => Carbon::parse($reserva->fecha)->format('d/m/Y'),
                        'horaInicio' => $horaInicio,
                        'horaFin' => $horaFin,
                        'descuentoReserva' => 0.00,
                        'precioTotal' => round((float)$totalPagar, 2),
                    ]],
                ];

                $coleccionProcesar[] = ['tipo' => 'reserva', 'model' => $reserva, 'payload' => $payload];
            } catch (\Throwable $e) {
                $this->marcarFallo($reserva, 'Error construyendo payload: ' . $e->getMessage());
                $fallidas++;
            }
        }

        foreach ($mensualidades as $mensualidad) {
            try {
                $espacio = $mensualidad->espacio;
                if (!$espacio || !$espacio->edificio?->codigo || !$espacio->codigo) {
                    $this->marcarFallo($mensualidad, 'Mensualidad sin códigos de edificio o espacio');
                    $fallidas++;
                    continue;
                }
                $usuario = $mensualidad->usuario;
                if (!$usuario) {
                    $this->marcarFallo($mensualidad, 'Mensualidad sin usuario');
                    $fallidas++;
                    continue;
                }

                // Buscar pago asociado via PagosDetalles
                $pago = Pago::query()
                    ->whereHas('detalles', function ($q) use ($mensualidad) {
                        $q->where('tipo_concepto', 'mensualidad')->where('id_concepto', $mensualidad->id);
                    })
                    ->first();
                $hayPagoOk = $pago && strtoupper($pago->estado) === 'OK';
                if (!$hayPagoOk) {
                    // Omitir mensualidades sin pago OK (no cuenta como fallo)
                    continue;
                }

                $rolDb = strtolower((string)($usuario->tipos_usuario[0] ?? ''));
                $rol = $rolDb === 'egresado' ? 'GRADUADO' : strtoupper($rolDb ?: 'ESTUDIANTE');

                $payload = [
                    'tarea' => 3,
                    'numberDoc' => '522',
                    'fechaTransac' => $fechaHoy->format('d/m/Y'),
                    'canalVenta' => 'RESERVA_EN_LINEA',
                    'formaPago' => 'PAGO_ONLINE',
                    'descuentoTotal' => 0.00,
                    'totalPagar' => round((float)$mensualidad->valor, 2),
                    'Ecollect' => [
                        'ticketId' => $pago->ticket_id,
                        'paymentId' => $pago->codigo,
                        'medioPagoEcollect' => (string)(optional(PagoConsulta::where('codigo', $pago->codigo)->first())->medio_pago === 'PSE' ? 0 : 1),
                        'svrcode' => 21000,
                    ],
                    'DatosReserva' => $this->construirDatosReserva($usuario, $rol),
                    'Reserva' => [[
                        'codReserva' => 'MENS-' . $mensualidad->id,
                        'tipoReserva' => 'ESPACIO_GIM',
                        'edificio' => $espacio->edificio->codigo,
                        'espacio' => $espacio->codigo,
                        'elemento' => null,
                        'lunes' => null,
                        'martes' => null,
                        'miercoles' => null,
                        'jueves' => null,
                        'viernes' => null,
                        'sabado' => null,
                        'domingo' => null,
                        'fechaInicioReserva' => Carbon::parse($mensualidad->fecha_inicio)->format('d/m/Y'),
                        'fechaFinReserva' => Carbon::parse($mensualidad->fecha_fin)->format('d/m/Y'),
                        'horaInicio' => '0000',
                        'horaFin' => '2359',
                        'descuentoReserva' => 0.00,
                        'precioTotal' => round((float)$mensualidad->valor, 2),
                    ]],
                ];

                $coleccionProcesar[] = ['tipo' => 'mensualidad', 'model' => $mensualidad, 'payload' => $payload];
            } catch (\Throwable $e) {
                $this->marcarFallo($mensualidad, 'Error construyendo payload: ' . $e->getMessage());
                $fallidas++;
            }
        }

        foreach ($coleccionProcesar as $item) {
            $model = $item['model'];
            $payload = $item['payload'];
            try {
                Log::channel('cronjobs')->info('[CRON] Enviando reporte reserva/mensualidad', [
                    'tipo' => $item['tipo'],
                    'id' => $model->id ?? null,
                    'body' => $payload,
                ]);
                $response = Http::timeout(30)
                    ->connectTimeout(5)
                    ->withBasicAuth($this->usuario_unab, $this->password_unab)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ])->post($endpoint, $payload);

                if (!$response->ok()) {
                    throw new Exception('HTTP status ' . $response->status());
                }
                $json = $response->json();
                Log::channel('cronjobs')->info('[CRON] Respuesta reporte reserva/mensualidad', [
                    'tipo' => $item['tipo'],
                    'id' => $model->id ?? null,
                    'body' => $json,
                ]);
                if (!is_array($json)) {
                    throw new Exception('Respuesta no JSON');
                }
                // Normalizar (el servicio puede responder un array con un único objeto)
                $resp = $this->normalizarRespuestaServicio($json);
                if (!$resp) {
                    throw new Exception('Formato de respuesta inválido');
                }
                if (strtolower((string)$resp['estado']) !== 'success') {
                    // Propagar mensaje del servicio para que quede en logs y en ultimo_error_reporte
                    $msgSrv = $resp['mensaje'] ?? 'sin_mensaje';
                    throw new Exception('Estado respuesta: ' . ($resp['estado'] ?? 'error') . ' - ' . $msgSrv);
                }

                $model->reportado = true;
                $model->ultimo_error_reporte = null;
                $model->save();
                // Loguear casos exitosos solo en reportes
                Log::channel('cronjobs')->info('[CRON] Reporte enviado con éxito', [
                    'tipo' => $item['tipo'],
                    'id' => $model->id ?? null,
                ]);
                $reportadasOk++;
            } catch (\Throwable $e) {
                $this->marcarFallo($model, 'Error reporte: ' . $e->getMessage());
                $fallidas++;
            }
        }

        // Recolectar con fallos maximos
        $reservasFallosMax = Reservas::query()->where('fallos_reporte', '>=', $maxFallos)->where('reportado', false)->limit(100)->get()->toArray();
        $mensualidadesFallosMax = Mensualidades::query()->where('fallos_reporte', '>=', $maxFallos)->where('reportado', false)->limit(100)->get()->toArray();

        // Enviar correo si hay algo
        if (!empty($reservasFallosMax) || !empty($mensualidadesFallosMax)) {
            try {
                \Illuminate\Support\Facades\Mail::to(config('mail.reporte_fallos'))
                    ->send(new ReporteFallosReservaMensualidades($reservasFallosMax, $mensualidadesFallosMax));
            } catch (\Throwable $mailEx) {
                Log::channel('cronjobs')->error('[CRON] Error enviando correo fallos reporte de reservas y mensualidades', ['error' => $mailEx->getMessage()]);
            }
        }

        $duracion = round((microtime(true) - $inicio) * 1000, 1);
        Log::channel('cronjobs')->info('[CRON] Fin procesarReporteReservasMensualidades', [
            'reportadas_ok' => $reportadasOk,
            'fallidas' => $fallidas,
            'omitidas_fallos_max' => $omitidasPorFallos,
            'omitidas_gimnasio' => $omitidasGimnasio,
            'ms' => $duracion,
        ]);
    }

    // Wrapper para mantener compatibilidad con el comando existente
    public function procesarJobTres(): void
    {
        $this->procesarReporteReservasMensualidades();
    }

    private function construirDatosReserva($usuario, string $rol): array
    {
        try {
            $persona = $usuario->persona ?? null;
            // Usar la persona de facturación (padre referenciado) si existe; si no, la persona normal
            $pf = $persona?->personaFacturacion ?: $persona;
            Log::channel('cronjobs')->debug('[CRON] Construyendo DatosReserva', [
                'pf' => $pf
            ]);
            if ($pf) {
                $datosDir = explode(';', $pf->direccion);

                $tipoPersona = strtoupper($pf->tipo_persona ?? 'NATURAL');
                $regimen = (string)($pf->regimen_tributario_id ?? '99');
                $nombres = trim(($pf->primer_nombre ?? '') . ' ' . ($pf->segundo_nombre ?? ''));
                $apellidos = trim(($pf->primer_apellido ?? '') . ' ' . ($pf->segundo_apellido ?? ''));
                $tipoDocumento = $pf->tipoDocumento->codigo ?? 'CC';
                $numDocumento = (string)($pf->numero_documento ?? '');
                $dv = (string)($pf->digito_verificacion ?? '0');
                $ciudadDoc = str_pad((string)($pf->ciudadExpedicion->codigo ?? ''), 4, '0', STR_PAD_LEFT);
                $departamentoDoc = (string)($pf->ciudadExpedicion->departamento->codigo ?? '');
                $direccion = count($datosDir) > 1 ? (string)($datosDir[1] ?? '') : (string)($pf->direccion ?? '');
                $ciudadDir = str_pad((string)($pf->ciudadResidencia->codigo ?? ''), 4, '0', STR_PAD_LEFT);
                $departamentoDir = (string)($pf->ciudadResidencia->departamento->codigo ?? '');
                $email = count($datosDir) > 1 ? (string)($datosDir[0] ?? '') : (string)($usuario->email ?? '');
                $celular = (string)($pf->celular ?? ($usuario->celular ?? ''));

                return [
                    'rol' => $rol,
                    'tipoPersona' => $tipoPersona,
                    'regimenTributario' => $regimen,
                    'nombresCliente' => $nombres,
                    'apellidosCliente' => $apellidos,
                    'tipoDocumento' => $tipoDocumento,
                    'numDocumento' => $numDocumento,
                    'digitoVerificacion' => $dv,
                    'ciudadDocumento' => '34|' . $departamentoDoc . '|' . $ciudadDoc,
                    'direccion' => $direccion,
                    'ciudadDireccion' => '34|' . $departamentoDir . '|' . $ciudadDir,
                    'email' => $email,
                    'celular' => $celular,
                ];
            }
        } catch (\Throwable $e) {
            Log::channel('cronjobs')->warning('[CRON] Error construyendo DatosReserva', ['error' => $e->getMessage()]);
        }

        // Fallback a datos del usuario
        return [
            'rol' => $rol,
            'tipoPersona' => $usuario->persona->tipo_persona,
            'regimenTributario' => (string)$usuario->persona->regimen_tributario_id ?? '99',
            'nombresCliente' => trim((string)($usuario->persona->primer_nombre ?? '') . ' ' . (string)($usuario->persona->segundo_nombre ?? '')),
            'apellidosCliente' => trim((string)($usuario->persona->primer_apellido ?? '') . ' ' . (string)($usuario->persona->segundo_apellido ?? '')),
            'tipoDocumento' => (string)($usuario->persona->tipo_documento->codigo ?? 'CC'),
            'numDocumento' => (string)($usuario->persona->numero_documento ?? ''),
            'digitoVerificacion' => (string)($usuario->persona->digito_verificacion ?? '0'),
            'ciudadDocumento' => '34|' . (string)($usuario->ciudad_documento->departamento->codigo ?? '') . '|' . str_pad((string)($usuario->ciudad_documento->codigo ?? ''), 4, '0', STR_PAD_LEFT),
            'direccion' => (string)($usuario->persona->direccion ?? ''),
            'ciudadDireccion' => '34|' . (string)($usuario->ciudad_direccion->departamento->codigo ?? '') . '|' . str_pad((string)($usuario->ciudad_direccion->codigo ?? ''), 4, '0', STR_PAD_LEFT),
            'email' => (string)($usuario->email ?? ''),
            'celular' => (string)($usuario->persona->celular ?? ''),
        ];
    }

    private function flagsDiaSemanaParaFecha(Carbon $fecha): array
    {
        $dow = $fecha->dayOfWeekIso; // 1..7
        return [
            'lunes' => $dow === 1,
            'martes' => $dow === 2,
            'miercoles' => $dow === 3,
            'jueves' => $dow === 4,
            'viernes' => $dow === 5,
            'sabado' => $dow === 6,
            'domingo' => $dow === 7,
        ];
    }

    private function calcularTotalReserva(Reservas $reserva, $usuario): array
    {
        $precioEspacio = 0.0;
        try {
            $horaIni = $reserva->hora_inicio instanceof Carbon ? $reserva->hora_inicio->format('H:i') : Carbon::parse($reserva->hora_inicio)->format('H:i');
            $horaFin = $reserva->hora_fin instanceof Carbon ? $reserva->hora_fin->format('H:i') : Carbon::parse($reserva->hora_fin)->format('H:i');
            $idConfig = $reserva->id_configuracion ?? optional($reserva->configuracion)->id;
            if ($idConfig) {
                $svc = app(\App\Services\ReservaService::class);
                $valores = $svc->obtenerValorReserva(null, $idConfig, $horaIni, $horaFin);
                $precioEspacio = (float)($valores['valor_descuento'] ?? $valores['valor'] ?? 0);
            }
        } catch (\Throwable $e) {
            Log::channel('cronjobs')->warning('[CRON] Error calculando valor de espacio', ['reserva_id' => $reserva->id, 'error' => $e->getMessage()]);
        }

        $precioElementos = 0.0;
        try {
            $rolDb = strtolower((string)($usuario->tipos_usuario[0] ?? 'externo'));
            foreach ($reserva->detalles as $det) {
                $elem = $det->elemento;
                if (!$elem) continue;
                $cantidad = (int)($det->cantidad ?? 1);
                $precio = 0.0;
                switch ($rolDb) {
                    case 'administrativo':
                        $precio = (float)($elem->valor_administrativo ?? 0);
                        break;
                    case 'egresado':
                        $precio = (float)($elem->valor_egresado ?? 0);
                        break;
                    case 'estudiante':
                        $precio = (float)($elem->valor_estudiante ?? 0);
                        break;
                    default:
                        $precio = (float)($elem->valor_externo ?? 0);
                        break;
                }
                $precioElementos += $precio * $cantidad;
            }
        } catch (\Throwable $e) {
            Log::channel('cronjobs')->warning('[CRON] Error calculando valor de elementos', ['reserva_id' => $reserva->id, 'error' => $e->getMessage()]);
        }

        $total = $precioEspacio + $precioElementos;
        return [$total, $precioEspacio, $precioElementos];
    }

    private function marcarFallo($model, string $mensaje): void
    {
        try {
            $fallos = (int)($model->fallos_reporte ?? 0);
            if ($fallos >= 5) {
                return; // no incrementar
            }
            $model->fallos_reporte = $fallos + 1;
            $model->ultimo_error_reporte = mb_substr($mensaje, 0, 500);
            $model->save();
            Log::channel('cronjobs')->error('[CRON] Fallo reporte reporte de reservas y mensualidades', [
                'id' => $model->id ?? null,
                'tipo' => $model instanceof Mensualidades ? 'mensualidad' : 'reserva',
                'fallos' => $model->fallos_reporte,
                'error' => $mensaje,
            ]);
        } catch (\Throwable $e) {
            Log::channel('cronjobs')->error('[CRON] Error marcando fallo reporte', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Convierte horas recibidas como 'HHmm' o 'Hmm' a 'HH:MM:00'.
     * Si ya viene con ':' se intenta estandarizar a HH:MM:SS.
     */
    private function normalizarHora4(?string $valor): ?string
    {
        if (!$valor) return null;
        $valor = trim($valor);
        if (preg_match('/^\d{3,4}$/', $valor)) {
            // Ej: 700 -> 07:00:00, 1159 -> 11:59:00
            $len = strlen($valor);
            if ($len === 3) {
                $h = substr($valor, 0, 1);
                $m = substr($valor, 1, 2);
            } else {
                $h = substr($valor, 0, 2);
                $m = substr($valor, 2, 2);
            }
            return str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ':00';
        }
        if (strpos($valor, ':') !== false) {
            // Normalizar a HH:MM:SS
            $parts = explode(':', $valor);
            $h = str_pad($parts[0] ?? '00', 2, '0', STR_PAD_LEFT);
            $m = str_pad($parts[1] ?? '00', 2, '0', STR_PAD_LEFT);
            $s = str_pad($parts[2] ?? '00', 2, '0', STR_PAD_LEFT);
            return $h . ':' . $m . ':' . $s;
        }
        return null;
    }

    /**
     * Normaliza la respuesta del servicio externo.
     * El servicio puede responder como un array envolviendo un único objeto con llaves: estado, mensaje, datos.
     * Retorna un array asociativo con esas llaves o null si no cumple.
     */
    private function normalizarRespuestaServicio($json): ?array
    {
        // Si viene como array numerico con un solo elemento, tomar ese
        if (is_array($json) && array_key_exists(0, $json) && is_array($json[0])) {
            $json = $json[0];
        }
        if (!is_array($json)) {
            return null;
        }
        // Debe tener al menos 'estado' y 'mensaje'
        if (!array_key_exists('estado', $json)) {
            return null;
        }
        // Garantizar llaves esperadas
        return [
            'estado' => $json['estado'] ?? null,
            'mensaje' => $json['mensaje'] ?? null,
            'datos' => $json['datos'] ?? null,
        ];
    }
}
