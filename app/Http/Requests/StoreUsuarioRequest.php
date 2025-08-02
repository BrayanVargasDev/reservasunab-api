<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsuarioRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'email' => 'required|string|email|max:100|unique:usuarios',
            'password' => 'required|string|min:8',
            'tipos_usuario' => [
                'required',
                'array',
                'min:1'
            ],
            'tipos_usuario.*' => [
                'required',
                'string',
                Rule::in(['estudiante', 'docente', 'administrativo', 'egresado', 'externo']),
            ],
            'rol' => [
                'required',
                Rule::in(['gestor', 'consultor', 'administrador']),
            ],
            'ldap_uid' => 'nullable|uuid|unique:usuarios',
            'id_persona' => 'nullable|exists:personas,id_persona',
            'activo' => 'boolean'
        ];
    }

    /**
     * Obtiene los mensajes personalizados para los errores de validación.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'email.required' => 'El email es obligatorio',
            'email.email' => 'El formato del email no es válido',
            'email.unique' => 'El email ya está en uso',
            'password.required' => 'La contraseña es obligatoria',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'tipos_usuario.required' => 'Los tipos de usuario son obligatorios',
            'tipos_usuario.array' => 'Los tipos de usuario deben ser un array',
            'tipos_usuario.min' => 'Debe seleccionar al menos un tipo de usuario',
            'tipos_usuario.*.required' => 'Cada tipo de usuario es obligatorio',
            'tipos_usuario.*.in' => 'Uno o más tipos de usuario seleccionados no son válidos',
            'rol.required' => 'El rol es obligatorio',
            'rol.in' => 'El rol seleccionado no es válido',
            'ldap_uid.uuid' => 'El formato del UID de LDAP no es válido',
            'ldap_uid.unique' => 'El UID de LDAP ya está en uso',
            'id_persona.exists' => 'La persona seleccionada no existe',
        ];
    }
}
