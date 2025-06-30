<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUsuarioDashboardRequest extends FormRequest
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
            'id' => 'sometimes|exists:usuarios,id_usuario',
            'nombre' => 'sometimes|string|max:255',
            'apellido' => 'sometimes|string|max:255',
            'tipoDocumento' => 'sometimes|exists:tipos_documento,id_tipo',
            // 'documento' => 'sometimes|string|max:20|unique:personas,numero_documento',
            // 'rol' => 'sometimes|exists:roles,id_rol',
            'tipoUsuario' => 'sometimes|in:estudiante,administrativo,egresado,externo',
            'password' => 'sometimes|string|min:8|max:255',
            'fechaNacimiento' => 'sometimes',
            'telefono' => 'sometimes|string|max:15',
            'direccion' => 'sometimes|string|max:255',
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
            'id.exists' => 'El ID del usuario no es válido.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'apellido.string' => 'El apellido debe ser una cadena de texto.',
            'tipoDocumento.exists' => 'El tipo de documento seleccionado no es válido.',
            // 'documento.unique' => 'El número de documento ya está en uso.',
            // 'rol.exists' => 'El rol seleccionado no es válido.',
            'tipoUsuario.in' => 'El tipo de usuario seleccionado no es válido.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'fechaNacimiento.date_format' => 'La fecha de nacimiento debe estar en el formato Y-m-d.',
            'telefono.string' => 'El teléfono debe ser una cadena de texto.',
            'direccion.string' => 'La dirección debe ser una cadena de texto.',
        ];
    }
}
