<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use App\Models\Usuario;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(\Laravel\Sanctum\PersonalAccessToken::class);

        // Registrar eventos SAML2 directamente
        Event::listen(\Slides\Saml2\Events\SignedIn::class, function (\Slides\Saml2\Events\SignedIn $event) {
            Log::info('SAML2 SignedIn event received');

            try {
                $samlUser = $event->auth->getSaml2User();
                $attributes = $samlUser->getAttributes();

                Log::info('SAML user attributes', ['attributes' => $attributes]);

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

                Log::info('Extracted email from SAML', ['email' => $email]);

                if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Log::error('Invalid email from SAML user', ['email' => $email]);
                    return;
                }

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
                    Log::info('New SAML user created', ['user_id' => $user->id_usuario, 'email' => $email]);
                } else {
                    $user->update([
                        'ldap_uid' => $samlUser->getUserId(),
                        'activo' => true,
                    ]);
                    Log::info('Existing SAML user updated', ['user_id' => $user->id_usuario, 'email' => $email]);
                }

                Auth::login($user, true); // true para "remember me"
                Log::info('User authenticated via SAML', ['user_id' => $user->id_usuario]);
            } catch (\Exception $e) {
                Log::error('Error in SAML authentication', [
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
