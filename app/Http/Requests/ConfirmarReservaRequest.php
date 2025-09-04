<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmarReservaRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            // Resumen de iniciarReserva
            'id' => ['nullable', 'integer', 'exists:reservas,id'],
            'id_espacio' => ['required', 'integer'],
            'id_configuracion_base' => ['nullable', 'integer'],
            'fecha' => ['required', 'string'],
            'hora_inicio' => ['required', 'string'],
            'hora_fin' => ['nullable', 'string'],
            'duracion' => ['nullable', 'integer', 'min:1'],
            'jugadores' => ['nullable', 'array'],
            'jugadores.*' => ['integer'],
            'detalles' => ['nullable', 'array'],
            'detalles.*.id' => ['integer', 'exists:elementos,id'],
            'detalles.*.cantidad_seleccionada' => ['integer', 'min:0'],
            'valor_elementos' => ['numeric', 'min:0'],
            'valor' => ['numeric', 'min:0'],
            'valor_descuento' => ['numeric', 'min:0'],
            'valor_total_reserva' => ['numeric', 'min:0'],
        ];
    }

    public function messages()
    {
        return [
            'id_espacio.required' => 'El espacio es requerido.',
            'fecha.required' => 'La fecha es requerida.',
            'hora_inicio.required' => 'La hora de inicio es requerida.',
            'detalles.*.id.exists' => 'El elemento seleccionado no es válido.',
            'id.exists' => 'La reserva seleccionada no es válida.',
        ];
    }
}
