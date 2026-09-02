<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Redirect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRedirectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // La ruta ya exige el permiso.
    }

    /**
     * Se normaliza antes de validar, no después.
     *
     * Si se validara el texto tal cual llega, «/Repetida/» pasaría la
     * comprobación de único y luego chocaría con «repetida» al guardar, que es
     * la misma dirección escrita de otra forma.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'from_path' => Redirect::normalize((string) $this->input('from_path')),
            'to_path' => Redirect::normalize((string) $this->input('to_path')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_path' => ['required', 'string', 'max:500', Rule::unique('redirects', 'from_path')],
            'to_path' => ['required', 'string', 'max:500'],
            'status_code' => ['nullable', Rule::in([301, 302])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'from_path' => 'dirección antigua',
            'to_path' => 'dirección nueva',
            'status_code' => 'tipo de redirección',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from_path.required' => 'Escribe la dirección antigua que quieres redirigir.',
            'from_path.unique' => 'Ya existe una redirección para esa dirección antigua.',
            'to_path.required' => 'Escribe a dónde debe llevar.',
        ];
    }

    /**
     * Una redirección a sí misma manda al visitante en círculos.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $from = (string) $this->input('from_path');
                $to = (string) $this->input('to_path');

                if ($from !== '' && $from === $to) {
                    $validator->errors()->add(
                        'to_path',
                        'La dirección nueva no puede ser la misma que la antigua.',
                    );
                }
            },
        ];
    }

    /**
     * @return array{from_path: string, to_path: string, status_code: int, is_automatic: false}
     */
    public function toRedirect(): array
    {
        return [
            // Ya vienen normalizadas de prepareForValidation().
            'from_path' => (string) $this->string('from_path'),
            'to_path' => (string) $this->string('to_path'),
            'status_code' => (int) ($this->input('status_code') ?? 301),
            'is_automatic' => false,
        ];
    }
}
