<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRolRequest;
use App\Http\Requests\UpdateRolRequest;
use App\Http\Resources\RolPermisosResource;
use App\Http\Resources\RolResource;
use App\Services\RolService;
use App\Models\Rol;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Http\Request;

class RolController extends Controller
{

    private $rolService;

    public function __construct(RolService $rolService)
    {
        $this->rolService = $rolService;
    }

    public function index()
    {
        try {
            $roles = $this->rolService->getAll();

            return response()->json(
                [
                    'status' => 'success',
                    'data' => RolResource::collection($roles),
                ],
                200,
            );
        } catch (Exception $e) {
            Log::error('Error al consultar roles', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al obtener los roles',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function indexPermisos(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            // $search = $request->input('page', 1);
            $roles = $this->rolService->getAllWithPermisos($perPage);

            return RolPermisosResource::collection($roles);
        } catch (Exception $e) {
            Log::error('Error al consultar roles con permisos', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al obtener los roles con permisos',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreRolRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRolRequest $request)
    {
        try {
            // Log para depurar los datos que llegan
            Log::info('Datos recibidos para crear rol', [
                'raw_data' => $request->all(),
                'validated_data' => $request->validated(),
                'usuario_id' => Auth::id() ?? 'no autenticado',
            ]);

            $rol = $this->rolService->create($request->validated());

            return response()->json(
                [
                    'status' => 'success',
                    'data' => new RolResource($rol),
                ],
                201,
            );
        } catch (Exception $e) {
            Log::error('Error al crear rol', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al crear el rol',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }


    public function show(Rol $rol)
    {
        //
    }


    public function edit(Rol $rol)
    {
        //
    }

    public function update(UpdateRolRequest $request, string $idRol)
    {
        try {
            $data = $request->validated();

            // Log para depurar los datos que llegan
            Log::info('Datos recibidos para actualizar rol', [
                'raw_data' => $request->all(),
                'validated_data' => $data,
                'rol_id' => $idRol,
                'usuario_id' => Auth::id() ?? 'no autenticado',
            ]);

            $rol = $this->rolService->update($idRol, $data);

            return response()->json(
                [
                    'status' => 'success',
                    'message' => 'Rol actualizado correctamente',
                    'data' => new RolResource($rol),
                ],
                200,
            );
        } catch (Exception $e) {
            Log::error('Error al actualizar rol', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al actualizar el rol',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Rol  $rol
     * @return \Illuminate\Http\Response
     */
    public function destroy(Rol $rol)
    {
        //
    }

    public function getPermisosRol(int $idRol)
    {
        try {
            $permisosRol = $this->rolService->getPermisosRol($idRol);

            return response()->json([
                'status' => 'success',
                'data' => $permisosRol
            ], 200);
        } catch (Exception $e) {
            Log::error('Error al obtener permisos de rol', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'id_rol' => $idRol,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener los permisos del rol',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Asigna permisos a un rol específico
     */
    public function asignarPermisosRol(Request $request, int $idRol)
    {
        try {
            $request->validate([
                'permisos' => 'required|array',
                'permisos.*.id_permiso' => 'required|integer|exists:permisos,id_permiso',
                'permisos.*.concedido' => 'required|boolean'
            ]);

            $rol = $this->rolService->asignarPermisos($idRol, $request->permisos);

            return response()->json([
                'status' => 'success',
                'message' => 'Permisos asignados correctamente al rol',
                'data' => [
                    'id_rol' => $rol->id_rol,
                    'nombre' => $rol->nombre,
                    'permisos_count' => $rol->permisos->count(),
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error('Error al asignar permisos a rol', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'id_rol' => $idRol,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al asignar permisos al rol',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
