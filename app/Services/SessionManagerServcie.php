<?php

namespace App\Services;

use App\Models\AuthCode;
use App\Models\Usuario;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SessionManagerServcie
{

    const TIME_OUT = 30;

    private $unab_host = null;
    private $unab_endpoint = null;
    private $usuario_unab = null;
    private $password_unab = null;
    private $tarea = null;

    private function loadUnabConfig(): bool
    {
        if (function_exists('app') && app()->configurationIsCached()) {
            Log::info('Configuración de Laravel está en caché (config:cache activo).');
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

    public function procesarEmailDeGoogle(Usuario $user)
    {
        Log::info('Evento de inicio de sesión con google recibido');

        if (!$this->loadUnabConfig()) {
            return;
        }

        try {
            $email = $user->email;

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::error('Email inválido de usuario de Google', ['email' => $email]);
                return;
            }

            $datos = [
                'tarea' => $this->tarea,
                'correo_unab' => $email,
            ];

            $url = "https://{$this->unab_host}{$this->unab_endpoint}";

            // $response = Http::timeout(30)
            //     ->connectTimeout(5)
            //     ->withBasicAuth($this->usuario_unab, $this->password_unab)
            //     ->withHeaders([
            //         'Content-Type' => 'application/json',
            //         'Accept' => 'application/json',
            //         'Connection' => 'keep-alive'
            //     ])
            //     ->post($url, $datos);

            // if (!$response->successful()) {
            //     Log::error('Error en la comunicación con UNAB', [
            //         'status' => $response->status(),
            //         'body' => $response->body()
            //     ]);
                // return;
            // }

            // $usuarioEnUnab = $response->json();

            $datosUnab = [];
            // try {
                // $datosUnab = $usuarioEnUnab['datos'];
            // } catch (\Throwable $th) {
            //     Log::error('Error al obtener datos de UNAB', [
            //         'error' => $th->getMessage(),
            //         'file' => $th->getFile(),
            //         'line' => $th->getLine()
            //     ]);
                // return;
            // }

            // if (empty($datosUnab)) {
                // Log::error('Datos de UNAB están vacíos');
                // return;
            // }

            // if (!is_array($datosUnab)) {
                // Log::error('Datos de UNAB no son un array');
                // return;
            // }

            $tipoMap = [
                'ESTUDIANTE' => 'estudiante',
                'EMPLEADO' => 'administrativo',
                'EGRESADO' => 'egresado',
                'GRADUADO' => 'egresado',
            ];

            $primerElemento = is_array($datosUnab) && isset($datosUnab[0]) && is_array($datosUnab[0])
                ? $datosUnab[0]
                : [];

            $tiposUsuario = [];
            if (is_array($datosUnab)) {
                foreach ($datosUnab as $entrada) {
                    if (!is_array($entrada)) continue;
                    $tipoUpper = strtoupper($entrada['tipo'] ?? '');
                    if ($tipoUpper === '') continue;
                    $tiposUsuario[] = $tipoMap[$tipoUpper] ?? 'egresado';
                }
            }

            $tiposUsuario = array_values(array_unique($tiposUsuario));
            if (empty($tiposUsuario)) {
                $tiposUsuario = ['egresado'];
            }

            $payload = [
                'email' => $email,
                'ldap_uid' => $primerElemento['id_banner'] ?? null,
                'google_id' => $user->google_id,
                'tipos_usuario' => $tiposUsuario,
                'nombre' => $primerElemento['nombres'] ?? null,
                'apellido' => $primerElemento['apellidos'] ?? null,
                'telefono' => $primerElemento['celular'] ?? null,
                'documento' => $primerElemento['numero_documento'] ?? null,
                'activo' => true,
            ];

            $usuarioService = app(UsuarioService::class);

            if (!$user) {
                $user = $usuarioService->create($payload, false, true);
                Log::info('Usuario UNAB creado vía Oauth2', [
                    'user_id' => $user->id_usuario,
                    'email' => $email,
                    'tipos_usuario' => $tiposUsuario,
                ]);
            } else {
                $user = $usuarioService->update($user->id_usuario, $payload);
                Log::info('Usuario UNAB actualizado vía Oauth2', [
                    'user_id' => $user->id_usuario,
                    'email' => $email,
                    'tipos_usuario' => $tiposUsuario,
                ]);
            }

            $tokenService = app(TokenService::class);
            $ip = request()->ip();
            $dispositivo = request()->header('User-Agent');

            $tiene_refresh_valido = \App\Models\RefreshToken::where('id_usuario', $user->id_usuario)
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
                ? $tokenService->crearRefreshTokenParaUsuario($user, $ip, $dispositivo)['raw']
                : $tiene_refresh_valido->token_hash;

            $token = $tokenService->generarAccessToken($user);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'access_token' => $token['token'],
                    'id' => $user->id_usuario,
                    'email' => $user->email,
                    'rol' => $user->rol,
                    'nombre' => $user->persona->nombre ?? null,
                    'apellido' => $user->persona->apellido ?? null,
                    'tipo_usuario' => $user->tipos_usuario,
                    'activo' => $user->activo,
                    'token_expires_at' => $token['expires_at'],
                    'refresh_token' => $refresh_token,
                    'permisos' => $user->obtenerTodosLosPermisos()->pluck('codigo'),
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en la autenticación con Google', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
}
