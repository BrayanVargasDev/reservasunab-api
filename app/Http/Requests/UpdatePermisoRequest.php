<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermisoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'sometimes|string|max:500',
            'icono' => 'sometimes|string|max:50',
            'id_pantalla' => 'sometimes|exists:pantallas,id_pantalla',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        return [
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede exceder los 255 caracteres.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
            'descripcion.max' => 'La descripción no puede exceder los 500 caracteres.',
            'icono.string' => 'El icono debe ser una cadena de texto.',
            'icono.max' => 'El icono no puede exceder los 50 caracteres.',
            'id_pantalla.exists' => 'La pantalla seleccionada no existe.',
        ];
    }
}
