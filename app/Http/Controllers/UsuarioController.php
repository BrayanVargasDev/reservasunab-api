<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Http\Resources\UsuariosResource;
use App\Models\Usuario;
use App\Services\UsuarioService;
use App\Exceptions\UsuarioException;
use App\Http\Requests\StoreUsuarioDashboardRequest;
use App\Http\Requests\UpdateUsuarioDashboardRequest;
use App\Http\Requests\UpdateUsuarioPermisosRequest;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UsuarioController extends Controller
{
    private $usuarioService;

    public function __construct(UsuarioService $usuarioService)
    {
        $this->usuarioService = $usuarioService;
    }

    public function index(Request $request)
    {
        try {
            // $this->authorize('verTodos', Usuario::class);

            $perPage = $request->query('per_page', 10);
            $search = $request->query('search', '');

            $usuarios = $this->usuarioService->getAll($perPage, $search);
            return UsuariosResource::collection(
                $usuarios,
            );
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

    /**
     * Almacena un nuevo usuario
     *
     * @param  \App\Http\Requests\StoreUsuarioRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreUsuarioRequest $request)
    {
        try {
            // $this->authorize('crear', Usuario::class);

            $data = $request->validated();

            $usuario = $this->usuarioService->create($data);

            Log::info('Usuario creado', [
                'usuario_id' => Auth::id(),
                'usuario_creado_id' => $usuario->id_usuario,
            ]);

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $usuario,
                    'message' => 'Usuario creado correctamente',
                ],
                201,
            );
        } catch (UsuarioException $e) {
            Log::error('Error al crear usuario', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'error_type' => $e->getErrorType(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $e->render();
        } catch (Exception $e) {
            Log::error('Error al crear usuario', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al crear el usuario',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function storeFromDashboard(StoreUsuarioDashboardRequest $request)
    {
        try {
            // $this->authorize('crearDesdeDashboard', Usuario::class);

            $data = $request->validated();
            $usuario = $this->usuarioService->create($data, true);

            Log::info('Usuario creado desde el dashboard', [
                'usuario_id' => Auth::id(),
                'usuario_creado_id' => $usuario->id_usuario,
            ]);

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $usuario,
                    'message' => 'Usuario creado correctamente desde el dashboard',
                ],
                201,
            );
        } catch (UsuarioException $e) {
            Log::error('Error al crear usuario desde el dashboard', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'error_type' => $e->getErrorType(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $e->render();
        } catch (Exception $e) {
            Log::error('Error al crear usuario desde el dashboard', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' =>
                    'Ocurrió un error al crear el usuario desde el dashboard',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Muestra los detalles de un usuario específico
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $usuario = $this->usuarioService->getById($id);
            // $this->authorize('ver', $usuario);

            return response()->json(
                [
                    'status' => 'success',
                    'data' => new UsuariosResource($usuario),
                    'message' => 'Usuario obtenido correctamente',
                ],
                200,
            );
        } catch (UsuarioException $e) {
            Log::warning('Problema al obtener usuario', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'usuario_buscado_id' => $id,
                'error_type' => $e->getErrorType(),
                'error' => $e->getMessage(),
            ]);

            return $e->render();
        } catch (Exception $e) {
            Log::error('Error al consultar usuario', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'usuario_buscado_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al obtener el usuario',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function validarEmailTomado($email)
    {
        try {
            $usuario = $this->usuarioService->getByEmail($email);

            if (!empty($usuario)) {
                return response()->json(
                    [
                        'status' => 'error',
                        'data' => false,
                        'message' => 'El correo electrónico ya está en uso',
                    ],
                    400,
                );
            }

            return response()->json(
                [
                    'status' => 'success',
                    'data' => true,
                    'message' => 'El correo electrónico está disponible',
                ],
                200,
            );
        } catch (Exception $e) {
            Log::error('Error al validar email', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al validar el correo electrónico',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Actualiza un usuario específico
     *
     * @param  \App\Http\Requests\UpdateUsuarioRequest  $request
     * @param  \App\Models\Usuario  $usuario
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateUsuarioRequest $request, Usuario $usuario)
    {
        try {
            // $this->authorize('actualizar', $usuario);

            $data = $request->validated();

            $usuarioActualizado = $this->usuarioService->update(
                $usuario->email,
                $data,
            );

            Log::info('Usuario actualizado', [
                'usuario_id' => Auth::id(),
                'usuario_actualizado_id' => $usuario->id_usuario,
            ]);

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $usuarioActualizado,
                    'message' => 'Usuario actualizado correctamente',
                ],
                200,
            );
        } catch (UsuarioException $e) {
            Log::warning('Problema al actualizar usuario', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'usuario_id' => $usuario->id_usuario,
                'error_type' => $e->getErrorType(),
                'error' => $e->getMessage(),
            ]);

            return $e->render();
        } catch (Exception $e) {
            Log::error('Error al actualizar usuario', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'usuario_id' => $usuario->id_usuario,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al actualizar el usuario',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function updateFromDashboard(
        UpdateUsuarioDashboardRequest $request,
        Usuario $usuario,
    ) {
        try {
            // $this->authorize('actualizarDesdeDashboard', $usuario);
            $data = $request->validated();

            $usuarioActualizado = $this->usuarioService->update(
                $usuario->id_usuario,
                $data,
            );

            Log::info('Usuario actualizado desde el dashboard', [
                'usuario_id' => Auth::id(),
                'usuario_actualizado_id' => $usuario->id_usuario,
            ]);

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $usuarioActualizado,
                    'message' =>
                    'Usuario actualizado correctamente desde el dashboard',
                ],
                200,
            );
        } catch (UsuarioException $e) {
            Log::warning('Problema al actualizar usuario desde el dashboard', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'usuario_id' => $usuario->id_usuario,
                'error_type' => $e->getErrorType(),
                'error' => $e->getMessage(),
            ]);

            return $e->render();
        } catch (Exception $e) {
            Log::error('Error al actualizar usuario desde el dashboard', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'usuario_id' => $usuario->id_usuario,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' =>
                    'Ocurrió un error al actualizar el usuario desde el dashboard',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Elimina un usuario (softDelete)
     *
     * @param  \App\Models\Usuario  $usuario
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Usuario $usuario)
    {
        try {
            // $this->authorize('eliminar', $usuario);

            $usuario_id = $usuario->id_usuario;
            if (
                !is_numeric($usuario_id) ||
                intval($usuario_id) != $usuario_id
            ) {
                throw new UsuarioException(
                    'El ID del usuario debe ser un número entero',
                    'invalid_id_format',
                    400,
                );
            }

            $this->usuarioService->delete($usuario_id);

            Log::info('Usuario eliminado (softDelete)', [
                'usuario_id' => Auth::id(),
                'usuario_eliminado_id' => $usuario_id,
            ]);

            return response()->json(
                [
                    'status' => 'success',
                    'message' => 'Usuario eliminado correctamente',
                ],
                200,
            );
        } catch (UsuarioException $e) {
            Log::warning('Problema al eliminar usuario', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'usuario_id' => $usuario->id_usuario,
                'error_type' => $e->getErrorType(),
                'error' => $e->getMessage(),
            ]);

            return $e->render();
        } catch (Exception $e) {
            Log::error('Error al eliminar usuario', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'usuario_id' => $usuario->id_usuario,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al eliminar el usuario',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Restaura un usuario eliminado previamente
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore($id)
    {
        try {
            if (!is_numeric($id) || intval($id) != $id) {
                throw new UsuarioException(
                    'El ID del usuario debe ser un número entero',
                    'invalid_id_format',
                    400,
                );
            }

            $usuario = Usuario::withTrashed()->findOrFail($id);

            // $this->authorize('restaurar', $usuario);

            $usuario = $this->usuarioService->restore($id);

            Log::info('Usuario restaurado', [
                'usuario_id' => Auth::id(),
                'usuario_restaurado_id' => $id,
            ]);

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $usuario,
                    'message' => 'Usuario restaurado correctamente',
                ],
                200,
            );
        } catch (UsuarioException $e) {
            Log::warning('Problema al restaurar usuario', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'usuario_buscado_id' => $id,
                'error_type' => $e->getErrorType(),
                'error' => $e->getMessage(),
            ]);

            return $e->render();
        } catch (Exception $e) {
            Log::error('Error al restaurar usuario', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'usuario_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al restaurar el usuario',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }


    public function trashed(Request $request)
    {
        try {
            // $this->authorize('verEliminados', Usuario::class);
            $perPage = $request->query('per_page', 10);
            $usuarios = $this->usuarioService->getTrashed($perPage);

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $usuarios,
                    'message' => 'Usuarios eliminados obtenidos correctamente',
                ],
                200,
            );
        } catch (Exception $e) {
            Log::error('Error al consultar usuarios eliminados', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' =>
                    'Ocurrió un error al obtener los usuarios eliminados',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function updatePermisos(UpdateUsuarioPermisosRequest $request, string $idUsuario)
    {
        try {
            $data = $request->validated();

            Log::info('Actualizando permisos de usuario', [
                'id_usuario' => $idUsuario,
                'permisos_count' => count($data['permisos']),
                'usuario_id' => Auth::id() ?? 'no autenticado',
            ]);

            $usuario = $this->usuarioService->actualizarPermisos($idUsuario, $data['permisos']);

            return response()->json([
                'status' => 'success',
                'message' => 'Permisos del usuario actualizados correctamente',
                'data' => [
                    'id_usuario' => $usuario->id_usuario,
                    'email' => $usuario->email,
                    'permisos_directos_count' => $usuario->permisosDirectos ? $usuario->permisosDirectos->count() : 0,
                    'permisos_totales_count' => $usuario->obtenerTodosLosPermisos()->count(),
                ]
            ], 200);
        } catch (UsuarioException $e) {
            Log::warning('Error de negocio al actualizar permisos', [
                'id_usuario' => $idUsuario,
                'error' => $e->getMessage(),
                'codigo' => $e->getCode(),
                'usuario_id' => Auth::id() ?? 'no autenticado',
            ]);

            return response()->json([
                'status' => 'error',
            ], $e->getCode());
        } catch (Exception $e) {
            Log::error('Error al actualizar permisos del usuario', [
                'id_usuario' => $idUsuario,
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al actualizar los permisos del usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
