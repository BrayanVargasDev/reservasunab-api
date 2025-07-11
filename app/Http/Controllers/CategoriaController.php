<?php

namespace App\Http\Controllers;

use App\Services\CategoriaService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CategoriaController extends Controller
{
    private $categoria_service;

    public function __construct(CategoriaService $categoria_service)
    {
        $this->categoria_service = $categoria_service;
    }

    public function index(Request $request)
    {
        try {

            $search = $request->query('search', '');
            $per_page = $request->query('per_page', null);

            $categorias = $this->categoria_service->getAll($per_page, $search);
            return $per_page ? response()->json($categorias, 200) : response()->json([
                'success' => true,
                'data' => $categorias,
            ], 200);
        } catch (Exception $e) {
            Log::error('Error al consultar categorías', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al obtener los categorías',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function store(Request $request)
    {
        $validated_data = $request->validate([
            'nombre' => 'required|string|max:255',
            'id_grupo' => 'sometimes|integer|exists:grupos,id',
        ]);

        try {
            $categoria = $this->categoria_service->create($validated_data);
            return response()->json([
                'success' => true,
                'data' => $categoria,
            ], 201);
        } catch (Exception $e) {
            Log::error('Error al crear categoría', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al crear la categoría',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function update(Request $request, $id)
    {
        $validated_data = $request->validate([
            'nombre' => 'required|string|max:255',
            'id_grupo' => 'sometimes|integer|exists:grupos,id',
        ]);

        try {
            $categoria = $this->categoria_service->update($id, $validated_data);
            return response()->json([
                'success' => true,
                'data' => $categoria,
            ], 200);
        } catch (Exception $e) {
            Log::error('Error al actualizar categoría', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al actualizar la categoría',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function destroy($id)
    {
        try {

            $categoria = $this->categoria_service->destroy($id);

            return response()->json([
                'success' => true,
                'data' => $categoria,
                'message' => 'Categoría eliminada correctamente',
            ], 200);
        } catch (Exception $e) {
            Log::error('Error al eliminar categoría', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al eliminar la categoría',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function restore($id)
    {
        try {
            $categoria = $this->categoria_service->restore($id);
            return response()->json([
                'success' => true,
                'data' => $categoria,
                'message' => 'Categoría restaurada correctamente',
            ], 200);
        } catch (Exception $e) {
            Log::error('Error al restaurar categoría', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al restaurar la categoría',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
