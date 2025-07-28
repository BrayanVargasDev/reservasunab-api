<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonaRequest extends FormRequest
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
        $personaId = $this->route('persona') ? $this->route('persona')->id_persona : null;

        return [
            'tipo_documento_id' => 'sometimes|required|exists:tipo_documentos,id_tipo',
            'numero_documento' => 'sometimes|required|string|max:20|unique:personas,numero_documento,' . $personaId . ',id_persona',
            'primer_nombre' => 'sometimes|required|string|max:50',
            'segundo_nombre' => 'nullable|string|max:50',
            'primer_apellido' => 'sometimes|required|string|max:50',
            'segundo_apellido' => 'nullable|string|max:50',
            'fecha_nacimiento' => 'nullable|date|before:today',
            'direccion' => 'nullable|string|max:255',
            'celular' => 'nullable|string|max:15',
            'tipo_persona' => 'sometimes|required|in:natural,juridica',
            'regimen_tributario_id' => 'nullable|exists:regimen_tributarios,id',
            'ciudad_expedicion_id' => 'nullable|exists:ciudads,id',
            'ciudad_residencia_id' => 'nullable|exists:ciudads,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'tipo_persona.required' => 'El tipo de persona es obligatorio.',
            'tipo_persona.in' => 'El tipo de persona debe ser natural o jurídica.',
            'regimen_tributario_id.exists' => 'El régimen tributario seleccionado no existe.',
            'ciudad_expedicion_id.exists' => 'La ciudad de expedición seleccionada no existe.',
            'ciudad_residencia_id.exists' => 'La ciudad de residencia seleccionada no existe.',
            'numero_documento.unique' => 'Este número de documento ya está registrado.',
            'tipo_documento_id.exists' => 'El tipo de documento seleccionado no existe.',
        ];
    }
}
