<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUsuarioDashboardRequest extends FormRequest
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
            'apellido' => 'required|string|max:255',
            'email' => 'required|email|max:100|unique:usuarios,email',
            'tipoDocumento' => 'required|exists:tipos_documento,id_tipo',
            'documento' => 'required|string|max:20|unique:personas,numero_documento',
            // 'rol' => 'required|exists:roles,id_rol',
            // 'tipoUsuario' => 'required|in:estudiante,administrativo,egresado,externo',
            'password' => 'nullable|string|min:8|max:255',
            'fechaNacimiento' => 'nullable',
            'telefono' => 'nullable|string|max:15',
            'direccion' => 'nullable|string|max:255',
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
            'nombre.required' => 'El nombre es obligatorio.',
            'apellido.required' => 'El apellido es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El formato del correo electrónico es inválido.',
            'email.unique' => 'El correo electrónico ya está en uso.',
            'tipoDocumento.required' => 'El tipo de documento es obligatorio.',
            'documento.required' => 'El número de documento es obligatorio.',
            'documento.unique' => 'El número de documento ya está en uso.',
            'rol.required' => 'El rol es obligatorio.',
            'tipoUsuario.required' => 'El tipo de usuario es obligatorio.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}
