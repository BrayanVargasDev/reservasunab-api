<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Programar limpieza de tokens expirados diariamente a las 2:00 AM
Schedule::command('sanctum:clean-expired-tokens')->dailyAt('02:00');
