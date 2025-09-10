<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateElementoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'sometimes|string|max:255',
            'valor_administrativo' => 'sometimes|numeric|min:0',
            'valor_egresado' => 'sometimes|numeric|min:0',
            'valor_estudiante' => 'sometimes|numeric|min:0',
            // 'valor_externo' => 'sometimes|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no debe exceder los 255 caracteres.',
            'valor_administrativo.numeric' => 'El valor para administrativos debe ser un número.',
            'valor_administrativo.min' => 'El valor para administrativos no puede ser negativo.',
            'valor_egresado.numeric' => 'El valor para egresados debe ser un número.',
            'valor_egresado.min' => 'El valor para egresados no puede ser negativo.',
            'valor_estudiante.numeric' => 'El valor para estudiantes debe ser un número.',
            'valor_estudiante.min' => 'El valor para estudiantes no puede ser negativo.',
            // 'valor_externo.numeric' => 'El valor para externos debe ser un número.',
            // 'valor_externo.min' => 'El valor para externos no puede ser negativo.',
        ];
    }
}
