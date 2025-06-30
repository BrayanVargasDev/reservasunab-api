<?php

namespace App\Http\Controllers;

use App\Exceptions\EspacioConfiguracionException;
use App\Models\EspacioConfiguracion;
use App\Services\EspacioConfiguracionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EspacioConfiguracionController extends Controller
{
    private $espacio_config_service;

    public function __construct(EspacioConfiguracionService $espacioConfigService)
    {
        $this->espacio_config_service = $espacioConfigService;
    }

    public function index(Request $request)
    {
        try {
            // $this->authorize('verTodos', Usuario::class);
            $id_espacio = $request->input('id_espacio');
            $espacios = $this->espacio_config_service->getAll($id_espacio);
            return response()->json(
                [
                    'status' => 'success',
                    'data' => $espacios,
                    'message' => 'Espacios obtenidos correctamente.',
                ],
                200,
            );
        } catch (Exception $e) {
            Log::error('Error al consultar usuarios', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al obtener los usuarios',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function showPorFecha(Request $request)
    {
        try {
            // $this->authorize('ver', $configuracion);

            $id_espacio = $request->input('id_espacio');
            $fecha = $request->input('fecha');

            if (!$id_espacio || !$fecha) {
                return response()->json(
                    [
                        'status' => 'error',
                        'message' => 'Faltan parámetros requeridos',
                    ],
                    400,
                );
            }

            $configuracion = $this->espacio_config_service->getPorFecha($id_espacio, $fecha);

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $configuracion,
                    'message' => 'Configuración obtenida correctamente.',
                ],
                200,
            );
        } catch (Exception $e) {
            Log::error('Error al consultar configuración', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'fecha' => $request->input('fecha'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al obtener la configuración',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'minutos_uso' => 'required|integer',
                'id_espacio' => 'required|integer|exists:espacios,id',
                'hora_apertura' => 'required|string|date_format:H:i',
                'dias_previos_apertura' => 'required|integer|min:1|max:30',
                'dia_semana' => 'sometimes|in:1,2,3,4,5,6,7,8',
                'tiempo_cancelacion' => 'required|integer|min:1',
                'fecha' => 'sometimes|date',
                'franjas_horarias' => 'sometimes|array',
                'franjas_horarias.*.hora_inicio' => 'required_with:franjas_horarias|date_format:H:i',
                'franjas_horarias.*.hora_fin' => 'required_with:franjas_horarias|date_format:H:i|after:franjas_horarias.*.hora_inicio',
                'franjas_horarias.*.valor' => 'required_with:franjas_horarias|numeric|min:0',
            ]);
            $espacio = $this->espacio_config_service->create($data);

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $espacio,
                    'message' => 'Espacio creado correctamente.',
                ],
                201,
            );
        } catch (Exception $e) {
            Log::error('Error al crear espacio.', [
                'espacio_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' =>
                    'Ocurrió un error al crear el espacio',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function update(Request $request, EspacioConfiguracion $tipoUsuarioConfig)
    {
        try {
            // $this->authorize('editar', $config_base);
            $data = $request->validate([
                'id' => 'required|integer|exists:espacios_configuracion,id',
                'minutos_uso' => 'required|integer',
                'id_espacio' => 'required|integer|exists:espacios,id',
                'hora_apertura' => 'required|string|date_format:H:i',
                'dias_previos_apertura' => 'required|integer|min:1|max:30',
                'dia_semana' => 'sometimes|in:1,2,3,4,5,6,7,8',
                'tiempo_cancelacion' => 'required|integer|min:1',
                'fecha' => 'sometimes|date',
                'franjas_horarias' => 'sometimes|array',
                'franjas_horarias.*.hora_inicio' => 'required_with:franjas_horarias|date_format:H:i',
                'franjas_horarias.*.hora_fin' => 'required_with:franjas_horarias|date_format:H:i|after:franjas_horarias.*.hora_inicio',
                'franjas_horarias.*.valor' => 'required_with:franjas_horarias|numeric|min:0',
            ]);

            $config_base = $this->espacio_config_service->update($data['id'], $data);

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $config_base,
                    'message' => 'Configuracion del espacio actualizada correctamente',
                ],
                200,
            );
        } catch (EspacioConfiguracionException $e) {
            Log::warning('Problema al actualizar config_base', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'espacio_buscado_id' => $tipoUsuarioConfig->id,
                'error_type' => $e->getErrorType(),
                'error' => $e->getMessage(),
            ]);

            return $e->render();
        } catch (Exception $e) {
            Log::error('Error al actualizar config_base', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'espacio_buscado_id' => $tipoUsuarioConfig->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al actualizar el config_base',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
