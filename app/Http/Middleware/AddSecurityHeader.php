<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeader
{

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Agrega la cabecera X-Frame-Options
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $frontend_url = config('app.frontend_url');
        // O, si prefieres usar Content-Security-Policy (CSP)
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'self' $frontend_url");

        return $response;
    }
}
