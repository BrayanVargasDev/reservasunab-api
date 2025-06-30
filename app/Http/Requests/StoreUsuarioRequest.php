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
            'tipo_usuario' => [
                'required',
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
            'tipo_usuario.required' => 'El tipo de usuario es obligatorio',
            'tipo_usuario.in' => 'El tipo de usuario seleccionado no es válido',
            'rol.required' => 'El rol es obligatorio',
            'rol.in' => 'El rol seleccionado no es válido',
            'ldap_uid.uuid' => 'El formato del UID de LDAP no es válido',
            'ldap_uid.unique' => 'El UID de LDAP ya está en uso',
            'id_persona.exists' => 'La persona seleccionada no existe',
        ];
    }
}
