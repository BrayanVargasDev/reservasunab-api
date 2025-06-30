<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class ArchivoController extends Controller
{
    /**
     * Servir un archivo (imagen) por su ruta para visualización
     *
     * @param Request $request
     * @return Response
     */
    public function servir(Request $request)
    {
        try {
            // Obtener la ruta del archivo desde el parámetro 'ruta'
            $rutaArchivo = $request->get('ruta');
            
            if (!$rutaArchivo) {
                return response()->json([
                    'error' => 'No se especificó la ruta del archivo'
                ], 400);
            }

            // Decodificar la URL por si tiene caracteres especiales
            $rutaArchivo = urldecode($rutaArchivo);

            // Verificar que el archivo existe en el storage público
            if (!Storage::disk('public')->exists($rutaArchivo)) {
                return response()->json([
                    'error' => 'Archivo no encontrado'
                ], 404);
            }

            // Obtener el contenido del archivo
            $contenidoArchivo = Storage::disk('public')->get($rutaArchivo);
            
            // Obtener la ruta completa para determinar el tipo MIME
            $rutaCompleta = storage_path('app/public/' . $rutaArchivo);
            $mimeType = File::mimeType($rutaCompleta);

            // Retornar el archivo con las cabeceras apropiadas
            return response($contenidoArchivo)
                ->header('Content-Type', $mimeType)
                ->header('Cache-Control', 'public, max-age=31536000') // Cache por 1 año
                ->header('Expires', gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET')
                ->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization');

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al servir el archivo: ' . $e->getMessage()
            ], 500);
        }
    }
}
