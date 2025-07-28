<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegimenTributarioRequest;
use App\Http\Requests\UpdateRegimenTributarioRequest;
use App\Http\Resources\RegimenTributarioResource;
use App\Models\RegimenTributario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegimenTributarioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 50);
            $search = $request->get('search', '');

            $regimenes = RegimenTributario::when($search, function ($query) use ($search) {
                $query->whereRaw('LOWER(nombre) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(descripcion) LIKE ?', ['%' . strtolower($search) . '%']);
            })
                ->orderBy('nombre')
                ->paginate($perPage);

            return RegimenTributarioResource::collection($regimenes);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los regímenes tributarios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRegimenTributarioRequest $request): JsonResponse
    {
        try {
            $regimen = RegimenTributario::create($request->validated());

            return response()->json([
                'success' => true,
                'data' => $regimen,
                'message' => 'Régimen tributario creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el régimen tributario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(RegimenTributario $regimenTributario): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $regimenTributario,
            'message' => 'Régimen tributario obtenido exitosamente'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRegimenTributarioRequest $request, RegimenTributario $regimenTributario): JsonResponse
    {
        try {
            $regimenTributario->update($request->validated());

            return response()->json([
                'success' => true,
                'data' => $regimenTributario,
                'message' => 'Régimen tributario actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el régimen tributario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RegimenTributario $regimenTributario): JsonResponse
    {
        try {
            // Verificar si hay personas asociadas
            if ($regimenTributario->personas()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el régimen tributario porque tiene personas asociadas'
                ], 400);
            }

            $regimenTributario->delete();

            return response()->json([
                'success' => true,
                'message' => 'Régimen tributario eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el régimen tributario: ' . $e->getMessage()
            ], 500);
        }
    }
}
