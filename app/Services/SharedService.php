<?php

namespace App\Services;

use App\Models\Fecha;
use App\Models\Grupo;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SharedService
{

    private $fechas_url = 'https://date.nager.at/api/v3';
    private $codigo_pais = 'CO';


    public function seed_fechas(int | string $anio)
    {
        try {
            $url = $this->fechas_url . '/PublicHolidays' . '/' . $anio . '/' . $this->codigo_pais;
            $response = Http::get($url);

            if (!$response->successful()) {
                throw new Exception('Error al obtener los datos de la API: ' . $response->status());
            }

            $data = $response->json();

            if (empty($data)) {
                throw new Exception('No se encontraron datos para el año ' . $anio);
            }

            $fechas = [];

            foreach ($data as $fecha) {
                $fechas[] = [
                    'fecha' => $fecha['date'],
                    'descripcion' => $fecha['localName'],
                ];
            }

            Fecha::insert($fechas);
        } catch (Exception $e) {
            throw new Exception('Error al procesar las fechas: ' . $e->getMessage());
        }
    }

    public function get_grupos()
    {
        try {
            $grupos = Grupo::orderBy('nombre')
                ->get();

            return $grupos;
        } catch (Exception $e) {
            Log::error('Error al obtener grupos', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
