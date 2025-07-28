<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCiudadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'codigo' => 'required|integer|unique:ciudades,codigo',
            'id_departamento' => 'required|exists:departamentos,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la ciudad es obligatorio.',
            'nombre.string' => 'El nombre de la ciudad debe ser un texto.',
            'nombre.max' => 'El nombre de la ciudad no puede exceder los 255 caracteres.',
            'codigo.required' => 'El código de la ciudad es obligatorio.',
            'codigo.integer' => 'El código de la ciudad debe ser un número entero.',
            'codigo.unique' => 'Este código de ciudad ya está registrado.',
            'id_departamento.required' => 'El departamento es obligatorio.',
            'id_departamento.exists' => 'El departamento seleccionado no existe.',
        ];
    }
}
