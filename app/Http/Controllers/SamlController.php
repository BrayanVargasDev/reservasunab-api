<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SamlController extends Controller
{
    /**
     * Callback después de autenticación SAML exitosa
     */
    public function callback(Request $request)
    {
        if (Auth::check()) {
            Log::info('Usuario autenticado via SAML callback', [
                'user_id' => Auth::id(),
                'email' => Auth::user()->email
            ]);

            // Redirigir al dashboard o página deseada
            return redirect()->intended('/dashboard')->with('success', 'Autenticación SAML exitosa');
        }

        Log::warning('Callback SAML sin usuario autenticado');
        return redirect('/login')->with('error', 'Error en la autenticación SAML');
    }

    /**
     * Logout SAML
     */
    public function logout(Request $request)
    {
        if (Auth::check()) {
            $user = Auth::user();
            Log::info('Logout SAML iniciado', ['user_id' => $user->id_usuario]);

            // Disparar evento de logout
            event(new \App\Events\SamlAuth($user, [], 'logout'));

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect('/')->with('success', 'Sesión cerrada exitosamente');
    }

    /**
     * Metadata SAML (si necesitas exponer metadata personalizado)
     */
    public function metadata()
    {
        // Aquí puedes personalizar el metadata si es necesario
        // Por ahora, redirigir al metadata del paquete
        return redirect()->route('saml2.metadata');
    }
}
