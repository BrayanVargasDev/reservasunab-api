<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUsuarioPermisosRequest extends FormRequest
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
            'permisos.required' => 'Los permisos son obligatorios.',
            'permisos.array' => 'Los permisos deben ser un arreglo.',
            'permisos.*.id_permiso.required' => 'El ID del permiso es obligatorio.',
            'permisos.*.id_permiso.integer' => 'El ID del permiso debe ser un número entero.',
            'permisos.*.id_permiso.exists' => 'Uno o más permisos seleccionados no existen en la base de datos.',
            'permisos.*.concedido.required' => 'La propiedad concedido es obligatoria para cada permiso.',
            'permisos.*.concedido.boolean' => 'La propiedad concedido debe ser verdadero o falso.',
        ];
    }
}
