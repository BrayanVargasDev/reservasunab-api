<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'documento' => 'sometimes|string|max:20',
            // 'rol' => 'sometimes|exists:roles,id_rol',
            'tipoUsuario' => [
                'sometimes',
                'array',
                'min:1'
            ],
            'tipoUsuario.*' => [
                'required',
                'string',
                Rule::in(['estudiante', 'administrativo', 'egresado', 'externo']),
            ],
            'password' => 'sometimes|string|min:8|max:255',
            'fechaNacimiento' => 'sometimes',
            'telefono' => 'sometimes|string|max:15',
            'direccion' => 'sometimes|string|max:255',
            'ciudadExpedicion' => 'sometimes|exists:ciudades,id',
            'ciudadResidencia' => 'sometimes|exists:ciudades,id',
            'regimenTributario' => 'sometimes|exists:regimenes_tributarios,codigo',
            // Datos de facturación
            'facturacion' => 'sometimes|nullable|array',
            'facturacion.nombre' => 'sometimes|string|max:255',
            'facturacion.apellido' => 'sometimes|string|max:255',
            'facturacion.tipoDocumento' => 'sometimes|exists:tipos_documento,id_tipo',
            'facturacion.documento' => 'sometimes|string|max:20',
            'facturacion.fechaNacimiento' => 'sometimes|date',
            'facturacion.telefono' => 'sometimes|string|max:15',
            'facturacion.digitoVerificacion' => 'sometimes|numeric|max:1|digits:1',
            'facturacion.direccion' => 'sometimes|string|max:255',
            'facturacion.ciudadExpedicion' => 'sometimes|exists:ciudades,id',
            'facturacion.ciudadResidencia' => 'sometimes|exists:ciudades,id',
            'facturacion.email' => 'sometimes|string|email|max:100',
            'facturacion.regimenTributario' => 'sometimes|exists:regimenes_tributarios,codigo',
            'facturacion.tipoPersona' => 'sometimes|in:natural,juridica',
            'facturacion.id' => 'sometimes|exists:personas,id_persona',
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
            'ciudadExpedicion.exists' => 'La ciudad de expedición seleccionada no es válida.',
            'ciudadResidencia.exists' => 'La ciudad de residencia seleccionada no es válida.',
            'regimenTributario.exists' => 'El régimen tributario seleccionado no es válido.',
            'facturacion.array' => 'Los datos de facturación deben ser un objeto.',
            'facturacion.nombre.string' => 'El nombre de la facturación debe ser una cadena de texto.',
            'facturacion.apellido.string' => 'El apellido de la facturación debe ser una cadena de texto.',
            'facturacion.tipoDocumento.exists' => 'El tipo de documento de la facturación seleccionado no es válido.',
            'facturacion.documento.string' => 'El documento de la facturación debe ser una cadena de texto.',
            'facturacion.email.email' => 'El email de la facturación debe ser una dirección de correo electrónico válida.',
            'facturacion.telefono.string' => 'El teléfono de la facturación debe ser una cadena de texto.',
            'facturacion.ciudadExpedicion.exists' => 'La ciudad de expedición de la facturación seleccionada no es válida.',
            'facturacion.regimenTributario.exists' => 'El régimen tributario de la facturación seleccionado no es válido.',
            'facturacion.tipoPersona.in' => 'El tipo de persona de la facturación seleccionado no es válido.',
            'facturacion.id.exists' => 'La persona de facturación seleccionada no es válida.',
            'facturacion.digitoVerificacion.string' => 'El dígito de verificación de la facturación debe ser una cadena de texto.',
            'facturacion.digitoVerificacion.max' => 'El dígito de verificación de la facturación no puede tener más de 1 carácter.',
        ];
    }
}
