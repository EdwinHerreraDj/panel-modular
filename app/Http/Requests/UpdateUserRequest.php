<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required', 'string', 'email:filter', 'max:190', 'lowercase',
                Rule::unique('users', 'email')->ignoreModel($target),
            ],
            'role' => ['required', new Enum(RoleName::class)],
            'status' => ['required', new Enum(UserStatus::class)],
        ];
    }

    /**
     * Comprobaciones que dependen de quién edita a quién.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User $target */
                $target = $this->route('user');
                $editor = $this->user();

                if ($editor === null) {
                    return;
                }

                // Nadie cambia su propio rol ni su propio estado: es la vía
                // habitual de escalada de privilegios y de bloqueo accidental.
                if ($editor->is($target)) {
                    if ($this->input('role') !== $target->roleName()?->value) {
                        $validator->errors()->add('role', 'No puedes cambiar tu propio rol.');
                    }

                    if ($this->input('status') !== $target->status->value) {
                        $validator->errors()->add('status', 'No puedes cambiar tu propio estado.');
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe una cuenta con ese correo electrónico.',
            'role.required' => 'Selecciona un rol.',
            'status.required' => 'Selecciona un estado.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'role' => 'rol',
            'status' => 'estado',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }
}
