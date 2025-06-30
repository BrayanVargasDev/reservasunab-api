<?php

namespace App\Http\Controllers;

use App\Http\Resources\TipoDocumentoResource;
use App\Models\TipoDocumento;
use App\Services\TipoDocumentoService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TipoDocumentoController extends Controller
{
    private $tipoDocumentoService;

    public function __construct(TipoDocumentoService $tipoDocumentoService)
    {
        $this->tipoDocumentoService = $tipoDocumentoService;
    }

    public function index()
    {
        try {
            $tiposDocumento = $this->tipoDocumentoService->getAll();

            return TipoDocumentoResource::collection($tiposDocumento)
                ->additional([
                    'status' => 'success',
                    'message' => 'Tipos de documento obtenidos correctamente',
                ]);
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


    public function show(TipoDocumento $tipoDocumento)
    {
        try {
            $tipoDocumento = $this->tipoDocumentoService->getById($tipoDocumento->id_tipo);

            return new TipoDocumentoResource($tipoDocumento);
        } catch (Exception $e) {
            Log::error('Error al consultar tipo de documento', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al obtener el tipo de documento',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
