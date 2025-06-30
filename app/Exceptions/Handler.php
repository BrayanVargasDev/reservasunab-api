<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (Throwable $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {

                if ($e instanceof QueryException) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Error en la consulta a la base de datos',
                        'error' => config('app.debug') ? $e->getMessage() : null
                    ], 400);
                }

                if ($e instanceof ModelNotFoundException) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Recurso no encontrado'
                    ], 404);
                }

                if ($e instanceof NotFoundHttpException) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'La ruta especificada no existe'
                    ], 404);
                }

                if ($e instanceof AuthenticationException) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No autenticado'
                    ], 401);
                }

                if ($e instanceof AuthorizationException) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No autorizado para realizar esta acción'
                    ], 403);
                }

                if ($e instanceof ValidationException) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Datos de entrada inválidos',
                        'errors' => $e->errors()
                    ], 422);
                }

                if ($e instanceof ThrottleRequestsException) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Demasiadas solicitudes. Por favor, inténtelo de nuevo más tarde.'
                    ], 429);
                }

                if (!config('app.debug')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Ha ocurrido un error en el servidor'
                    ], 500);
                }
            }

            return null; // Dejar que Laravel maneje otros casos
        });
    }
}
