<?php

namespace App\Exceptions;

use Exception;

class ReservaException extends Exception
{
    protected $statusCode;
    protected $errorType;

    /**
     * Constructor para la excepción personalizada de reservas
     *
     * @param string $message Mensaje de error
     * @param string $errorType Tipo de error (validation, not_found, permission, etc.)
     * @param int $statusCode Código de estado HTTP
     * @param \Throwable|null $previous Excepción previa
     */
    public function __construct(
        string $message = 'Error en la operación de reservas',
        string $errorType = 'general',
        int $statusCode = 400,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
        $this->errorType = $errorType;
    }

    /**
     * Obtener el código de estado HTTP
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Obtener el tipo de error
     *
     * @return string
     */
    public function getErrorType(): string
    {
        return $this->errorType;
    }

    /**
     * Convertir la excepción a una respuesta JSON
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function render()
    {
        return response()->json([
            'status' => 'error',
            'error_type' => $this->errorType,
            'message' => $this->getMessage()
        ], $this->statusCode);
    }
}
