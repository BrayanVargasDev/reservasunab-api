<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePermisoRequest extends FormRequest
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
        // Verificar si es un array de permisos o un solo permiso
        $input = $this->all();

        // Si el primer elemento es un array, entonces es un array de permisos
        if (isset($input[0]) && is_array($input[0])) {
            return [
                '*.nombre' => 'required|string|max:255|unique:permisos,nombre',
                '*.codigo' => 'required|string|max:50|unique:permisos,codigo',
                '*.icono' => 'nullable|string|max:50',
                '*.descripcion' => 'nullable|string|max:500',
                '*.idPantalla' => 'required|exists:pantallas,id_pantalla',
            ];
        }

        // Si no es un array, validar como un solo permiso
        return [
            'nombre' => 'required|string|max:255|unique:permisos,nombre',
            'codigo' => 'required|string|max:50|unique:permisos,codigo',
            'icono' => 'nullable|string|max:50',
            'descripcion' => 'nullable|string|max:500',
            'idPantalla' => 'required|exists:pantallas,id_pantalla',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        $input = $this->all();

        // Si es un array de permisos
        if (isset($input[0]) && is_array($input[0])) {
            return [
                '*.nombre.required' => 'El nombre del permiso es obligatorio.',
                '*.nombre.string' => 'El nombre debe ser una cadena de texto.',
                '*.nombre.max' => 'El nombre no puede exceder los 255 caracteres.',
                '*.nombre.unique' => 'Ya existe un permiso con este nombre.',
                '*.codigo.required' => 'El código del permiso es obligatorio.',
                '*.codigo.string' => 'El código debe ser una cadena de texto.',
                '*.codigo.max' => 'El código no puede exceder los 50 caracteres.',
                '*.codigo.unique' => 'Ya existe un permiso con este código.',
                '*.icono.string' => 'El icono debe ser una cadena de texto.',
                '*.icono.max' => 'El icono no puede exceder los 50 caracteres.',
                '*.descripcion.string' => 'La descripción debe ser una cadena de texto.',
                '*.descripcion.max' => 'La descripción no puede exceder los 500 caracteres.',
                '*.idPantalla.required' => 'La pantalla asociada es obligatoria.',
                '*.idPantalla.exists' => 'La pantalla seleccionada no existe.',
            ];
        }

        // Si es un solo permiso
        return [
            'nombre.required' => 'El nombre del permiso es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede exceder los 255 caracteres.',
            'nombre.unique' => 'Ya existe un permiso con este nombre.',
            'codigo.required' => 'El código del permiso es obligatorio.',
            'codigo.string' => 'El código debe ser una cadena de texto.',
            'codigo.max' => 'El código no puede exceder los 50 caracteres.',
            'codigo.unique' => 'Ya existe un permiso con este código.',
            'icono.string' => 'El icono debe ser una cadena de texto.',
            'icono.max' => 'El icono no puede exceder los 50 caracteres.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
            'descripcion.max' => 'La descripción no puede exceder los 500 caracteres.',
            'idPantalla.required' => 'La pantalla asociada es obligatoria.',
            'idPantalla.exists' => 'La pantalla seleccionada no existe.',
        ];
    }
}
