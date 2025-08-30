<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMensualidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_usuario' => 'sometimes|integer|exists:usuarios,id_usuario',
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => 'sometimes|date|after_or_equal:fecha_inicio',
            'valor' => 'sometimes|numeric|min:0',
            'estado' => 'sometimes|string|in:pendiente,activo,finalizado,cancelado',
        ];
    }
}
