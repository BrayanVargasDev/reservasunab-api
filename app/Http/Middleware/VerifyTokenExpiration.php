<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class VerifyTokenExpiration
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('sanctum')->check()) {
            return $next($request);
        }

        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return $next($request);
        }

        $accessToken = PersonalAccessToken::findToken($bearerToken);

        if (!$accessToken) {
            return $next($request);
        }

        if (!$accessToken->expires_at || !$accessToken->expires_at->isPast()) {
            return $next($request);
        }

        $accessToken->delete();

        return response()->json([
            'status' => 'error',
            'message' => 'Token expirado. Por favor, inicie sesión nuevamente.',
            'error_code' => 'TOKEN_EXPIRED'
        ], 401);
    }
}
