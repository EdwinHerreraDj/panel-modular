<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // La ruta ya exige el permiso apikeys.manage.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'allowed_origins' => ['nullable', 'array', 'max:20'],
            'allowed_origins.*' => ['string', 'max:255'],
            'rate_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'allowed_origins' => 'dominios permitidos',
            'allowed_origins.*' => 'dominio',
            'rate_limit' => 'límite de peticiones',
            'expires_at' => 'fecha de caducidad',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Pon un nombre que te recuerde para qué es esta clave.',
            'expires_at.after' => 'La fecha de caducidad tiene que ser posterior a hoy.',
            'rate_limit.min' => 'El límite tiene que ser de al menos 1 petición por minuto.',
        ];
    }

    /**
     * Cada dominio debe ser un origen completo: esquema y servidor, sin ruta.
     * Es lo que el navegador envía en la cabecera `Origin`, y compararlo con
     * cualquier otra cosa haría que el CORS no encajara nunca.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var list<string> $origins */
                $origins = $this->input('allowed_origins', []) ?? [];

                foreach ($origins as $index => $origin) {
                    $origin = trim($origin);

                    if ($origin === '') {
                        continue;
                    }

                    $parts = parse_url($origin);

                    $valid = $parts !== false
                        && isset($parts['scheme'], $parts['host'])
                        && in_array($parts['scheme'], ['http', 'https'], true)
                        && ! isset($parts['path'][1]);   // «/» sí, «/algo» no

                    if (! $valid) {
                        $validator->errors()->add(
                            "allowed_origins.{$index}",
                            'Escribe el dominio completo, por ejemplo https://www.tuweb.com (sin nada detrás).',
                        );
                    }
                }
            },
        ];
    }

    /**
     * @return list<string>
     */
    public function origins(): array
    {
        /** @var list<string> $origins */
        $origins = $this->input('allowed_origins', []) ?? [];

        return array_values(array_unique(array_filter(
            array_map(fn (string $origin): string => rtrim(trim($origin), '/'), $origins),
            fn (string $origin): bool => $origin !== '',
        )));
    }
}
