<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use App\Models\Usuario;
use Illuminate\Support\Facades\Event;
use App\Services\SamlAuthService;
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

        Event::listen(\Slides\Saml2\Events\SignedIn::class, function (\Slides\Saml2\Events\SignedIn $event) {
            Log::debug('SAML2 SignedIn event triggered', [
                'user' => $event->auth->getSaml2User()->getUserId(),
                'attributes' => $event->auth->getSaml2User()->getAttributes()
            ]);
            $samlAuthService = new SamlAuthService();
            $samlAuthService->handleSamlSignIn($event);
        });

        Event::listen(\Slides\Saml2\Events\SignedOut::class, function (\Slides\Saml2\Events\SignedOut $event) {
            \Illuminate\Support\Facades\Auth::logout();
            \Illuminate\Support\Facades\Log::info('Usuario desconectado via SAML2');
        });
    }
}
