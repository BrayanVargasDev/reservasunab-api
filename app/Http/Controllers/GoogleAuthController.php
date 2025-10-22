<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Services\SessionManagerServcie;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
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
        return Socialite::driver('google')
            ->with(["prompt" => "select_account"])
            ->stateless()
            ->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        $request->validate(['idToken' => 'required|string']);

        $usuarioGoogle = Socialite::driver('google')
            ->stateless()
            ->userFromToken($request->idToken);

        $usuario = Usuario::where('google_id', $usuarioGoogle->id)
            ->orWhere('email', $usuarioGoogle->email)
            ->first();

        if ($usuario) {
            $usuario->google_id = $usuarioGoogle->id;
            $usuario->save();
        }

        return $this->session_manager_service->procesarEmailDeGoogle($usuario, $usuarioGoogle->id);
    }
}
