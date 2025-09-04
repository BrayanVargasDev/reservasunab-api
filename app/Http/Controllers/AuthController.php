<?php

namespace App\Http\Controllers;

use App\Models\AuthCode;
use App\Models\Permiso;
use App\Models\Persona;
use App\Models\RefreshToken;
use App\Models\Usuario;
use App\Services\TokenService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    private $reglasDeValidacionDeRegistro = [
        'nombre' => 'required|string|max:255',
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

    private $token_service;
    private $unab_host = null;
    private $unab_endpoint = null;
    private $usuario_unab = null;
    private $password_unab = null;
    private $tarea = null;


    private function loadUnabConfig(): bool
    {
        // Advertir si la configuración está en caché
        if (function_exists('app') && app()->configurationIsCached()) {
            Log::debug('Configuración de Laravel está en caché (config:cache activo).');
        }

        $this->unab_host = config('app.unab_host');
        $this->unab_endpoint = config('app.unab_endpoint');
        $this->usuario_unab = config('app.unab_usuario');
        $this->password_unab = config('app.unab_password');
        $this->tarea = config('app.unab_tarea');

        $missing = [];
        if (empty($this->unab_host)) $missing[] = 'UNAB_HOST (app.unab_host)';
        if (empty($this->unab_endpoint)) $missing[] = 'UNAB_ENDPOINT (app.unab_endpoint)';
        if (empty($this->usuario_unab)) $missing[] = 'UNAB_USUARIO (app.unab_usuario)';
        if ($this->password_unab === null || $this->password_unab === '') $missing[] = 'UNAB_PASSWORD (app.unab_password)';
        if ($this->tarea === null || $this->tarea === '') $missing[] = 'UNAB_TAREA (app.unab_tarea)';

        if (!empty($missing)) {
            Log::error('Faltan variables de entorno/configuración UNAB', [
                'faltantes' => $missing,
            ]);
            return false;
        }

        return true;
    }

    public function __construct(TokenService $tokenService)
    {
        $this->token_service = $tokenService;
    }

    private function procesarNombreCompleto(array $palabrasNombre)
    {
        $cantidadPalabras = count($palabrasNombre);

        switch ($cantidadPalabras) {
            case 0:
                return ['nombre' => '', 'apellido' => ''];
            case 1:
                return ['nombre' => $palabrasNombre[0], 'apellido' => ''];
            case 2:
                return [
                    'nombre' => $palabrasNombre[0],
                    'apellido' => $palabrasNombre[1]
                ];
            case 3:
                return [
                    'nombre' => $palabrasNombre[0],
                    'apellido' => $palabrasNombre[1] . ' ' . $palabrasNombre[2]
                ];
            case 4:
                return [
                    'nombre' => $palabrasNombre[0] . ' ' . $palabrasNombre[1],
                    'apellido' => $palabrasNombre[2] . ' ' . $palabrasNombre[3]
                ];
            case 5:
                return [
                    'nombre' => $palabrasNombre[0] . ' ' . $palabrasNombre[1] . ' ' . $palabrasNombre[2],
                    'apellido' => $palabrasNombre[3] . ' ' . $palabrasNombre[4]
                ];
            default:
                $nombre = implode(' ', array_slice($palabrasNombre, 0, 3));
                $apellido = implode(' ', array_slice($palabrasNombre, -2));
                return [
                    'nombre' => $nombre,
                    'apellido' => $apellido
                ];
        }
    }

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
                'tipos_usuario' => ['externo'],
                'activo' => true,
            ]);

            if (!$usuario) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ocurrió un error al registrar el usuario.',
                ], 409);
            }

            $palabrasNombre = array_filter(explode(' ', trim($request->nombre)));

            $datosNombre = $this->procesarNombreCompleto($palabrasNombre);

            $persona = Persona::create([
                'id_usuario' => $usuario->id_usuario,
                'primer_nombre' => explode(' ', $datosNombre['nombre'])[0] ?? '',
                'segundo_nombre' => implode(' ', array_slice(explode(' ', $datosNombre['nombre']), 1)) ?? '',
                'primer_apellido' => explode(' ', $datosNombre['apellido'])[0] ?? '',
                'segundo_apellido' => implode(' ', array_slice(explode(' ', $datosNombre['apellido']), 1)) ?? '',
                'celular' => $request->celular,
            ]);

            // Asignar el permiso de reservar a todos los usuarios nuevos
            $usuario->asignarPermisoReservar();

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Usuario registrado correctamente.',
                'data' => [
                    'id' => $usuario->id_usuario,
                    'email' => $usuario->email,
                    'nombre' => $datosNombre['nombre'],
                    'apellido' => $datosNombre['apellido'],
                    'celular' => $request->celular,
                    'tipo_usuario' => $usuario->tipos_usuario,
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

    public function login(Request $request)
    {
        try {
            $validarUsuario = Validator::make(
                $request->all(),
                $this->reglasDeValidacionDeLogin,
                $this->mensajesDeValidacion
            );

            if ($validarUsuario->fails()) {
                return response()->json([
                    'status'  => 'error',
                    'errors'  => $validarUsuario->errors(),
                    'message' => 'El usuario o la contraseña son incorrectos.',
                ], 409);
            }

            $email    = $request->input('email');
            $password = $request->input('password');
            $usuario  = Usuario::where('email', $email)->first();

            if (!$usuario || !password_verify($password, $usuario->password_hash)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'El usuario o la contraseña son incorrectos.',
                ], 401);
            }

            $dispositivo = $request->header('User-Agent');
            $ip          = $request->ip();

            $tiene_refresh_valido = RefreshToken::where('id_usuario', $usuario->id_usuario)
                ->where(function ($q) use ($dispositivo, $ip) {
                    $q->where(function ($qq) use ($dispositivo, $ip) {
                        $qq->where('dispositivo', $dispositivo)
                            ->where('ip', $ip);
                    })->orWhere(function ($qq) use ($dispositivo, $ip) {
                        $qq->where('dispositivo', $ip)
                            ->where('ip', $dispositivo);
                    });
                })
                ->where(function ($q) {
                    $q->whereNull('expira_en')
                        ->orWhere('expira_en', '>', now());
                })
                ->whereNull('revocado_en')
                ->first();

            $refresh_token = !$tiene_refresh_valido
                ? $this->token_service->crearRefreshTokenParaUsuario($usuario, $ip, $dispositivo)['raw']
                : $tiene_refresh_valido->token_hash;

            $token = $this->token_service->generarAccessToken($usuario);

            if ($this->loadUnabConfig()) {
                $this->consultarYActualizarTiposUsuario($usuario, $email);
            }

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'access_token'     => $token['token'],
                    'id'               => $usuario->id_usuario,
                    'email'            => $usuario->email,
                    'nombre'           => $usuario->persona->nombre ?? null,
                    'apellido'         => $usuario->persona->apellido ?? null,
                    'tipo_usuario'     => $usuario->tipos_usuario,
                    'activo'           => $usuario->activo,
                    'token_expires_at' => $token['expires_at'],
                    'refresh_token'    => $refresh_token,
                    'permisos'         => $usuario->obtenerTodosLosPermisos()->pluck('codigo'),
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error('Error inesperado en el proceso de login', [
                'error'   => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea'   => $e->getLine(),
                'trace'   => $e->getTraceAsString()
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Ha ocurrido un error inesperado. Por favor, intente nuevamente más tarde.',
            ], 500);
        }
    }

    private function consultarYActualizarTiposUsuario($usuario, string $email): void
    {
        try {
            $url = "https://{$this->unab_host}{$this->unab_endpoint}";

            $response = Http::timeout(30)
                ->connectTimeout(5)
                ->withBasicAuth($this->usuario_unab, $this->password_unab)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                    'Connection'   => 'keep-alive'
                ])
                ->post($url, [
                    'tarea'       => $this->tarea,
                    'correo_unab' => $email,
                ]);

            if ($response->failed()) {
                Log::error('Error en la comunicación con UNAB', [
                    'status' => $response->status(),
                    'body'   => $response->body()
                ]);
                return;
            }

            $usuarioEnUnab = $response->json();
            $datosUnab     = $usuarioEnUnab['datos'] ?? null;

            if (empty($datosUnab) || !is_array($datosUnab)) {
                Log::error('Datos de UNAB inválidos o vacíos', ['datos' => $datosUnab]);
                return;
            }

            $tipoMap = [
                'ESTUDIANTE' => 'estudiante',
                'EMPLEADO'   => 'administrativo',
                'EGRESADO'   => 'egresado',
            ];

            $tiposUsuario = [];
            foreach ($datosUnab as $entrada) {
                if (!is_array($entrada)) continue;
                $tipoUpper = strtoupper($entrada['tipo'] ?? '');
                if ($tipoUpper === '') continue;
                $tiposUsuario[] = $tipoMap[$tipoUpper] ?? 'externo';
            }

            $tiposUsuario = array_values(array_unique($tiposUsuario));
            if (empty($tiposUsuario)) {
                $tiposUsuario = ['externo'];
            }

            $this->actualizarTiposUsuario($usuario, $tiposUsuario);
        } catch (\Throwable $th) {
            Log::error('Error al procesar datos de UNAB', [
                'error' => $th->getMessage(),
                'file'  => $th->getFile(),
                'line'  => $th->getLine()
            ]);
        }
    }

    private function actualizarTiposUsuario(Usuario $usuario, array $nuevosTipos)
    {
        $tiposValidos = ['externo', 'estudiante', 'administrativo', 'egresado'];
        $tiposFiltrados = array_values(array_intersect($nuevosTipos, $tiposValidos));

        if (empty($tiposFiltrados)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Debe proporcionar al menos un tipo de usuario válido.',
            ], 400);
        }

        $usuario->tipos_usuario = $tiposFiltrados;
        $usuario->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Tipos de usuario actualizados correctamente.',
            'data' => [
                'id' => $usuario->id_usuario,
                'email' => $usuario->email,
                'tipo_usuario' => $usuario->tipos_usuario,
            ],
        ], 200);
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
                'nombre' => $usuario->persona?->primer_nombre,
                'apellido' => $usuario->persona?->primer_apellido,
                'tipo_usuario' => $usuario->tipos_usuario,
                'activo' => $usuario->activo,
                'rol' => $usuario->rol,
                'token_expires_at' => $currentToken?->expires_at,
                'permisos' => $usuario->obtenerTodosLosPermisos(),
            ];

            if ($shouldRefreshToken) {
                $currentToken->delete();

                $expirationConfig = config('sanctum.expiration', 1440);
                $expirationMinutes = is_numeric($expirationConfig) ? (int)$expirationConfig : 1440;
                $tokenName = 'auth-token-' . now()->format('Y-m-d-H-i-s');

                $newToken = $usuario->createToken(
                    $tokenName,
                    ['*'],
                    now()->addMinutes($expirationMinutes)
                );

                $data['access_token'] = $newToken->plainTextToken;
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
            $raw = $request->input('refresh');
            if (!$raw) {
                return response()->json(['status' => 'error', 'message' => 'no_refresh_token'], 401);
            }

            $result = $this->token_service->emitirDesdeRefresh(
                $raw,
                $request->ip(),
                $request->header('User-Agent'),
                null, // usa expiration de sanctum si está configurado
                true, // rotar si está por expirar
                3     // umbral en días
            );

            return response()->json([
                'status' => 'success',
                'message' => $result['rotated_refresh'] ? 'Token refrescado y refresh rotado.' : 'Token refrescado correctamente.',
                'data' => [
                    'access_token' => $result['access_token'],
                    'token_expires_at' => $result['token_expires_at'],
                    'refresh_token' => $result['refresh_token'],
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

    public function intercambiar(Request $req)
    {
        $codigo = $req->input('codigo');

        $authCode = AuthCode::where('consumido', false)
            ->where('expira_en', '>', now())
            ->where('user_agent', $req->header('User-Agent'))
            ->when($codigo, function ($query, $codigo) {
                return $query->where('codigo', $codigo);
            })
            ->first();

        if (!$authCode) return response()->json([
            'error' => 'código no encontrado o expirado, o el dispositivo no coincide.'
        ], 400);

        $authCode->consumido = true;
        $authCode->save();

        $user = $authCode->usuario;

        $access = $this->token_service->generarAccessToken($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Código de intercambio generado correctamente.',
            'data' => [
                'access_token' => $access['token'],
                'token_expires_at' => $access['expires_at'],
                'refresh_token' => $authCode->refresh_token_hash,
            ]
        ]);
    }

    public function refresh(Request $request)
    {
        // Delegar a refreshToken para mantener una sola lógica
        return $this->refreshToken($request);
    }
}
