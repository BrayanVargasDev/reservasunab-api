<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Auth\AuthenticationException;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * Este middleware garantiza que las rutas API solo usen tokens Bearer
     * y no autenticación basada en sesiones/cookies.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            throw new AuthenticationException('Token Bearer requerido para rutas API.');
        }

        $accessToken = PersonalAccessToken::findToken($bearerToken);

        if (!$accessToken) {
            throw new AuthenticationException('Token Bearer inválido.');
        }

        // Verificar si el token ha expirado
        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            $accessToken->delete();
            throw new AuthenticationException('Token expirado.');
        }

        // Configurar el usuario autenticado
        auth('sanctum')->setUser($accessToken->tokenable);

        return $next($request);
    }
}
