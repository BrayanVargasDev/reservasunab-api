<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use App\Models\Usuario;
use App\Services\ReservaService;
use App\Services\PagoService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
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

            try {
                $samlUser = $event->auth->getSaml2User();
                $attributes = $samlUser->getAttributes();

                Log::info('Atributos del usuario de Google', ['attributes' => $attributes]);

                // Extraer email del usuario - ajustar según tu proveedor SAML
                $email = null;
                $possibleEmailFields = ['emailaddress', 'email', 'mail', 'Email', 'EmailAddress'];

                foreach ($possibleEmailFields as $field) {
                    if (isset($attributes[$field]) && !empty($attributes[$field])) {
                        $email = is_array($attributes[$field]) ? $attributes[$field][0] : $attributes[$field];
                        break;
                    }
                }

                // Si no se encuentra email en atributos, usar el ID del usuario
                if (!$email) {
                    $email = $samlUser->getUserId();
                }

                Log::info('Email extraido de google', ['email' => $email]);

                if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Log::error('Email inválido de usuario de Google', ['email' => $email]);
                    return;
                }

                // $usuarioEnUnab = Http::get();


                // Buscar o crear usuario
                $user = Usuario::where('email', $email)->first();

                if (!$user) {
                    $user = Usuario::create([
                        'email' => $email,
                        'ldap_uid' => $samlUser->getUserId(),
                        'tipo_usuario' => 'saml',
                        'activo' => true,
                        // 'id_rol' => 3,
                    ]);

                    // Asignar el permiso de reservar a todos los usuarios nuevos
                    $user->asignarPermisoReservar();

                    Log::info('Nuevo usuario google creado', ['user_id' => $user->id_usuario, 'email' => $email]);
                } else {
                    $user->update([
                        'ldap_uid' => $samlUser->getUserId(),
                        'activo' => true,
                    ]);
                    Log::info('Usuario existente actualizado', ['user_id' => $user->id_usuario, 'email' => $email]);
                }

                Auth::login($user, true);
                Log::info('Usuario autenticado a través de Google', ['user_id' => $user->id_usuario]);
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
