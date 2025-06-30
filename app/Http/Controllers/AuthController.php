<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    private $reglasDeValidacionDeRegistro = [
        'nombre' => 'required|string|max:255',
        'apellido' => 'required|string|max:255',
        'celular' => 'required|string|max:10',
        'email' => 'required|string|email|max:255|unique:usuarios',
        'password' => 'required|string|min:8',
    ];

    private $reglasDeValidacionDeLogin = [
        'email' => 'required|string|email|max:255',
        'password' => 'required|string|min:8',
    ];

    private $mensajesDeValidacion = [
        'email.required' => 'El campo email es obligatorio.',
        'email.email' => 'El campo email debe ser una dirección de correo electrónico válida.',
        'email.max' => 'El campo email no puede tener más de 255 caracteres.',
        'email.unique' => 'El email ya está registrado.',
        'password.required' => 'El campo contraseña es obligatorio.',
        'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
    ];

    public function registrar(Request $request)
    {
        $validarUsuario = Validator::make($request->all(), $this->reglasDeValidacionDeRegistro, $this->mensajesDeValidacion);

        if ($validarUsuario->fails()) {
            return response()->json([
                'errors' => $validarUsuario->errors(),
                'message' => 'Ocurrió un error en la validación de los datos.',
            ], 409);
        }

        $usuarioExistente = Usuario::where('email', $request->email)->first();

        if ($usuarioExistente) {
            return response()->json([
                'message' => 'El correo electrónico ya está registrado.',
            ], 409);
        }

        try {
            DB::beginTransaction();

            $usuario = Usuario::create([
                'email' => $request->email,
                'password_hash' => bcrypt($request->password),
                'tipo_usuario' => 'externo',
                'activo' => true,
            ]);

            Log::debug($usuario);

            if (!$usuario) {
                return response()->json([
                    'message' => 'Ocurrió un error al registrar el usuario.',
                ], 409);
            }
            DB::commit();
            return response()->json([
                'message' => 'Usuario registrado correctamente.',
                'token' => $usuario->createToken('auth-token')->plainTextToken,
            ], 201);
        } catch (Exception $th) {
            DB::rollBack();

            Log::error('Error al registrar el usuario', [
                'error' => $th->getMessage(),
                'archivo' => $th->getFile(),
                'linea' => $th->getLine(),
                'trace' => $th->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Ocurrió un error al registrar el usuario.',
                'error' => $th->getMessage(),
            ], 409);
        }
    }

    public function login()
    {
        try {
            $validarUsuario = Validator::make(request()->all(), $this->reglasDeValidacionDeLogin, $this->mensajesDeValidacion);

            if ($validarUsuario->fails()) {
                Log::warning('Intento de login fallido - Validación incorrecta', [
                    'email' => request('email'),
                    'errores' => $validarUsuario->errors()
                ]);
                return response()->json([
                    'errores' => $validarUsuario->errors(),
                    'message' => 'El usuario o la contraseña son incorrectos.',
                ], 409);
            }

            $email = request('email');
            $password = request('password');

            $usuario = Usuario::where('email', $email)->first();

            if (!$usuario || !password_verify($password, $usuario->password_hash)) {
                Log::warning('Intento de login fallido - Credenciales incorrectas', [
                    'email' => $email
                ]);
                return response()->json([
                    'message' => 'El usuario o la contraseña son incorrectos.',
                ], 401);
            }

            $token = $usuario->createToken('auth-token', ['*'], now()->addHour());

            Log::info('Login exitoso - Nuevo token creado', [
                'usuario_id' => $usuario->id_usuario,
            ]);

            return response()->json([
                'token' => $token->plainTextToken,
            ], 200);
        } catch (Exception $e) {
            Log::error('Error inesperado en el proceso de login', [
                'error' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Ha ocurrido un error inesperado. Por favor, intente nuevamente más tarde.',
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $usuario = $request->user();
        $usuario->tokens()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ], 200);
    }
}
