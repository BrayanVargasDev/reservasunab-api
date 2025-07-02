<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class UpdateEspacioRequest extends FormRequest
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
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'sometimes|string|max:1000',
            'permitirJugadores' => 'sometimes|boolean',
            'minimoJugadores' => 'sometimes|integer|min:0',
            'maximoJugadores' => 'sometimes|integer|min:0',
            'permitirExternos' => 'sometimes|boolean',
            'sede' => 'sometimes|exists:sedes,id',
            'categoria' => 'sometimes|exists:categorias,id',
            'imagen' => 'sometimes|file|mimes:jpeg,png,jpg,gif,svg|max:5120', // 5 MB or data URL
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
            'sede.exists' => 'La sede seleccionada no existe.',
            'categoria.exists' => 'La categoría seleccionada no existe.',
            'imagen.image' => 'El archivo de imagen debe ser una imagen válida.',
            'imagen.mimes' => 'La imagen debe ser de tipo jpeg, png, jpg, gif o svg.',
            'imagen.max' => 'La imagen no puede exceder los 5 MB.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     *
     * @throws \JsonException
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('payload')) {
            try {
                $this->merge(json_decode(
                    (string) $this->input('payload'),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                ));
            } catch (\JsonException $e) {
                Log::error('JSON payload inválido', [
                    'payload' => $this->input('payload'),
                    'msg'     => $e->getMessage(),
                ]);
            }
        }
    }
}
