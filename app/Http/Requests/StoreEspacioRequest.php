<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEspacioRequest extends FormRequest
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
            'descripcion' => 'sometimes|string|max:2500',
            'permitirJugadores' => 'boolean',
            'minimoJugadores' => 'sometimes|integer|min:0',
            'maximoJugadores' => 'sometimes|integer|min:0',
            'permitirExternos' => 'boolean',
            'sede' => 'required|exists:sedes,id',
            'categoria' => 'required|exists:categorias,id',
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
            'nombre.required' => 'El nombre del espacio es obligatorio.',
            'nombre.string' => 'El nombre del espacio debe ser una cadena de texto.',
            'nombre.max' => 'El nombre del espacio no puede exceder los 255 caracteres.',
            'descripcion.string' => 'La descripción del espacio debe ser una cadena de texto.',
            'descripcion.max' => 'La descripción del espacio no puede exceder los 1000 caracteres.',
            'permiteJugadores.boolean' => 'El campo "Permite Jugadores" debe ser verdadero o falso.',
            'minimoJugadores.integer' => 'El mínimo de jugadores debe ser un número entero.',
            'minimoJugadores.min' => 'El mínimo de jugadores debe ser al menos 0.',
            'maximoJugadores.integer' => 'El máximo de jugadores debe ser un número entero.',
            'maximoJugadores.min' => 'El máximo de jugadores debe ser al menos 0.',
            'permiteExternos.boolean' => 'El campo "Permite Externos" debe ser verdadero o falso.',
            'sede.required' => 'La sede es obligatoria.',
            'sede.exists' => 'La sede seleccionada no existe.',
            'categoria.required' => 'La categoría es obligatoria.',
            'categoria.exists' => 'La categoría seleccionada no existe.',
        ];
    }
}
