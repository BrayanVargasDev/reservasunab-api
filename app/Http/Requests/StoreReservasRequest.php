<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservasRequest extends FormRequest
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
            'base' => ['required', 'array'],
            'fecha' => ['required', 'string'],
            'horaInicio' => ['required', 'string'],
            'horaFin' => ['required', 'string'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        return [
            'base.required' => 'Los datos base son requeridos.',
            'fechaBase.required' => 'La fecha base es requerida.',
            'horaInicio.required' => 'La hora de inicio es requerida.',
            'horaFin.required' => 'La hora de fin es requerida.',
        ];
    }
}
