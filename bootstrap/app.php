<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Comentamos statefulApi() para usar solo tokens sin cookies
        // $middleware->statefulApi();
        $middleware->alias([
            'security_headers' => \App\Http\Middleware\AddSecurityHeader::class,
            'verify.token.expiration' => \App\Http\Middleware\VerifyTokenExpiration::class,
        ]);

        $middleware->append([
            'security_headers',
        ]);

        // Deshabilitamos la validación CSRF para las rutas API
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'api/saml/*/acs'
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
