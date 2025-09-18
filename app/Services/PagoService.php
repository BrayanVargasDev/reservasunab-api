<?php

namespace App\Services;

use App\Mail\ConfirmacionReservaEmail;
use App\Models\Espacio;
use App\Models\Pago;
use App\Models\PagoConsulta;
use App\Models\Movimientos;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use App\Models\PagosDetalles;
use App\Models\Mensualidades;

class PagoService
{
    private $reserva_service;
    private $api_key;
    private $url_pagos;
    private $entity_code;
    private $service_code;
    private $url_redirect_base = 'https://reservasunab.wgsoluciones.com/pagos/reservas';
    private $session_token;

    public function __construct(ReservaService $reserva_service)
    {
        $this->reserva_service = $reserva_service;
        $this->api_key = config('app.key_pagos');
        $this->url_pagos = config('app.url_pagos');
        $this->entity_code = config('app.entity_code');
        $this->service_code = config('app.service_code');
        $this->session_token = null;
    }

    public function pagarConSaldo(int $id_reserva): array
    {
        DB::beginTransaction();
        try {
            $reserva = $this->reserva_service->getReservaById($id_reserva);

            if (!$reserva) {
                throw new Exception('Reserva no encontrada');
            }

            $estadosPagada = ['completada', 'pagada'];
            if (in_array(strtolower((string) $reserva->estado), array_map('strtolower', $estadosPagada), true)) {
                throw new Exception('La reserva ya se encuentra pagada o completada');
            }
            // Valor de elementos (si los hay) multiplicando por cantidad
            $reserva->loadMissing(['detalles.elemento', 'usuarioReserva']);

            if ($reserva->precio_total <= 0) {
                throw new Exception('No hay valor por pagar para esta reserva');
            }

            $idUsuarioReserva = (int) ($reserva->usuarioReserva->id_usuario ?? $reserva->id_usuario);
            $saldoFavor = $this->obtenerSaldoFavorUsuario($idUsuarioReserva);

            if ($saldoFavor <= 0 || $saldoFavor < $reserva->precio_total) {
                throw new Exception('Saldo insuficiente para pagar la reserva');
            }

            $ultimoIngreso = Movimientos::where('id_usuario', $idUsuarioReserva)
                ->where('tipo', Movimientos::TIPO_INGRESO)
                ->orderBy('fecha', 'desc')
                ->first();

            $movimiento = Movimientos::create([
                'id_usuario' => $idUsuarioReserva,
                'id_reserva' => $reserva->id,
                'id_movimiento_principal' => $ultimoIngreso->id ?? null,
                'fecha' => Carbon::now(),
                'valor' => $reserva->precio_total,
                'tipo' => Movimientos::TIPO_EGRESO,
                'creado_por' => Auth::id(),
            ]);

            $reserva->estado = 'completada';
            $reserva->save();

            DB::commit();

            // Enviar correo de confirmación al pagar con saldo
            try {
                $reserva->loadMissing(['espacio.sede', 'usuarioReserva:id_usuario,email']);
                Mail::to($reserva->usuarioReserva->email)
                    ->send(new ConfirmacionReservaEmail(
                        $reserva,
                        (float)($reserva->precio_base ?? 0),
                        (float)($reserva->precio_espacio ?? 0),
                    ));
            } catch (Throwable $mailTh) {
                Log::warning('Error enviando correo de confirmación tras pagar con saldo', [
                    'reserva_id' => $reserva->id,
                    'error' => $mailTh->getMessage(),
                ]);
            }

            $saldoRestante = max(0, $saldoFavor - $reserva->precio_total);
            $resumen = $this->reserva_service->getMiReserva($reserva->id);

            return [
                'status' => 'success',
                'message' => 'Reserva pagada con saldo a favor',
                'movimiento_id' => $movimiento->id,
                'saldo_restante' => $saldoRestante,
                'reserva' => $resumen,
            ];
        } catch (Throwable $th) {
            DB::rollBack();
            Log::error('Error al pagar con saldo a favor', [
                'id_reserva' => $id_reserva,
                'usuario_auth' => Auth::id(),
                'error' => $th->getMessage(),
            ]);
            throw new Exception($th->getMessage());
        }
    }

    private function obtenerSaldoFavorUsuario(int $idUsuario): float
    {
        $ingresos = (float) Movimientos::where('id_usuario', $idUsuario)
            ->where('tipo', Movimientos::TIPO_INGRESO)
            ->sum('valor');

        $egresos = (float) Movimientos::where('id_usuario', $idUsuario)
            ->where('tipo', Movimientos::TIPO_EGRESO)
            ->sum('valor');

        return $ingresos - $egresos;
    }

    public function obtenerPagos(int $perPage = 10, string $search = '')
    {
        $search = trim((string) $search);
        $usuario = Auth::user();

        $esAdministrador = $usuario && optional($usuario->rol)->nombre === 'Administrador';

        $query = Pago::withTrashed()->with([
            'reserva' => function ($q) {
                $q->withTrashed()->with([
                    'espacio',
                    'configuracion',
                    'usuarioReserva',
                    'usuarioReserva.persona',
                    'usuarioReserva.persona.tipoDocumento',
                ]);
            },
            'detalles',
            'mensualidad' => function ($q) {
                $q->withTrashed()->with([
                    'usuario',
                    'usuario.persona',
                    'usuario.persona.tipoDocumento',
                    'espacio',
                ]);
            },
            'elementos',
        ])
            ->orderBy('creado_en', 'desc');

        if (!$esAdministrador) {
            $query->where(function ($q) use ($usuario) {
                $q->whereHas('reserva.usuarioReserva', fn($sq) => $sq->where('id_usuario', $usuario->id_usuario))
                    ->orWhereHas('mensualidad.usuario', fn($sq) => $sq->where('id_usuario', $usuario->id_usuario));
            });
        }

        if (!empty($search)) {
            $query->search($search);
        }

        return $query->paginate($perPage);
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

    public function iniciarTransaccionDePago(int $id_reserva)
    {
        if (!$this->session_token) {
            $this->getSessionToken();
        }

        $url = "$this->url_pagos/createTransactionPayment";

        $data = [
            'SessionToken' => $this->session_token,
            'EntityCode' => $this->entity_code,
            'ApiKey' => $this->api_key,
            'LangCode' => 'ES',
            "SrvCurrency" => "COP",
            "SrvCode" => $this->service_code,
        ];

        try {
            DB::beginTransaction();
            $pagoExistente = Pago::whereHas('detalles', function ($q) use ($id_reserva) {
                $q->where('tipo_concepto', 'reserva')
                    ->where('id_concepto', $id_reserva);
            })
                ->whereNull('eliminado_en')
                ->orderBy('creado_en', 'desc')
                ->first();

            if ($pagoExistente && $pagoExistente->estado == 'CREATED') {
                Log::info('Reutilizando pago existente CREATED para la reserva', [
                    'id_reserva' => $id_reserva,
                    'pago_codigo' => $pagoExistente->codigo,
                    'estado' => $pagoExistente->estado,
                ]);
                return $pagoExistente->url_ecollect;
            }

            if ($pagoExistente && $pagoExistente->estado == 'PENDING') {
                throw new Exception("Esperando resolución del pago actual");
            }

            if ($pagoExistente && in_array($pagoExistente->estado, ['ERROR', 'FAILED', 'EXPIRED', 'NOT_AUTHORIZED'], true)) {
                Log::info('Eliminando pago existente con estado ' . $pagoExistente->estado . ' para crear uno nuevo', [
                    'id_reserva' => $id_reserva,
                    'pago_codigo' => $pagoExistente->codigo,
                    'estado' => $pagoExistente->estado,
                ]);
                $pagoExistente->forceDelete();
                $pagoExistente = null;
            }

            $pago = $this->crearPago($id_reserva);
            $url_redirect = $this->url_redirect_base . '?codigo=' . $pago->codigo;

            $this->getSessionToken();
            $data['SessionToken'] = $this->session_token;
            $data['URLRedirect'] = $url_redirect;

            $data['TransValue'] = $pago->valor;

            $data['ReferenceArray'] = [
                $pago->reserva->usuarioReserva->persona->tipoDocumento->codigo,
                $pago->reserva->usuarioReserva->persona->numero_documento,
                $pago->codigo,
                $this->reserva_service->construirNombreCompleto($pago->reserva->usuarioReserva->persona),
                $pago->reserva->usuarioReserva->email,
                $pago->reserva->usuarioReserva->persona->celular,
            ];

            $response = Http::post($url, $data);

            if (!$response->successful()) {
                throw new Exception('Error iniciando transacción después de obtener token: ' . $response->body());
            }

            $responseData = $response->json();

            if (isset($responseData['ReturnCode']) && $responseData['ReturnCode'] === 'FAIL_APIEXPIREDSESSION') {
                $this->getSessionToken();
                $data['SessionToken'] = $this->session_token;
            }

            $pago->ticket_id = $responseData['TicketId'];
            $pago->url_ecollect = $responseData['eCollectUrl'];
            $pago->estado = 'pendiente';

            $pago->save();

            DB::commit();
            return $pago->url_ecollect;
        } catch (Throwable $th) {
            DB::rollBack();
            throw new Exception('Error iniciando transacción de pago: ' . $th->getMessage());
        }
    }

    public function crearPago(int $id_reserva): Pago
    {

        $reserva = $this->reserva_service->getReservaById($id_reserva);

        $reserva->loadMissing(['detalles', 'detalles.elemento', 'usuarioReserva']);

        $pago = Pago::create([
            'valor' => $reserva->precio_total,
            'estado' => 'inicial',
        ]);

        $detalles = [];
        $detalles[] = [
            'id_pago' => $pago->codigo,
            'tipo_concepto' => 'reserva',
            'cantidad' => 1,
            'id_concepto' => $reserva->id,
            'total' => $reserva->precio_total,
            'creado_en' => now(),
            'actualizado_en' => now(),
        ];

        $tipos = (array) optional($reserva->usuarioReserva)->tipos_usuario ?: [];
        foreach ($reserva->detalles ?? [] as $d) {
            $elem = $d->elemento;
            $cant = (int) ($d->cantidad ?? 0);
            if (!$elem || $cant <= 0) {
                continue;
            }

            $detalles[] = [
                'id_pago' => $pago->codigo,
                'tipo_concepto' => 'elemento',
                'cantidad' => $cant,
                'id_concepto' => $elem->id,
                'total' => $elem->valor_unitario * $cant,
                'creado_en' => now(),
                'actualizado_en' => now(),
            ];
        }

        if (!empty($detalles)) {
            PagosDetalles::insert($detalles);
        }

        return $pago->load([
            'reserva',
            'reserva.espacio',
            'reserva.configuracion',
            'reserva.usuarioReserva',
            'reserva.usuarioReserva.persona',
            'reserva.usuarioReserva.persona.tipoDocumento',
        ]);
    }

    public function crearPagoMensualidad(
        int $id_mensualidad,
        float $valor,
        int $cantidad = 1
    ): Pago {
        if ($valor <= 0) {
            throw new Exception('Mensualidad sin costo: no requiere creación de pago.');
        }

        $pago = Pago::create([
            'valor' => $valor * max(1, $cantidad),
            'estado' => 'inicial',
        ]);

        PagosDetalles::create([
            'id_pago' => $pago->codigo,
            'tipo_concepto' => 'mensualidad',
            'cantidad' => max(1, $cantidad),
            'id_concepto' => $id_mensualidad,
            'total' => $valor * max(1, $cantidad),
        ]);

        return $pago;
    }

    public function get_info_pago(string $codigo, bool $from_cron = false)
    {
        try {
            $pagoConsulta = PagoConsulta::where('codigo', $codigo)
                ->where('estado', 'OK')
                ->first();

            if ($pagoConsulta) {
                return $this->formatearRespuestaDesdePagoConsulta($pagoConsulta);
            }

            $pago = Pago::where('codigo', $codigo)->firstOrFail();

            // Incluir posibles registros soft-deleted en reserva/mensualidad
            $pago->load([
                'reserva' => function ($q) {
                    $q->withTrashed()->with([
                        'espacio',
                        'configuracion',
                        'usuarioReserva',
                        'usuarioReserva.persona',
                        'usuarioReserva.persona.tipoDocumento',
                    ]);
                },
                'mensualidad' => function ($q) {
                    $q->withTrashed()->with([
                        'usuario',
                        'usuario.persona',
                        'usuario.persona.tipoDocumento',
                        'espacio',
                    ]);
                },
            ]);

            $pagoInfo = $this->consultarPasarelaPago($pago->ticket_id);

            if ($pago->estado !== $pagoInfo['TranState']) {
                $pago->estado = $pagoInfo['TranState'];
                $pago->save();
            }

            if (!empty($pagoInfo['TranState'])) {
                DB::beginTransaction();
                try {
                    $pagoConsulta = $this->crearRegistroPagoConsulta($pago, $pagoInfo);

                    if ($pago->reserva) {
                        $pago->reserva->estado = 'completada';
                        $pago->reserva->save();
                    } elseif ($pago->mensualidad) {
                        $pago->mensualidad->estado = 'activa';
                        $pago->mensualidad->save();
                    }

                    DB::commit();

                    if ($pago->reserva && ($pagoInfo['TranState'] ?? null) === 'OK' && !$from_cron) {
                        try {
                            $pago->reserva->loadMissing(['espacio.sede', 'usuarioReserva:id_usuario,email']);
                            Mail::to($pago->reserva->usuarioReserva->email)
                                ->send(new ConfirmacionReservaEmail(
                                    $pago->reserva,
                                    (float)($pago->reserva->precio_base ?? 0),
                                    (float)($pago->reserva->precio_espacio ?? 0),
                                ));
                        } catch (Throwable $mailTh) {
                            Log::warning('Error enviando correo de confirmación post-pago', [
                                'reserva_id' => optional($pago->reserva)->id,
                                'pago_codigo' => $pago->codigo,
                                'error' => $mailTh->getMessage(),
                            ]);
                        }
                    }

                    return $this->formatearRespuestaDesdePagoConsulta($pagoConsulta);
                } catch (Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            }

            $transaccion = $this->formatearTransaccion($pagoInfo);
            if ($pago->mensualidad && !$pago->reserva) {
                return [
                    'pago' => [
                        'codigo' => $pago->codigo,
                        'valor' => $pago->valor,
                        'estado' => $pago->estado,
                        'ticket_id' => $pago->ticket_id,
                        'creado_en' => $pago->creado_en,
                        'actualizado_en' => $pago->actualizado_en,
                    ],
                    'transaccion' => $transaccion,
                    'mensualidad' => [
                        'id' => $pago->mensualidad->id,
                        'usuario' => [
                            'id' => $pago->mensualidad->usuario->id_usuario,
                            'documento' => optional($pago->mensualidad->usuario->persona)->numero_documento,
                            'nombre_completo' => optional($pago->mensualidad->usuario->persona)
                                ? (optional($pago->mensualidad->usuario->persona)->primer_nombre . ' ' . optional($pago->mensualidad->usuario->persona)->primer_apellido)
                                : null,
                            'email' => $pago->mensualidad->usuario->email,
                        ],
                        'estado' => $pago->mensualidad->estado,
                    ],
                ];
            }

            return [
                'pago' => [
                    'codigo' => $pago->codigo,
                    'valor' => $pago->valor,
                    'estado' => $pago->estado,
                    'ticket_id' => $pago->ticket_id,
                    'creado_en' => $pago->creado_en,
                    'actualizado_en' => $pago->actualizado_en,
                ],
                'transaccion' => $transaccion,
                'reserva' => [
                    'id' => $pago->reserva->id,
                    'hora_inicio' => $pago->reserva->hora_inicio,
                    'hora_fin' => $pago->reserva->hora_fin,
                    'codigo' => $pago->reserva->codigo,
                    'fecha' => $pago->reserva->fecha,
                    'usuario' => [
                        'id' => $pago->reserva->usuarioReserva->id,
                        'tipo_docuemnto' => $pago->reserva->usuarioReserva->persona->tipoDocumento->codigo . ' ' . $pago->reserva->usuarioReserva->persona->numero_documento,
                        'documento' => $pago->reserva->usuarioReserva->persona->numero_documento,
                        'nombre_completo' => $this->reserva_service->construirNombreCompleto($pago->reserva->usuarioReserva->persona),
                        'email' => $pago->reserva->usuarioReserva->email,
                        'celular' => $pago->reserva->usuarioReserva->persona->celular,
                    ],
                    'espacio' => [
                        'id' => $pago->reserva->espacio->id,
                        'nombre' => $pago->reserva->espacio->nombre,
                    ],
                ]
            ];
        } catch (Exception $e) {
            Log::error('Error al obtener información del pago', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'codigo' => $codigo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al obtener la información del pago',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    private function formatearTransaccion(array $pagoInfo): array
    {
        $data = [
            'entidad' => $pagoInfo['FiName'],
            'moneda' => $pagoInfo['PayCurrency'],
            'fecha_banco' => $pagoInfo['BankProcessDate'],
            'codigo_traza' => $pagoInfo['TrazabilityCode'],
            'tipo_doc_titular' => $pagoInfo['ReferenceArray'][0] ?? '',
        ];

        if ($pagoInfo['PaymentSystem'] === "0") {
            // Pse
            $data['tipo'] = 'PSE';
            $data['titular'] = $pagoInfo['PaymentInfoArray'][0]['AttributeValue'];
            $data['doc_titular'] = $pagoInfo['PaymentInfoArray'][1]['AttributeValue'];
        }

        if ($pagoInfo['PaymentSystem'] === "1") {
            // Tarjeta
            $data['tipo'] = 'Tarjeta';
            $data['cuotas'] = $pagoInfo['PaymentInfoArray'][0]['AttributeValue'];
            $data['digitos'] = $pagoInfo['PaymentInfoArray'][2]['AttributeValue'];
            $data['titular'] = $pagoInfo['PaymentInfoArray'][4]['AttributeValue'];
            $data['doc_titular'] = $pagoInfo['PaymentInfoArray'][3]['AttributeValue'];
        }

        return $data;
    }

    private function consultarPasarelaPago(int $ticketId): array
    {
        $url = "$this->url_pagos/getTransactionInformation";

        if (!$this->session_token) {
            $this->getSessionToken();
        }

        $pagoInfoResponse = Http::post($url, [
            'SessionToken' => $this->session_token,
            'EntityCode' => $this->entity_code,
            'TicketId' => $ticketId,
        ]);

        if (!$pagoInfoResponse->successful()) {
            throw new Exception('Error obteniendo información del pago: ' . $pagoInfoResponse->body());
        }

        $pagoInfo = $pagoInfoResponse->json();

        if (isset($pagoInfo['ReturnCode']) && $pagoInfo['ReturnCode'] === 'FAIL_APIEXPIREDSESSION') {
            $this->getSessionToken();
            $pagoInfoResponse = Http::post($url, [
                'SessionToken' => $this->session_token,
                'EntityCode' => $this->entity_code,
                'TicketId' => $ticketId,
            ]);

            if (!$pagoInfoResponse->successful()) {
                throw new Exception('Error obteniendo información del pago después de refrescar el token: ' . $pagoInfoResponse->body());
            }

            $pagoInfo = $pagoInfoResponse->json();
        }

        return $pagoInfo;
    }

    public function crearRegistroPagoConsulta(Pago $pago, array $pagoInfo): PagoConsulta
    {
        $transaccionFormateada = $this->formatearTransaccion($pagoInfo);

        $pago->loadMissing([
            'reserva.espacio',
            'reserva.configuracion',
            'reserva.usuarioReserva.persona.tipoDocumento',
            'mensualidad.usuario.persona.tipoDocumento',
        ]);

        $esReserva = (bool) $pago->reserva;
        $esMensualidad = !$esReserva && (bool) $pago->mensualidad;

        $payload = [
            'codigo' => $pago->codigo,
            'valor_real' => (float)($pagoInfo['TransValue'] ?? $pago->valor),
            'valor_transaccion' => (float)($pagoInfo['TransValue'] ?? $pago->valor),
            'estado' => $pagoInfo['TranState'] ?? $pago->estado,
            'ticket_id' => $pago->ticket_id,
            'codigo_traza' => $pagoInfo['TrazabilityCode'] ?? null,
            'medio_pago' => ($pagoInfo['PaymentSystem'] ?? "0") === "0" ? 'PSE' : 'Tarjeta',
            'tipo_doc_titular' => $transaccionFormateada['tipo_doc_titular'] ?? '',
            'nombre_medio_pago' => $pagoInfo['FiName'] ?? null,
            'tarjeta_oculta' => $transaccionFormateada['digitos'] ?? null,
            'ultimos_cuatro' => isset($transaccionFormateada['digitos']) ? substr($transaccionFormateada['digitos'], -4) : null,
            'fecha_banco' => $pagoInfo['BankProcessDate'] ?? now(),
            'moneda' => $pagoInfo['PayCurrency'] ?? 'COP',
        ];

        if ($esReserva) {
            // Valor real según franja
            $payload['valor_real'] = $pago->reserva->precio_total;

            $payload += [
                'numero_doc_titular' => $pago->reserva->usuarioReserva->persona->numero_documento,
                'nombre_titular' => $transaccionFormateada['titular'] ?? $this->reserva_service->construirNombreCompleto($pago->reserva->usuarioReserva->persona),
                'email_titular' => $pago->reserva->usuarioReserva->email,
                'celular_titular' => $pago->reserva->usuarioReserva->persona->celular,
                'descripcion_pago' => "Pago reserva {$pago->reserva->codigo}",
                'tipo_concepto' => 'reserva',
                'id_concepto' => $pago->reserva->id,
                'hora_inicio' => $pago->reserva->hora_inicio,
                'hora_fin' => $pago->reserva->hora_fin,
                'fecha_reserva' => $pago->reserva->fecha->format('Y-m-d'),
                'codigo_reserva' => $pago->reserva->codigo,
                'id_usuario_reserva' => $pago->reserva->usuarioReserva->id_usuario,
                'tipo_doc_usuario_reserva' => $pago->reserva->usuarioReserva->persona->tipoDocumento->codigo,
                'doc_usuario_reserva' => $pago->reserva->usuarioReserva->persona->numero_documento,
                'email_usuario_reserva' => $pago->reserva->usuarioReserva->email,
                'celular_usuario_reserva' => $pago->reserva->usuarioReserva->persona->celular,
                'id_espacio' => $pago->reserva->espacio->id,
                'nombre_espacio' => $pago->reserva->espacio->nombre,
            ];
        } elseif ($esMensualidad) {
            $usuario = $pago->mensualidad->usuario;
            $persona = optional($usuario)->persona;
            $payload += [
                'numero_doc_titular' => optional($persona)->numero_documento,
                'nombre_titular' => $transaccionFormateada['titular'] ?? ($persona ? $this->reserva_service->construirNombreCompleto($persona) : ''),
                'email_titular' => optional($usuario)->email,
                'celular_titular' => optional($persona)->celular,
                'descripcion_pago' => "Pago mensualidad {$pago->mensualidad->id}",
                'tipo_concepto' => 'mensualidad',
                'id_concepto' => $pago->mensualidad->id,
            ];
        }

        $pagoConsultaExistente = PagoConsulta::where('codigo', $pago->codigo)
            ->whereIn('estado', ['PENDING', 'CREATED'])
            ->first();

        if ($pagoConsultaExistente) {
            $pagoConsultaExistente->delete();
        }

        return PagoConsulta::create($payload);
    }

    private function formatearRespuestaDesdePagoConsulta(PagoConsulta $pagoConsulta): array
    {
        $respuesta = [
            'pago' => [
                'codigo' => $pagoConsulta->codigo,
                'valor' => $pagoConsulta->valor_transaccion ?? $pagoConsulta->valor_real,
                'estado' => $pagoConsulta->estado,
                'ticket_id' => $pagoConsulta->ticket_id,
                'creado_en' => $pagoConsulta->fecha_banco,
                'actualizado_en' => $pagoConsulta->fecha_banco,
            ],
            'transaccion' => [
                'entidad' => $pagoConsulta->nombre_medio_pago,
                'moneda' => $pagoConsulta->moneda,
                'fecha_banco' => $pagoConsulta->fecha_banco,
                'codigo_traza' => $pagoConsulta->codigo_traza,
                'tipo' => $pagoConsulta->medio_pago,
                'titular' => $pagoConsulta->nombre_titular,
                'doc_titular' => $pagoConsulta->numero_doc_titular,
                'digitos' => $pagoConsulta->tarjeta_oculta,
                'cuotas' => null,
            ],
        ];

        if ($pagoConsulta->tipo_concepto === 'mensualidad') {
            $mensualidad = Mensualidades::with(['usuario.persona', 'espacio'])
                ->find($pagoConsulta->id_concepto);
            $usuario = optional($mensualidad)->usuario;
            $persona = optional($usuario)->persona;
            $espacio = optional($mensualidad)->espacio;
            $respuesta['mensualidad'] = [
                'id' => $pagoConsulta->id_concepto,
                'fecha_inicio' => optional($mensualidad?->fecha_inicio)->format('Y-m-d'),
                'fecha_fin' => optional($mensualidad?->fecha_fin)->format('Y-m-d'),
                'usuario' => [
                    'id' => $usuario->id_usuario ?? null,
                    'tipo_documento' => optional($persona?->tipoDocumento)->codigo,
                    'documento' => $persona->numero_documento ?? $pagoConsulta->numero_doc_titular,
                    'nombre_completo' => $pagoConsulta->nombre_titular,
                    'email' => $usuario->email ?? null,
                    'celular' => $persona->celular ?? null,
                ],
                'espacio' => [
                    'id' => $espacio->id ?? null,
                    'nombre' => $espacio->nombre ?? null,
                ],
            ];
        } else {
            $respuesta['reserva'] = [
                'id' => $pagoConsulta->id_concepto,
                'hora_inicio' => $pagoConsulta->hora_inicio,
                'hora_fin' => $pagoConsulta->hora_fin,
                'codigo' => $pagoConsulta->codigo_reserva,
                'fecha' => $pagoConsulta->fecha_reserva,
                'usuario' => [
                    'id' => $pagoConsulta->id_usuario_reserva,
                    'tipo_documento' => $pagoConsulta->tipo_doc_usuario_reserva . ' ' . $pagoConsulta->doc_usuario_reserva,
                    'documento' => $pagoConsulta->doc_usuario_reserva,
                    'nombre_completo' => $pagoConsulta->nombre_titular,
                    'email' => $pagoConsulta->email_usuario_reserva,
                    'celular' => $pagoConsulta->celular_usuario_reserva,
                ],
                'espacio' => [
                    'id' => $pagoConsulta->id_espacio,
                    'nombre' => $pagoConsulta->nombre_espacio,
                ],
            ];
        }

        return $respuesta;
    }

    public function iniciarTransaccionDeMensualidad(int $id_mensualidad, int $cantidad = 1)
    {
        if (!$this->session_token) {
            $this->getSessionToken();
        }

        $url = "$this->url_pagos/createTransactionPayment";

        $data = [
            'SessionToken' => $this->session_token,
            'EntityCode' => $this->entity_code,
            'ApiKey' => $this->api_key,
            'LangCode' => 'ES',
            'SrvCurrency' => 'COP',
            'SrvCode' => $this->service_code,
        ];

        try {
            DB::beginTransaction();

            $mensualidad = Mensualidades::with(['usuario.persona.tipoDocumento'])
                ->findOrFail($id_mensualidad);

            $valorUnitario = (float) ($mensualidad->valor ?? 0);
            try {
                $espacio = Espacio::find($mensualidad->id_espacio);
                if ($espacio) {
                    $valorUnitario = $this->reserva_service->calcularValorMensualidadParaUsuario($espacio, $mensualidad->usuario);
                }
            } catch (\Throwable $th) {
                // mantener valorUnitario actual
            }
            $cantidad = max(1, (int) $cantidad);

            if ($valorUnitario <= 0) {
                throw new Exception('Mensualidad sin costo o valor inválido.');
            }

            $pago = $this->crearPagoMensualidad($mensualidad->id, $valorUnitario, $cantidad);

            $url_redirect = $this->url_redirect_base . '?codigo=' . $pago->codigo;

            $this->getSessionToken();
            $data['SessionToken'] = $this->session_token;
            $data['URLRedirect'] = $url_redirect;
            $data['TransValue'] = $pago->valor;

            $persona = optional($mensualidad->usuario)->persona;
            $tipoDoc = optional(optional($persona)->tipoDocumento)->codigo;
            $numeroDoc = optional($persona)->numero_documento;
            $nombreTitular = $persona
                ? $this->reserva_service->construirNombreCompleto($persona)
                : null;
            $emailTitular = optional($mensualidad->usuario)->email;
            $celularTitular = optional($persona)->celular;

            $data['ReferenceArray'] = [
                $tipoDoc,
                $numeroDoc,
                $pago->codigo,
                $nombreTitular,
                $emailTitular,
                $celularTitular,
            ];

            $response = Http::post($url, $data);

            if (!$response->successful()) {
                throw new Exception('Error iniciando transacción de mensualidad: ' . $response->body());
            }

            $responseData = $response->json();

            if (isset($responseData['ReturnCode']) && $responseData['ReturnCode'] === 'FAIL_APIEXPIREDSESSION') {
                $this->getSessionToken();
                $data['SessionToken'] = $this->session_token;
                $response = Http::post($url, $data);
                if (!$response->successful()) {
                    throw new Exception('Error iniciando transacción de mensualidad tras refrescar token: ' . $response->body());
                }
                $responseData = $response->json();
            }

            $pago->ticket_id = $responseData['TicketId'] ?? null;
            $pago->url_ecollect = $responseData['eCollectUrl'] ?? null;
            $pago->estado = 'pendiente';
            $pago->save();

            DB::commit();
            return $pago->url_ecollect;
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Error al iniciar transacción de mensualidad', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'id_mensualidad' => $id_mensualidad,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Error initiating monthly payment transaction: ' . $e->getMessage());
        }
    }

    public function crearMensualidad(int $id_espacio)
    {

        DB::beginTransaction();

        try {

            $espacio = Espacio::findOrFail($id_espacio);

            if (!$espacio->pago_mensual) {
                throw new Exception('El espacio no tiene pago mensual habilitado.');
            }

            $mensualidad = new Mensualidades();
            $mensualidad->id_espacio = $id_espacio;
            $mensualidad->id_usuario = Auth::id() ?? null;
            try {
                $usuario = Auth::user();
                $mensualidad->valor = $this->reserva_service->calcularValorMensualidadParaUsuario($espacio, $usuario);
            } catch (\Throwable $th) {
                $mensualidad->valor = (float) ($espacio->valor_mensualidad ?? 0);
            }
            $mensualidad->fecha_inicio = Carbon::now()->startOfDay();
            $mensualidad->fecha_fin = Carbon::now()->addDays(30)->endOfDay();
            $mensualidad->estado = 'pendiente';
            $mensualidad->save();

            DB::commit();
            return $mensualidad;
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Error al crear mensualidad', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'id_espacio' => $id_espacio,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Error creando el registro de mensualidad: ' . $e->getMessage());
        }
    }
}
