<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SamlTestController extends Controller
{
    public function login(Request $request)
    {
        Log::info('[SAML] Login route hit', [
            'ip' => $request->ip(),
            'query' => $request->query(),
            'time' => now()->toIso8601String(),
        ]);

        return response()->json([
            'status' => 'ok',
            'route' => 'saml-login',
            'message' => 'SAML login test endpoint reached.'
        ]);
    }

    public function logout(Request $request)
    {
        Log::info('[SAML] Logout route hit', [
            'ip' => $request->ip(),
            'query' => $request->query(),
            'time' => now()->toIso8601String(),
        ]);

        return response()->json([
            'status' => 'ok',
            'route' => 'saml-logout',
            'message' => 'SAML logout test endpoint reached.'
        ]);
    }

    public function error(Request $request)
    {
        Log::warning('[SAML] Error route hit', [
            'ip' => $request->ip(),
            'query' => $request->query(),
            'time' => now()->toIso8601String(),
        ]);

        return response()->json([
            'status' => 'ok',
            'route' => 'saml-error',
            'message' => 'SAML error test endpoint reached.'
        ]);
    }
}
