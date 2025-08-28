<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBeneficiarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:150',
            'apellido' => 'required|string|max:150',
            'tipoDocumento' => 'required|integer|exists:tipos_documento,id_tipo',
            'documento' => 'required|string|max:50',
            'parentesco' => 'required|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
            'apellido.required' => 'El apellido es obligatorio',
            'tipoDocumento.required' => 'El tipo de documento es obligatorio',
            'tipoDocumento.exists' => 'El tipo de documento no es válido',
            'documento.required' => 'El número de documento es obligatorio',
            'parentesco.required' => 'El parentesco es obligatorio',
        ];
    }
}
