<?php

namespace App\Services;

use App\Models\Pago;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

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

            Log::debug([
                'response' => $responseData,
            ]);

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

        $pago = Pago::create([
            'id_reserva' => $reserva->id,
            'valor' => $this->reserva_service->obtenerValorReserva(
                [],
                $reserva->configuracion->id,
                $reserva->hora_inicio,
                $reserva->hora_fin
            ),
            'estado' => 'inicial',
        ]);

        return $pago->load([
            'reserva',
            'reserva.espacio',
            'reserva.configuracion',
            'reserva.usuarioReserva',
            'reserva.usuarioReserva.persona',
            'reserva.usuarioReserva.persona.tipoDocumento',
        ]);
    }

    public function get_info_pago(string $codigo)
    {

        $url = "$this->url_pagos/getTransactionInformation";

        if (!$this->session_token) {
            $this->getSessionToken();
        }

        try {
            $pago = Pago::where('codigo', $codigo)->firstOrFail();

            $pagoInfoResponse = Http::post($url, [
                'SessionToken' => $this->session_token,
                'EntityCode' => $this->entity_code,
                'TicketId' => $pago->ticket_id,
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
                    'TicketId' => $pago->ticket_id,
                ]);

                if (!$pagoInfoResponse->successful()) {
                    throw new Exception('Error obteniendo información del pago después de refrescar el token: ' . $pagoInfoResponse->body());
                }

                $pagoInfo = $pagoInfoResponse->json();
            }

            Log::debug($pagoInfo);

            if ($pago->estado !== $pagoInfo['TranState']) {
                $pago->estado = $pagoInfo['TranState'];
                $pago->save();
            }

            $pago->load([
                'reserva',
                'reserva.espacio',
                'reserva.configuracion',
                'reserva.usuarioReserva',
                'reserva.usuarioReserva.persona',
                'reserva.usuarioReserva.persona.tipoDocumento',
            ]);

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
}
