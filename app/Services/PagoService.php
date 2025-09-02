<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\PagoConsulta;
use App\Models\Movimientos;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Models\PagosDetalles;

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

            // Valor de la franja (con descuento por tipo de usuario)
            $valoresReserva = $this->reserva_service->obtenerValorReserva(
                null,
                $reserva->configuracion->id ?? null,
                $reserva->hora_inicio,
                $reserva->hora_fin
            );

            $valorReserva = 0.0;
            if (is_array($valoresReserva) && isset($valoresReserva['valor_descuento'])) {
                $valorReserva = (float) $valoresReserva['valor_descuento'];
            } elseif (isset($reserva->valor)) {
                $valorReserva = (float) $reserva->valor;
            }

            // Valor de elementos (si los hay) multiplicando por cantidad
            $reserva->loadMissing(['detalles.elemento', 'usuarioReserva']);
            $valorElementos = $this->calcularValorElementos($reserva);
            $valorAPagar = $valorReserva + $valorElementos;

            if ($valorAPagar <= 0) {
                throw new Exception('No hay valor por pagar para esta reserva');
            }

            $idUsuarioReserva = (int) ($reserva->usuarioReserva->id_usuario ?? $reserva->id_usuario);
            $saldoFavor = $this->obtenerSaldoFavorUsuario($idUsuarioReserva);

            if ($saldoFavor <= 0 || $saldoFavor < $valorAPagar) {
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
                'valor' => $valorAPagar,
                'tipo' => Movimientos::TIPO_EGRESO,
                'creado_por' => Auth::id(),
            ]);

            $reserva->estado = 'completada';
            $reserva->save();

            DB::commit();

            $saldoRestante = max(0, $saldoFavor - $valorAPagar);
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
            'reserva',
            'reserva.espacio',
            'reserva.configuracion',
            'reserva.usuarioReserva',
            'reserva.usuarioReserva.persona',
            'reserva.usuarioReserva.persona.tipoDocumento',
        ])
            ->orderBy('creado_en', 'desc');

        if (!$esAdministrador) {
            $query->whereHas('reserva.usuarioReserva', function ($q) use ($usuario) {
                $q->where('id_usuario', $usuario->id_usuario);
            });
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'ilike', "%$search%")
                    ->orWhere('ticket_id', 'ilike', "%$search%")
                    ->orWhereRaw("to_char(creado_en, 'YYYY-MM-DD') ILIKE ?", ["%$search%"])
                    ->orWhereRaw("to_char(creado_en, 'DD/MM/YYYY') ILIKE ?", ["%$search%"])
                    ->orWhereRaw("to_char(creado_en, 'YYYYMMDD') ILIKE ?", ["%$search%"])
                    ->orWhereRaw("to_char(creado_en, 'YYYY-MM') ILIKE ?", ["%$search%"])
                    ->orWhereRaw("to_char(creado_en, 'MM/YYYY') ILIKE ?", ["%$search%"]);

                $q->orWhereHas('reserva.usuarioReserva.persona', function ($userQuery) use ($search) {
                    $userQuery->where('numero_documento', 'ilike', "%$search%")
                        ->orWhere('primer_nombre', 'ilike', "%$search%")
                        ->orWhere('segundo_nombre', 'ilike', "%$search%")
                        ->orWhere('primer_apellido', 'ilike', "%$search%")
                        ->orWhere('segundo_apellido', 'ilike', "%$search%");
                });

                $q->orWhereHas('reserva.espacio', function ($espacioQ) use ($search) {
                    $espacioQ->where('nombre', 'ilike', "%$search%");
                });

                $q->orWhereHas('reserva', function ($reservaQ) use ($search) {
                    $reservaQ->whereRaw("to_char(fecha, 'YYYY-MM-DD') ILIKE ?", ["%$search%"])
                        ->orWhereRaw("to_char(fecha, 'DD/MM/YYYY') ILIKE ?", ["%$search%"])
                        ->orWhereRaw("to_char(fecha, 'YYYY-MM') ILIKE ?", ["%$search%"])
                        ->orWhereRaw("to_char(fecha, 'MM/YYYY') ILIKE ?", ["%$search%"]);
                });
            });
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
                throw new Exception('Error initiating payment transaction after token refresh: ' . $response->body());
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
            throw new Exception('Error initiating payment transaction: ' . $th->getMessage());
        }
    }

    public function crearPago(int $id_reserva): Pago
    {

        $reserva = $this->reserva_service->getReservaById($id_reserva);

        $valoresReserva = $this->reserva_service->obtenerValorReserva(
            [],
            $reserva->configuracion->id,
            $reserva->hora_inicio,
            $reserva->hora_fin
        );

        $reserva->loadMissing(['detalles', 'detalles.elemento', 'usuarioReserva']);
        $valorElementos = $this->calcularValorElementos($reserva);

        $valorReserva = $valoresReserva ? (float) $valoresReserva['valor_descuento'] : 0.0;
        $valorTotal = $valorReserva + $valorElementos;

        if ($valorTotal <= 0) {
            throw new Exception('Reserva sin costo: no requiere creación de pago.');
        }

        $pago = Pago::create([
            'valor' => $valorTotal,
            'estado' => 'inicial',
        ]);

        $detalles = [];
        $detalles[] = [
            'id_pago' => $pago->codigo,
            'tipo_concepto' => 'reserva',
            'cantidad' => 1,
            'id_concepto' => $reserva->id,
            'total' => $valorReserva,
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

            if (in_array('estudiante', $tipos) && $elem->valor_estudiante !== null) {
                $valorUnit = (float) $elem->valor_estudiante;
            } elseif (in_array('egresado', $tipos) && $elem->valor_egresado !== null) {
                $valorUnit = (float) $elem->valor_egresado;
            } elseif (in_array('administrativo', $tipos) && $elem->valor_administrativo !== null) {
                $valorUnit = (float) $elem->valor_administrativo;
            } elseif ($elem->valor_externo !== null) {
                $valorUnit = (float) $elem->valor_externo;
            } else {
                $valorUnit = 0.0;
            }

            $detalles[] = [
                'id_pago' => $pago->codigo,
                'tipo_concepto' => 'elemento',
                'cantidad' => $cant,
                'id_concepto' => $elem->id,
                'total' => $valorUnit * $cant,
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

    private function calcularValorElementos($reserva): float
    {
        if (!$reserva || !$reserva->relationLoaded('detalles') || !$reserva->detalles) {
            return 0.0;
        }

        $tipos = (array) optional($reserva->usuarioReserva)->tipos_usuario ?: [];

        $total = 0.0;
        foreach ($reserva->detalles as $d) {
            $elem = $d->elemento;
            $cant = (int) ($d->cantidad ?? 0);
            if (!$elem || $cant <= 0) {
                continue;
            }

            $valorUnit = null;
            if (in_array('estudiante', $tipos) && $elem->valor_estudiante !== null) {
                $valorUnit = (float) $elem->valor_estudiante;
            } elseif (in_array('egresado', $tipos) && $elem->valor_egresado !== null) {
                $valorUnit = (float) $elem->valor_egresado;
            } elseif (in_array('administrativo', $tipos) && $elem->valor_administrativo !== null) {
                $valorUnit = (float) $elem->valor_administrativo;
            } elseif ($elem->valor_externo !== null) {
                $valorUnit = (float) $elem->valor_externo;
            } else {
                $valorUnit = 0.0;
            }

            $total += $valorUnit * $cant;
        }
        return $total;
    }

    public function get_info_pago(string $codigo)
    {
        try {
            // Primero buscar en PagoConsulta
            $pagoConsulta = PagoConsulta::where('codigo', $codigo)->first();

            if ($pagoConsulta) {
                return $this->formatearRespuestaDesdePagoConsulta($pagoConsulta);
            }

            $pago = Pago::where('codigo', $codigo)->firstOrFail();

            $pago->load([
                'reserva',
                'reserva.espacio',
                'reserva.configuracion',
                'reserva.usuarioReserva',
                'reserva.usuarioReserva.persona',
                'reserva.usuarioReserva.persona.tipoDocumento',
            ]);

            $pagoInfo = $this->consultarPasarelaPago($pago->ticket_id);

            if ($pago->estado !== $pagoInfo['TranState']) {
                $pago->estado = $pagoInfo['TranState'];
                $pago->save();
            }

            if ($this->esEstadoExitoso($pagoInfo['TranState'])) {
                DB::beginTransaction();

                try {
                    $pagoConsulta = $this->crearRegistroPagoConsulta($pago, $pagoInfo);

                    $pago->reserva->estado = 'completada';
                    $pago->reserva->save();

                    DB::commit();

                    return $this->formatearRespuestaDesdePagoConsulta($pagoConsulta);
                } catch (Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            }

            $transaccion = $this->formatearTransaccion($pagoInfo);

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

    private function esEstadoExitoso(string $estado): bool
    {
        $estadosExitosos = ['completado', 'approved', 'APPROVED', 'success', 'SUCCESS', 'pagado', 'OK'];
        return in_array(strtolower($estado), array_map('strtolower', $estadosExitosos));
    }

    private function crearRegistroPagoConsulta(Pago $pago, array $pagoInfo): PagoConsulta
    {
        $transaccionFormateada = $this->formatearTransaccion($pagoInfo);

        // Obtener valores reales y con descuento
        $valoresReserva = $this->reserva_service->obtenerValorReserva(
            [],
            $pago->reserva->configuracion->id,
            $pago->reserva->hora_inicio,
            $pago->reserva->hora_fin
        );

        $valorReal = $valoresReserva ? $valoresReserva['valor_real'] : $pago->valor;

        return PagoConsulta::create([
            'codigo' => $pago->codigo,
            'valor_real' => $valorReal, // Valor sin descuento
            'valor_transaccion' => $pagoInfo['TransValue'] ?? $pago->valor, // Valor de la transacción del proveedor
            'estado' => $pagoInfo['TranState'],
            'ticket_id' => $pago->ticket_id,
            'codigo_traza' => $pagoInfo['TrazabilityCode'],
            'medio_pago' => $pagoInfo['PaymentSystem'] === "0" ? 'PSE' : 'Tarjeta',
            'tipo_doc_titular' => $transaccionFormateada['tipo_doc_titular'] ?? '',
            'numero_doc_titular' => $pago->reserva->usuarioReserva->persona->numero_documento,
            'nombre_titular' => $transaccionFormateada['titular'] ?? $this->reserva_service->construirNombreCompleto($pago->reserva->usuarioReserva->persona),
            'email_titular' => $pago->reserva->usuarioReserva->email,
            'celular_titular' => $pago->reserva->usuarioReserva->persona->celular,
            'descripcion_pago' => "Pago reserva {$pago->reserva->codigo}",
            'nombre_medio_pago' => $pagoInfo['FiName'],
            'tarjeta_oculta' => $transaccionFormateada['digitos'] ?? null,
            'ultimos_cuatro' => isset($transaccionFormateada['digitos']) ? substr($transaccionFormateada['digitos'], -4) : null,
            'fecha_banco' => $pagoInfo['BankProcessDate'],
            'moneda' => $pagoInfo['PayCurrency'],
            'id_reserva' => $pago->reserva->id,
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
        ]);
    }

    private function formatearRespuestaDesdePagoConsulta(PagoConsulta $pagoConsulta): array
    {
        return [
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
                'cuotas' => null, // Este campo no se guarda en PagoConsulta
            ],
            'reserva' => [
                'id' => $pagoConsulta->id_reserva,
                'hora_inicio' => $pagoConsulta->hora_inicio,
                'hora_fin' => $pagoConsulta->hora_fin,
                'codigo' => $pagoConsulta->codigo_reserva,
                'fecha' => $pagoConsulta->fecha_reserva,
                'usuario' => [
                    'id' => $pagoConsulta->id_usuario_reserva,
                    'tipo_docuemnto' => $pagoConsulta->tipo_doc_usuario_reserva . ' ' . $pagoConsulta->doc_usuario_reserva,
                    'documento' => $pagoConsulta->doc_usuario_reserva,
                    'nombre_completo' => $pagoConsulta->nombre_titular,
                    'email' => $pagoConsulta->email_usuario_reserva,
                    'celular' => $pagoConsulta->celular_usuario_reserva,
                ],
                'espacio' => [
                    'id' => $pagoConsulta->id_espacio,
                    'nombre' => $pagoConsulta->nombre_espacio,
                ],
            ]
        ];
    }
}
