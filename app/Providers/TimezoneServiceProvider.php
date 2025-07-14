<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Carbon\Carbon;

class TimezoneServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configurar la zona horaria de Carbon para toda la aplicación
        $timezone = config('app.timezone', 'America/Bogota');

        // Configurar Carbon para usar la zona horaria de la aplicación
        Carbon::setLocale('es');
        date_default_timezone_set($timezone);
    }
}
