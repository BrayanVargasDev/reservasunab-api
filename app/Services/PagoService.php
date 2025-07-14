<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Throwable;

class PagoService
{

    private $api_key = app('config')->get('key_pagos');
    private $url_pagos = app('config')->get('url_pagos');
    private $entity_code = app('config')->get('entity_code');
    private $service_code = app('config')->get('service_code');
    private $session_token;

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

    public function iniciarTransaccionDePago()
    {
        if (!$this->session_token) {
            $this->getSessionToken();
        }

        $url = "$this->url_pagos/iniciarTransaccionDePago";

        $data = [
            'SessionToken' => $this->session_token,
            'EntityCode' => $this->entity_code,
            'ApiKey' => $this->api_key,
        ];

        try {
            $response = Http::post($url, $data);

            if (!$response->successful()) {
                throw new Exception('Error iniciando la transacción de pago: ' . $response->body());
            }

            $responseData = $response->json();

            if (isset($responseData['ReturnCode']) || $responseData['ReturnCode'] !== 'FAIL_APIEXPIREDSESSION') {
                return $responseData;
            }

            // TODO: Crear un pago y gestionar la url con el id del pago antes de hacer la petición.
            // TODO: Si la petición es exitosa entonces actualizar el pago con el id del ticket y la url de la pasarela.
            // TODO: Si la petición falla borrar el pago y lanzar una excepción.
            // * Los datos del pago se hacen con los datos de la persona entonces en Auth::id() debe tener una persona.

            $this->getSessionToken();
            $data['SessionToken'] = $this->session_token;

            $response = Http::post($url, $data);

            if (!$response->successful()) {
                throw new Exception('Error initiating payment transaction after token refresh: ' . $response->body());
            }

            return $response->json();
        } catch (Throwable $th) {
            throw new Exception('Error initiating payment transaction: ' . $th->getMessage());
        }
    }
}
