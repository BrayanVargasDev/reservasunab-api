<?php

namespace App\Exceptions;

use Exception;

class TipoDocumentoException extends Exception
{
    protected $statusCode;
    protected $errorType;

    public function __construct(
        string $message = 'Error en la operación de tipo de documento',
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
