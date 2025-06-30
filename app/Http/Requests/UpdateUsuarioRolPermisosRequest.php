<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUsuarioRolPermisosRequest extends FormRequest
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
            'id_rol' => 'nullable|integer|exists:roles,id_rol',
            'permisos_directos' => 'sometimes|array',
            'permisos_directos.*.id_permiso' => 'required|integer|exists:permisos,id_permiso',
            'permisos_directos.*.concedido' => 'required|boolean',
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
            'id_rol.integer' => 'El ID del rol debe ser un número entero.',
            'id_rol.exists' => 'El rol seleccionado no existe en la base de datos.',
            'permisos_directos.array' => 'Los permisos directos deben ser un arreglo.',
            'permisos_directos.*.id_permiso.required' => 'El ID del permiso es obligatorio.',
            'permisos_directos.*.id_permiso.integer' => 'El ID del permiso debe ser un número entero.',
            'permisos_directos.*.id_permiso.exists' => 'Uno o más permisos seleccionados no existen en la base de datos.',
            'permisos_directos.*.concedido.required' => 'La propiedad concedido es obligatoria para cada permiso.',
            'permisos_directos.*.concedido.boolean' => 'La propiedad concedido debe ser verdadero o falso.',
        ];
    }
}
