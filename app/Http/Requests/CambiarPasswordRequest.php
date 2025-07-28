<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class CambiarPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'currentPassword' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!Hash::check($value, Auth::user()->password_hash)) {
                        $fail('La contraseña actual no es correcta.');
                    }
                },
            ],
            'newPassword' => [
                'required',
                'string',
                'min:8',
                'different:currentPassword'
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'currentPassword.required' => 'La contraseña actual es obligatoria.',
            'newPassword.required' => 'La nueva contraseña es obligatoria.',
            'newPassword.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'newPassword.different' => 'La nueva contraseña debe ser diferente a la actual.',
        ];
    }
}
