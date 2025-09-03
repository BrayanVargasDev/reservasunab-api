<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreElementoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'valor_administrativo' => 'sometimes|numeric|min:0',
            'valor_estudiante' => 'sometimes|numeric|min:0',
            'valor_externo' => 'sometimes|numeric|min:0',
            'valor_egresado' => 'sometimes|numeric|min:0',
        ];
    }
}
