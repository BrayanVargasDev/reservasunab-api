<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCiudadRequest;
use App\Http\Requests\UpdateCiudadRequest;
use App\Models\Ciudad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CiudadController extends Controller
{
    /**
     * Display a listing of all cities without pagination.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search', '');
            $includeDepartamento = $request->get('include_departamento', false);

            $query = Ciudad::query();

            // Incluir departamento si se solicita
            if ($includeDepartamento) {
                $query->with(['departamento:id,nombre,codigo']);
            }

            // Aplicar filtros de búsqueda si se proporciona
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(nombre) LIKE ?', ['%' . strtolower($search) . '%'])
                        ->orWhere('codigo', 'LIKE', '%' . $search . '%');
                });
            }

            $ciudades = $query->select('id', 'nombre', 'codigo', 'id_departamento')
                ->orderBy('nombre', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $ciudades,
                'message' => 'Ciudades obtenidas exitosamente',
                'total' => $ciudades->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las ciudades: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCiudadRequest $request): JsonResponse
    {
        try {
            $ciudad = Ciudad::create($request->validated());
            $ciudad->load(['departamento:id,nombre,codigo']);

            return response()->json([
                'success' => true,
                'data' => $ciudad,
                'message' => 'Ciudad creada exitosamente'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la ciudad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Ciudad $ciudad): JsonResponse
    {
        try {
            $ciudad->load(['departamento:id,nombre,codigo']);

            return response()->json([
                'success' => true,
                'data' => $ciudad,
                'message' => 'Ciudad obtenida exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la ciudad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCiudadRequest $request, Ciudad $ciudad): JsonResponse
    {
        try {
            $ciudad->update($request->validated());
            $ciudad->load(['departamento:id,nombre,codigo']);

            return response()->json([
                'success' => true,
                'data' => $ciudad,
                'message' => 'Ciudad actualizada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la ciudad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Ciudad $ciudad): JsonResponse
    {
        try {
            // Verificar si hay personas que usan esta ciudad como lugar de expedición o residencia
            if ($ciudad->personasExpedicion()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la ciudad porque hay personas que la tienen como lugar de expedición del documento'
                ], 400);
            }

            if ($ciudad->personasResidencia()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la ciudad porque hay personas que la tienen como lugar de residencia'
                ], 400);
            }

            $ciudad->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ciudad eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la ciudad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cities by department ID
     *
     * @param int $departamentoId
     * @return JsonResponse
     */
    public function porDepartamento(int $departamentoId): JsonResponse
    {
        try {
            $ciudades = Ciudad::where('id_departamento', $departamentoId)
                ->select('id', 'nombre', 'codigo', 'id_departamento')
                ->orderBy('nombre', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $ciudades,
                'message' => 'Ciudades del departamento obtenidas exitosamente',
                'total' => $ciudades->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las ciudades del departamento: ' . $e->getMessage()
            ], 500);
        }
    }
}
