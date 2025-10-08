<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Services\SessionManagerServcie;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{

    private $session_manager_service;

    public function __construct(SessionManagerServcie $session_manager_service)
    {
        $this->session_manager_service = $session_manager_service;
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        $usuarioGoogle = Socialite::driver('google')->stateless()->user();
        $usuario = Usuario::where('google_id', $usuarioGoogle->id)->orWhere('email', $usuarioGoogle->email)->first();
        $usuario->google_id = $usuarioGoogle->id;
        Log::info('Usuario encontrado: ', ['usuario' => $usuario]);

        $code = $this->session_manager_service->procesarEmailDeGoogle($usuario);

        return redirect()->away('https://reservasunab.wgsoluciones.com/auth/callback' . "?code=$code");
    }
}
