<?php

namespace App\Providers;

use App\Models\AuthCode;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use App\Models\Usuario;
use App\Services\UsuarioService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\TokenService;

class AppServiceProvider extends ServiceProvider
{

    const TIME_OUT = 30;

    private $unab_host = null;
    private $unab_endpoint = null;
    private $usuario_unab = null;
    private $password_unab = null;
    private $tarea = null;

    /**
     * Carga las variables UNAB desde config('app.*') y valida que existan.
     * Retorna true si todo está correcto, false si falta alguna crítica.
     */
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

        Log::info('Configuración UNAB cargada', [
            'host' => $this->unab_host,
            'endpoint' => $this->unab_endpoint,
            'usuario' => $this->usuario_unab,
            'tarea' => $this->tarea,
            'password_set' => $this->password_unab ? true : false,
        ]);

        return true;
    }

    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(\Laravel\Sanctum\PersonalAccessToken::class);

        // Registrar eventos SAML directamente
        Event::listen(\Slides\Saml2\Events\SignedIn::class, function (\Slides\Saml2\Events\SignedIn $event) {
            Log::info('Evento SignedIn recibido');

            if (!$this->loadUnabConfig()) {
                return;
            }

            try {
                $samlUser = $event->auth->getSaml2User();
                $attributes = $samlUser->getAttributes();

                $email = null;
                $possibleEmailFields = ['emailaddress', 'email', 'mail', 'Email', 'EmailAddress'];

                foreach ($possibleEmailFields as $field) {
                    if (isset($attributes[$field]) && !empty($attributes[$field])) {
                        $email = is_array($attributes[$field]) ? $attributes[$field][0] : $attributes[$field];
                        break;
                    }
                }

                if (!$email) {
                    $email = $samlUser->getUserId();
                }

                Log::info('Email extraido de google', ['email' => $email]);

                if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Log::error('Email inválido de usuario de Google', ['email' => $email]);
                    return;
                }

                $datos = [
                    'tarea' => $this->tarea,
                    'correo_unab' => $email,
                ];


                $url = "https://{$this->unab_host}{$this->unab_endpoint}";

                $response = Http::timeout(30)
                    ->connectTimeout(5)
                    ->withBasicAuth($this->usuario_unab, $this->password_unab)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Connection' => 'keep-alive'
                    ])
                    ->post($url, $datos);

                if (!$response->successful()) {
                    Log::error('Error en la comunicación con UNAB', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    return;
                }

                $usuarioEnUnab = $response->json();

                $datosUnab = null;
                try {
                    $datosUnab = $usuarioEnUnab['datos'];
                } catch (\Throwable $th) {
                    Log::error('Error al obtener datos de UNAB', [
                        'error' => $th->getMessage(),
                        'file' => $th->getFile(),
                        'line' => $th->getLine()
                    ]);
                    return;
                }

                if (empty($datosUnab)) {
                    Log::error('Datos de UNAB están vacíos');
                    return;
                }

                if (!is_array($datosUnab)) {
                    Log::error('Datos de UNAB no son un array');
                    return;
                }

                $tipoMap = [
                    'ESTUDIANTE' => 'estudiante',
                    'EMPLEADO' => 'administrativo',
                    'EGRESADO' => 'egresado',
                ];

                $tipoUnabUpper = strtoupper($datosUnab['tipo'] ?? '');
                $tipoUsuario = $tipoMap[$tipoUnabUpper] ?? 'externo';

                $payload = [
                    'email' => $email,
                    'ldap_uid' => $datosUnab['id_banner'] ?? $samlUser->getUserId(),
                    'tipos_usuario' => [$tipoUsuario],
                    'nombre' => $datosUnab['nombres'] ?? null,
                    'apellido' => $datosUnab['apellidos'] ?? null,
                    'telefono' => $datosUnab['celular'] ?? null,
                    'documento' => $datosUnab['numero_documento'] ?? null,
                    'activo' => true,
                ];

                $usuarioService = app(UsuarioService::class);

                $user = Usuario::where('email', $email)->first();

                if (!$user) {
                    $user = $usuarioService->create($payload, true);
                    Log::info('Usuario UNAB creado vía SAML', [
                        'user_id' => $user->id_usuario,
                        'email' => $email,
                        'tipo_usuario' => $tipoUsuario,
                    ]);
                } else {
                    $user = $usuarioService->update($user->id_usuario, $payload);
                    Log::info('Usuario UNAB actualizado vía SAML', [
                        'user_id' => $user->id_usuario,
                        'email' => $email,
                        'tipo_usuario' => $tipoUsuario,
                    ]);
                }

                // Crear refresh token y vincularlo a un AuthCode que expira en 90 segundos
                $frontendUrl = config('app.frontend_url');

                /** @var TokenService $tokenService */
                $tokenService = app(TokenService::class);
                $ip = request()->ip();
                $device = request()->header('User-Agent');

                $refresh = $tokenService->crearRefreshTokenParaUsuario($user, $ip, $device);

                $codigo = Str::random(64);
                AuthCode::create([
                    'id_usuario' => $user->id_usuario,
                    'codigo' => $codigo,
                    'refresh_token_hash' => $refresh['model']->token_hash,
                    'expira_en' => now()->addSeconds(90),
                    'consumido' => false,
                ]);

                Log::info('Usuario autenticado a través de SSO, código de intercambio generado', [
                    'user_id' => $user->id_usuario,
                    'code_len' => strlen($codigo),
                ]);
                Log::debug('Código de intercambio generado', ['code' => $codigo]);
                Log::debug('Url a la que se redirige', ['url' => "{$frontendUrl}/auth/callback?code={$codigo}"]);
                return redirect()->away("{$frontendUrl}/auth/callback?code={$codigo}");
            } catch (\Exception $e) {
                Log::error('Error en la autenticación con Google', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
        });

        Event::listen(\Slides\Saml2\Events\SignedOut::class, function (\Slides\Saml2\Events\SignedOut $event) {
            Log::info('SAML2 SignedOut event received');
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();
        });
    }
}
