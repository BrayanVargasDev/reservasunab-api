<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRolRequest extends FormRequest
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
            'nombre' => 'required|string|max:255',
            'descripcion' => 'required|string|max:500',
            'permisos' => 'required|array',
            'permisos.*.id_permiso' => 'required|integer|exists:permisos,id_permiso',
            'permisos.*.concedido' => 'required|boolean',
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        return [
            'nombre.required' => 'El nombre del rol es obligatorio.',
            'nombre.string' => 'El nombre del rol debe ser una cadena de texto.',
            'nombre.max' => 'El nombre del rol no puede exceder los 255 caracteres.',
            'descripcion.required' => 'La descripción del rol es obligatoria.',
            'descripcion.string' => 'La descripción del rol debe ser una cadena de texto.',
            'descripcion.max' => 'La descripción del rol no puede exceder los 500 caracteres.',
            'permisos.required' => 'Los permisos son obligatorios.',
            'permisos.array' => 'Los permisos deben ser un arreglo.',
            'permisos.*.id_permiso.required' => 'El ID del permiso es obligatorio.',
            'permisos.*.id_permiso.integer' => 'Todos los ID de permisos deben ser números enteros.',
            'permisos.*.id_permiso.exists' => 'Uno o más permisos seleccionados no existen en la base de datos.',
            'permisos.*.concedido.required' => 'La propiedad concedido es obligatoria para cada permiso.',
            'permisos.*.concedido.boolean' => 'La propiedad concedido debe ser verdadero o falso.',
        ];
    }
}
