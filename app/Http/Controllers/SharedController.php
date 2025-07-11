<?php

namespace App\Http\Controllers;

use App\Services\SharedService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class SharedController extends Controller
{

    private $shared_service;

    public function __construct(SharedService $shared_service)
    {
        $this->shared_service = $shared_service;
    }

    public function fechas(Request $request)
    {
        $anio = $request->input('anio');

        if (!$anio) {
            return response()->json(['error' => 'El año es requerido'], 400);
        }

        try {
            $this->shared_service->seed_fechas($anio);
            return response()->json(['message' => 'Fechas sembradas correctamente'], 200);
        } catch (Exception $e) {
            Log::error('Error al insertar fechas', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al insertar fechas',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Servir archivos (imágenes) desde el storage público
     *
     * @param Request $request
     * @return Response
     */
    public function servirArchivo(string $ruta)
    {
        try {
            if (!$ruta) {
                return response()->json([
                    'error' => 'No se especificó la ruta del archivo'
                ], 400);
            }

            $rutaArchivo = urldecode($ruta);

            if (!Storage::disk('public')->exists($rutaArchivo)) {
                return response()->json([
                    'error' => 'Archivo no encontrado'
                ], 404);
            }

            $contenidoArchivo = Storage::disk('public')->get($rutaArchivo);

            $rutaCompleta = storage_path('app/public/' . $rutaArchivo);
            $mimeType = File::mimeType($rutaCompleta);

            return response($contenidoArchivo)
                ->header('Content-Type', $mimeType)
                ->header('Cache-Control', 'public, max-age=31536000') // Cache por 1 año
                ->header('Expires', gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET')
                ->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization');
        } catch (Exception $e) {
            Log::error('Error al servir archivo', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'ruta' => $rutaArchivo ?? 'no especificada',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Error al servir el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    public function grupos()
    {
        try {
            $grupos = $this->shared_service->get_grupos();
            return response()->json([
                'status' => 'success',
                'data' => $grupos
            ])->setStatusCode(200);
        } catch (Exception $e) {
            Log::error('Error al obtener grupos', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Error al obtener grupos'], 500);
        }
    }

    public function crearGrupo() {}

    public function eliminarGrupo() {}

    public function actualizarGrupo() {}
}
