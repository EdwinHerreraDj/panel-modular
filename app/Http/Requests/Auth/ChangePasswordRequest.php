<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => [
                'required', 'string', 'confirmed',
                PasswordRules::default(),
                // No sirve volver a poner la misma contraseña.
                Rule::notIn([$this->input('current_password')]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Introduce tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.not_in' => 'La contraseña nueva debe ser distinta de la actual.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => 'contraseña actual',
            'password' => 'contraseña nueva',
        ];
    }
}
