<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
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
        $usuario_id = $this->route('usuario')->id_usuario;

        return [
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:100',
                Rule::unique('usuarios')->ignore($usuario_id, 'id_usuario'),
            ],
            'password' => 'sometimes|string|min:8',
            'tipo_usuario' => [
                'sometimes',
                Rule::in(['estudiante', 'docente', 'administrativo', 'egresado', 'externo']),
            ],
            'rol' => 'sometimes|exists:roles,id_rol',
            'ldap_uid' => [
                'nullable',
                'uuid',
                Rule::unique('usuarios')->ignore($usuario_id, 'id_usuario'),
            ],
            'id_persona' => 'sometimes|nullable|exists:personas,id_persona',
            'activo' => 'sometimes|boolean'
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
            'email.email' => 'El formato del email no es válido',
            'email.unique' => 'El email ya está en uso por otro usuario',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'tipo_usuario.in' => 'El tipo de usuario seleccionado no es válido',
            'rol.exists' => 'El rol seleccionado no existe',
            'ldap_uid.uuid' => 'El formato del UID de LDAP no es válido',
            'ldap_uid.unique' => 'El UID de LDAP ya está en uso por otro usuario',
            'id_persona.exists' => 'La persona seleccionada no existe',
        ];
    }
}
