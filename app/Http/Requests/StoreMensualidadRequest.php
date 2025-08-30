<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMensualidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_usuario' => 'required|integer|exists:usuarios,id_usuario',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'valor' => 'required|numeric|min:0',
            'estado' => 'sometimes|string|in:pendiente,activo,finalizado,cancelado',
        ];
    }
}
