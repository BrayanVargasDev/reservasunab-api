<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEspacioNovedadRequest;
use App\Http\Requests\UpdateEspacioNovedadRequest;
use App\Http\Resources\EspacioNovedadResource;
use App\Models\EspacioNovedad;
use Carbon\Carbon;
// use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EspacioNovedadController extends Controller
{
    // use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            // $this->authorize('viewAny', EspacioNovedad::class);

            $query = EspacioNovedad::with('espacio');

            $query->where('fecha', '>=', Carbon::today())
                ->where('id_espacio', (int) $request->get('id_espacio'));
            $query->withTrashed();

            $novedades = $query->orderBy('fecha', 'asc')->paginate($request->get('per_page', 10));

            return EspacioNovedadResource::collection($novedades);
        } catch (\Exception $e) {
            Log::error('Error al obtener novedades de espacios', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las novedades de espacios'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEspacioNovedadRequest $request): JsonResponse
    {
        try {
            // $this->authorize('create', EspacioNovedad::class);

            $data = $request->validated();
            $data['creado_por'] = Auth::id();

            if (!isset($data['fecha_fin']) || empty($data['fecha_fin'])) {
                $data['fecha_fin'] = $data['fecha_inicio'];
            }

            $data['fecha'] = $data['fecha_inicio'];
            unset($data['fecha_inicio']);

            $novedad = EspacioNovedad::create($data);
            $novedad->load('espacio');

            return response()->json([
                'success' => true,
                'message' => 'Novedad creada exitosamente',
                'data' => new EspacioNovedadResource($novedad)
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear novedad de espacio', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'usuario_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la novedad'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $novedad = EspacioNovedad::with('espacio')->findOrFail($id);
            // $this->authorize('view', $novedad);

            return response()->json([
                'success' => true,
                'data' => new EspacioNovedadResource($novedad)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Novedad no encontrada'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEspacioNovedadRequest $request, string $id): JsonResponse
    {
        try {
            $novedad = EspacioNovedad::findOrFail($id);
            // $this->authorize('update', $novedad);

            $data = $request->validated();
            $data['actualizado_por'] = Auth::id();

            if (isset($data['fecha']) && (!isset($data['fecha_fin']) || empty($data['fecha_fin']))) {
                $data['fecha_fin'] = $data['fecha'];
            }

            $novedad->update($data);
            $novedad->load('espacio');

            Log::info('Novedad de espacio actualizada', [
                'novedad_id' => $novedad->id,
                'espacio_id' => $novedad->id_espacio,
                'usuario_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Novedad actualizada exitosamente',
                'data' => new EspacioNovedadResource($novedad)
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar novedad de espacio', [
                'error' => $e->getMessage(),
                'novedad_id' => $id,
                'data' => $request->all(),
                'usuario_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la novedad'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $novedad = EspacioNovedad::findOrFail($id);
            // $this->authorize('delete', $novedad);

            // Actualizar el campo eliminado_por antes del soft delete
            $novedad->update(['eliminado_por' => Auth::id()]);
            $novedad->delete();

            Log::info('Novedad de espacio eliminada (soft delete)', [
                'novedad_id' => $id,
                'espacio_id' => $novedad->id_espacio,
                'usuario_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Novedad eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar novedad de espacio', [
                'error' => $e->getMessage(),
                'novedad_id' => $id,
                'usuario_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la novedad'
            ], 500);
        }
    }

    /**
     * Restore a soft deleted resource.
     */
    public function restore($id): JsonResponse
    {
        try {
            $novedad = EspacioNovedad::withTrashed()->findOrFail($id);
            // $this->authorize('restore', $novedad);

            if (!$novedad->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La novedad no está eliminada'
                ], 400);
            }

            $novedad->restore();
            $novedad->update(['eliminado_por' => null]);
            $novedad->load('espacio');

            Log::info('Novedad de espacio restaurada', [
                'novedad_id' => $id,
                'espacio_id' => $novedad->id_espacio,
                'usuario_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Novedad restaurada exitosamente',
                'data' => new EspacioNovedadResource($novedad)
            ]);
        } catch (\Exception $e) {
            Log::error('Error al restaurar novedad de espacio', [
                'error' => $e->getMessage(),
                'novedad_id' => $id,
                'usuario_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al restaurar la novedad'
            ], 500);
        }
    }

    /**
     * Force delete a resource (permanent deletion).
     */
    public function forceDelete(string $id): JsonResponse
    {
        try {
            $novedad = EspacioNovedad::withTrashed()->findOrFail($id);
            // $this->authorize('forceDelete', $novedad);

            Log::info('Novedad de espacio eliminada permanentemente', [
                'novedad_id' => $id,
                'espacio_id' => $novedad->id_espacio,
                'usuario_id' => Auth::id()
            ]);

            $novedad->forceDelete();

            return response()->json([
                'success' => true,
                'message' => 'Novedad eliminada permanentemente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar permanentemente novedad de espacio', [
                'error' => $e->getMessage(),
                'novedad_id' => $id,
                'usuario_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar permanentemente la novedad'
            ], 500);
        }
    }
}
