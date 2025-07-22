<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgregarJugadoresReservaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // La autorización se maneja en el servicio
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'jugadores' => 'required|array|min:1|max:10',
            'jugadores.*' => 'required|integer|distinct|exists:usuarios,id_usuario',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'jugadores.required' => 'Debe proporcionar al menos un jugador.',
            'jugadores.array' => 'Los jugadores deben ser un array.',
            'jugadores.min' => 'Debe agregar al menos un jugador.',
            'jugadores.max' => 'No puede agregar más de 10 jugadores a la vez.',
            'jugadores.*.required' => 'Cada jugador es requerido.',
            'jugadores.*.integer' => 'El ID del jugador debe ser un número entero.',
            'jugadores.*.distinct' => 'No se pueden duplicar jugadores en la lista.',
            'jugadores.*.exists' => 'Uno o más jugadores no existen en el sistema.',
        ];
    }
}
