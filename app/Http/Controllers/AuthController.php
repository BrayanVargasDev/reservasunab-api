<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    private $reglasDeValidacionDeRegistro = [
        'nombre' => 'required|string|max:255',
        'apellido' => 'required|string|max:255',
        'celular' => 'required|string|max:10',
        'email' => 'required|string|email|max:255|unique:usuarios',
        'password' => 'required|string|min:8',
    ];

    private $reglasDeValidacionDeLogin = [
        'email' => 'required|string|email|max:255',
        'password' => 'required|string|min:8',
    ];

    private $mensajesDeValidacion = [
        'email.required' => 'El campo email es obligatorio.',
        'email.email' => 'El campo email debe ser una dirección de correo electrónico válida.',
        'email.max' => 'El campo email no puede tener más de 255 caracteres.',
        'email.unique' => 'El email ya está registrado.',
        'password.required' => 'El campo contraseña es obligatorio.',
        'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
    ];

    public function registrar(Request $request)
    {
        $validarUsuario = Validator::make($request->all(), $this->reglasDeValidacionDeRegistro, $this->mensajesDeValidacion);

        if ($validarUsuario->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validarUsuario->errors(),
                'message' => 'Ocurrió un error en la validación de los datos.',
            ], 409);
        }

        $usuarioExistente = Usuario::where('email', $request->email)->first();

        if ($usuarioExistente) {
            return response()->json([
                'status' => 'error',
                'message' => 'El correo electrónico ya está registrado.',
            ], 409);
        }

        try {
            DB::beginTransaction();

            $usuario = Usuario::create([
                'email' => $request->email,
                'password_hash' => bcrypt($request->password),
                'tipo_usuario' => 'externo',
                'activo' => true,
            ]);

            Log::debug($usuario);

            if (!$usuario) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ocurrió un error al registrar el usuario.',
                ], 409);
            }
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Usuario registrado correctamente.',
                'data' => [
                    'id' => $usuario->id_usuario,
                    'email' => $usuario->email,
                    'nombre' => $request->nombre,
                    'apellido' => $request->apellido,
                    'celular' => $request->celular,
                    'tipo_usuario' => $usuario->tipo_usuario,
                    'activo' => $usuario->activo,
                    'token' => $usuario->createToken('auth-token')->plainTextToken,
                ],
            ], 201);
        } catch (Exception $th) {
            DB::rollBack();

            Log::error('Error al registrar el usuario', [
                'error' => $th->getMessage(),
                'archivo' => $th->getFile(),
                'linea' => $th->getLine(),
                'trace' => $th->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al registrar el usuario.',
                'error' => $th->getMessage(),
            ], 409);
        }
    }

    public function login()
    {
        try {
            $validarUsuario = Validator::make(request()->all(), $this->reglasDeValidacionDeLogin, $this->mensajesDeValidacion);

            if ($validarUsuario->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validarUsuario->errors(),
                    'message' => 'El usuario o la contraseña son incorrectos.',
                ], 409);
            }

            $email = request('email');
            $password = request('password');

            $usuario = Usuario::where('email', $email)->first();

            if (!$usuario || !password_verify($password, $usuario->password_hash)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El usuario o la contraseña son incorrectos.',
                ], 401);
            }

            $token = $usuario->createToken('auth-token', ['*'], now()->addHour());

            return response()->json([
                'status' => 'success',
                'data' => [
                    'token' => $token->plainTextToken,
                    'id' => $usuario->id_usuario,
                    'email' => $usuario->email,
                    'nombre' => $usuario->persona->nombre ?? null,
                    'apellido' => $usuario->persona->apellido ?? null,
                    'tipo_usuario' => $usuario->tipo_usuario,
                    'activo' => $usuario->activo,
                    'token_expires_at' => $token->accessToken->expires_at,
                    'permisos' => $usuario->obtenerTodosLosPermisos()->pluck('codigo'),
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error('Error inesperado en el proceso de login', [
                'error' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ha ocurrido un error inesperado. Por favor, intente nuevamente más tarde.',
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $usuario = $request->user();

            if ($usuario) {
                $usuario->tokens()->delete();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Sesión cerrada correctamente.',
            ], 200);
        } catch (Exception $e) {
            Log::error('Error al cerrar sesión', [
                'error' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al cerrar sesión.',
            ], 500);
        }
    }

    public function user(Request $request)
    {
        try {
            $usuario = $request->user();

            if (!$usuario) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado.',
                ], 401);
            }

            $usuario->load(['persona', 'rol']);

            $currentToken = $usuario->currentAccessToken();
            $refreshThreshold = 15;

            $shouldRefreshToken = false;
            if (
                $currentToken && $currentToken->expires_at &&
                $currentToken->expires_at->subMinutes($refreshThreshold)->isPast()
            ) {
                $shouldRefreshToken = true;
            }

            $data = [
                'id' => $usuario->id_usuario,
                'email' => $usuario->email,
                'nombre' => $usuario->persona->nombre ?? null,
                'apellido' => $usuario->persona->apellido ?? null,
                'tipo_usuario' => $usuario->tipo_usuario,
                'activo' => $usuario->activo,
                'token_expires_at' => $currentToken?->expires_at,
                'permisos' => $usuario->obtenerTodosLosPermisos()->pluck('codigo'),
            ];

            if ($shouldRefreshToken) {
                $currentToken->delete();

                $expirationMinutes = config('sanctum.expiration', 1440);
                $tokenName = 'auth-token-' . now()->format('Y-m-d-H-i-s');

                $newToken = $usuario->createToken(
                    $tokenName,
                    ['*'],
                    now()->addMinutes($expirationMinutes)
                );

                $data['token'] = $newToken->plainTextToken;
                $data['token_expires_at'] = $newToken->accessToken->expires_at;

                return response()->json([
                    'status' => 'success',
                    'message' => 'Token refrescado correctamente.',
                    'data' => $data
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Usuario autenticado correctamente.',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            Log::error('Error al obtener datos del usuario', [
                'error' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener datos del usuario.',
            ], 500);
        }
    }

    public function refreshToken(Request $request)
    {
        try {
            $usuario = $request->user();

            if (!$usuario) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado.',
                ], 401);
            }

            $currentToken = $usuario->currentAccessToken();

            if ($currentToken) {
                $currentToken->delete();
            }

            $expirationMinutes = config('sanctum.expiration', 1440);
            $tokenName = 'auth-token-' . now()->format('Y-m-d-H-i-s');

            $newToken = $usuario->createToken(
                $tokenName,
                ['*'],
                now()->addMinutes($expirationMinutes)
            );

            $usuario->load(['persona', 'rol']);

            return response()->json([
                'status' => 'success',
                'message' => 'Token refrescado correctamente.',
                'data' => [
                    'id' => $usuario->id_usuario,
                    'email' => $usuario->email,
                    'nombre' => $usuario->persona->nombre ?? null,
                    'apellido' => $usuario->persona->apellido ?? null,
                    'tipo_usuario' => $usuario->tipo_usuario,
                    'activo' => $usuario->activo,
                    'token' => $newToken->plainTextToken,
                    'token_expires_at' => $newToken->accessToken->expires_at,
                    'permisos' => $usuario->obtenerTodosLosPermisos()->pluck('codigo'),
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error('Error al refrescar token', [
                'error' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al refrescar token.',
            ], 500);
        }
    }
}
