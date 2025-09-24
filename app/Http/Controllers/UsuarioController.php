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
use App\Http\Requests\CambiarPasswordRequest;
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

    public function store(StoreUsuarioRequest $request)
    {
        try {
            // $this->authorize('crear', Usuario::class);

            $data = $request->validated();

            // Creación desde formulario público: no dashboard, no SSO
            $usuario = $this->usuarioService->create($data, false, false);

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
            // Creación desde dashboard: dashboard=true, no SSO
            $usuario = $this->usuarioService->create($data, true, false);

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

    public function update(UpdateUsuarioRequest $request, Usuario $usuario)
    {
        try {
            // $this->authorize('actualizar', $usuario);

            $data = $request->validated();

            $usuarioActualizado = $this->usuarioService->update(
                $usuario->email,
                $data,
            );

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
            $data = $request->validated();
            $data['__desde_dashboard'] = true;
            $usuarioActualizado = $this->usuarioService->update(
                $usuario->id_usuario,
                $data,
            );

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

    public function jugadores(Request $request)
    {
        try {
            $termino = $request->query('term', '');

            $tiposUsuario = Auth::user()->tipos_usuario;
            $esEgresado = false;
            if ($tiposUsuario instanceof \Illuminate\Support\Collection) {
                $esEgresado = $tiposUsuario->contains('egresado');
            } elseif (is_array($tiposUsuario)) {
                $esEgresado = in_array('egresado', $tiposUsuario, true);
            }

            $permiteExternos = filter_var(
                $request->query('permiteExternos', false),
                FILTER_VALIDATE_BOOLEAN
            ) && $esEgresado;

            $jugadores = $this->usuarioService->buscarJugadores($termino, $permiteExternos);

            return response()->json([
                'status' => 'success',
                'data' => UsuariosResource::collection($jugadores),
                'message' => 'Jugadores obtenidos correctamente',
            ], 200);
        } catch (Exception $e) {
            Log::error('Error al consultar jugadores', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener los jugadores',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function cambiarPassword(CambiarPasswordRequest $request)
    {
        try {
            $data = $request->validated();
            $usuarioId = Auth::id();

            $usuario = $this->usuarioService->cambiarPassword($usuarioId, $data['newPassword']);

            return response()->json([
                'status' => 'success',
                'message' => 'Contraseña cambiada correctamente',
            ], 200);
        } catch (UsuarioException $e) {
            Log::error('Error al cambiar contraseña desde el controlador', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'error_type' => $e->getErrorType(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $e->render();
        } catch (Exception $e) {
            Log::error('Error al cambiar contraseña desde el controlador', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al cambiar la contraseña',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function validarCamposFacturacion($id)
    {
        try {
            $usuario = Usuario::with(['persona.personasFacturacion'])->findOrFail($id);

            $puede_pagar = true;

            $persona = $usuario->persona;
            if ($persona && $persona->relationLoaded('personasFacturacion')) {
                $persona = $persona->personasFacturacion->first() ?? $persona;
            }

            if (!$persona) {
                $puede_pagar = false;
            }

            if ($persona && (!$persona->tipo_documento_id || !$persona->numero_documento)) {
                $puede_pagar = false;
            }

            if ($persona && (!$persona->direccion || !$persona->ciudad_residencia_id)) {
                $puede_pagar = false;
            }

            if ($persona && (!$persona->regimen_tributario_id || !$persona->ciudad_expedicion_id)) {
                $puede_pagar = false;
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'usuario' => $usuario,
                    'persona_usada_para_facturacion' => $persona,
                    'puede_pagar' => $puede_pagar,
                ],
                'message' => 'Campos de facturación validados correctamente',
            ], 200);
        } catch (UsuarioException $e) {
            Log::error('Error al validar campos de facturación', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            Log::error('Error al validar campos de facturación', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al validar los campos de facturación',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function validarTerminosCondiciones()
    {
        try {
            $usuario = Auth::user();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'terminos_condiciones' => $usuario->terminos_condiciones,
                ],
                'message' => 'Términos y condiciones validados correctamente',
            ], 200);
        } catch (UsuarioException $e) {
            Log::error('Error al validar términos y condiciones', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            Log::error('Error al validar términos y condiciones', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al validar los términos y condiciones',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function terminosCondiciones()
    {
        try {
            $usuario = Usuario::findOrFail(Auth::id());

            $usuario->terminos_condiciones = true;
            $usuario->save();

            return response()->json([
                'status' => 'success',
                'data' => $usuario->terminos_condiciones,
                'message' => 'Términos y condiciones aceptados correctamente',
            ], 200);
        } catch (Exception $e) {
            Log::error('Error al obtener términos y condiciones', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener los términos y condiciones',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function validarPerfilCompleto()
    {
        try {
            $es_completo = $this->usuarioService->verificarPerfilCompleto(Auth::id());

            return response()->json([
                'status' => 'success',
                'data' => [
                    'perfil_completo' => $es_completo,
                ],
                'message' => 'Perfil completo validado correctamente',
            ], 200);
        } catch (UsuarioException $e) {
            Log::error('Error al validar perfil completo', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            Log::error('Error al validar perfil completo', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al validar el perfil completo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
