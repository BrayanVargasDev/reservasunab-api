<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SamlTestController;

Route::get('/', function () {
    return view('welcome');
});

// Rutas de prueba para SAML (usando valores de config/saml2.php)
Route::prefix(config('saml2.routesPrefix', '/api/saml'))
    ->middleware(config('saml2.routesMiddleware', ['web']))
    ->group(function () {
        Route::get('/login', [SamlTestController::class, 'login'])->name(config('saml2.loginRoute', 'saml-login'));
        Route::get('/logout', [SamlTestController::class, 'logout'])->name(config('saml2.logoutRoute', 'saml-logout'));
        Route::get('/error', [SamlTestController::class, 'error'])->name(config('saml2.errorRoute', 'saml-error'));
    });
