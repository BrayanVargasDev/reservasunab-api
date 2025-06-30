<?php

namespace App\Http\Controllers;

use App\Exceptions\EspacioTipoUsuarioConfigException;
use App\Models\EspacioTipoUsuarioConfig;
use App\Services\EspacioTipoUsuarioConfigService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EspacioTipoUsuarioConfigController extends Controller
{
    private $espacio_tipo_usuario_config_service;
    public function __construct(EspacioTipoUsuarioConfigService $espacioTipoUsuarioConfigService)
    {
        $this->espacio_tipo_usuario_config_service = $espacioTipoUsuarioConfigService;
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'id_espacio' => 'required|integer',
                'tipo_usuario' => 'required|string|in:administrativo,estudiante,egresado,externo',
                'porcentaje_descuento' => 'sometimes|numeric|min:0|max:100',
                'minutos_retraso' => 'sometimes|integer|min:0',
            ]);
            $espacio = $this->espacio_tipo_usuario_config_service->create($data);

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

    public function update(Request $request, EspacioTipoUsuarioConfig $tipoUsuarioConfig)
    {
        try {
            // $this->authorize('editar', $config);
            $data = $request->validate([
                'porcentaje_descuento' => 'sometimes|numeric|min:0|max:100',
                'minutos_retraso' => 'sometimes|integer|min:0',
            ]);

            $config = $this->espacio_tipo_usuario_config_service->update($tipoUsuarioConfig->id, $data);

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $config,
                    'message' => 'Espacio actualizado correctamente',
                ],
                200,
            );
        } catch (EspacioTipoUsuarioConfigException $e) {
            Log::warning('Problema al actualizar config', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'espacio_buscado_id' => $tipoUsuarioConfig->id,
                'error_type' => $e->getErrorType(),
                'error' => $e->getMessage(),
            ]);

            return $e->render();
        } catch (Exception $e) {
            Log::error('Error al actualizar config', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'espacio_buscado_id' => $tipoUsuarioConfig->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al actualizar el config',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
