<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePermisoRequest;
use App\Http\Requests\UpdatePermisoRequest;
use App\Http\Resources\PermisoResource;
use App\Models\Permiso;
use App\Services\PermisoService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class PermisoController extends Controller
{

    private $permisoService;

    public function __construct(PermisoService $permisoService)
    {
        $this->permisoService = $permisoService;
    }

    public function index(Request $request)
    {
        try {

            $per_page = $request->query('per_page', 10);
            $search = $request->query('search', '');

            $permisos = $this->permisoService->getAll($per_page, $search);
            return PermisoResource::collection($permisos);
        } catch (Exception $e) {
            Log::error('Error al obtener permisos', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener los permisos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StorePermisoRequest $request)
    {
        try {
            $data = $request->validated();
            $response = $this->permisoService->store($data);

            return response()->json($response, 201);
        } catch (Exception $e) {
            Log::error('Error al crear permiso(s)', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al crear el/los permiso(s)',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Permiso $permiso)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Permiso  $permiso
     * @return \Illuminate\Http\Response
     */
    public function edit(Permiso $permiso)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdatePermisoRequest  $request
     * @param  \App\Models\Permiso  $permiso
     * @return \Illuminate\Http\Response
     */
    public function update(UpdatePermisoRequest $request, Permiso $permiso)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Permiso  $permiso
     * @return \Illuminate\Http\Response
     */
    public function destroy(Permiso $permiso)
    {
        //
    }

    /**
     * Obtiene todos los permisos con su estado (concedido/denegado) para un usuario específico
     */
    public function getPermisosUsuario(Request $request, int $idUsuario)
    {
        try {
            $permisosUsuario = $this->permisoService->getPermisosConEstadoParaUsuario($idUsuario);

            return response()->json([
                'status' => 'success',
                'data' => $permisosUsuario
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener permisos de usuario', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'id_usuario_consultado' => $idUsuario,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener los permisos del usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Asigna permisos directos a un usuario específico
     */
    public function asignarPermisosUsuario(Request $request, int $idUsuario)
    {
        try {
            $request->validate([
                'permisos' => 'required|array',
                'permisos.*.id_permiso' => 'required|integer|exists:permisos,id_permiso',
                'permisos.*.concedido' => 'required|boolean'
            ]);

            $usuario = $this->permisoService->asignarPermisosUsuario($idUsuario, $request->permisos);

            return response()->json([
                'status' => 'success',
                'message' => 'Permisos asignados correctamente al usuario',
                'data' => [
                    'id_usuario' => $usuario->id_usuario,
                    'permisos_directos_count' => $usuario->permisosDirectos->count(),
                    'permisos_totales_count' => $usuario->obtenerTodosLosPermisos()->count(),
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al asignar permisos a usuario', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'id_usuario_destino' => $idUsuario,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al asignar permisos al usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
