<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Requisitos de contraseña que se muestran en los formularios.
 *
 * Vive aparte porque los piden varias pantallas (alta de usuario, cambio de
 * contraseña, restablecimiento) y todas deben enseñar exactamente lo mismo que
 * luego valida el servidor.
 */
class PasswordPolicy
{
    /**
     * @return array{min_length: int, require_mixed_case: bool, require_numbers: bool, require_symbols: bool}
     */
    public static function forDisplay(): array
    {
        /** @var array<string, mixed> $config */
        $config = config('security.passwords');

        return [
            'min_length' => (int) $config['min_length'],
            'require_mixed_case' => (bool) $config['require_mixed_case'],
            'require_numbers' => (bool) $config['require_numbers'],
            'require_symbols' => (bool) $config['require_symbols'],
        ];
    }
}
