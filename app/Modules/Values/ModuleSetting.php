<?php

declare(strict_types=1);

namespace App\Modules\Values;

/**
 * Un ajuste propio de un módulo, tal y como se pinta en Configuración.
 *
 * Tres tipos y nada más: número, texto y sí/no. Lo justo para un puñado de
 * preferencias. Un módulo que necesite algo más elaborado —una imagen, una
 * lista— se hace su propia pantalla en lugar de estirar esto hasta convertirlo
 * en un constructor de formularios.
 */
final readonly class ModuleSetting
{
    public const NUMBER = 'number';

    public const TEXT = 'text';

    public const BOOLEAN = 'boolean';

    /**
     * @param  string  $key  Clave dentro del módulo, sin prefijar
     * @param  list<string>  $rules  Reglas de validación de Laravel
     * @param  string|null  $hint  Texto de ayuda bajo el campo
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type = self::TEXT,
        public array $rules = [],
        public string|int|bool|null $default = null,
        public ?string $hint = null,
    ) {}

    /**
     * Convierte lo que llega del formulario al tipo declarado.
     */
    public function cast(mixed $value): string|int|bool|null
    {
        return match ($this->type) {
            self::NUMBER => $value === null || $value === '' ? null : (int) $value,
            self::BOOLEAN => (bool) $value,
            default => $value === null ? null : (string) $value,
        };
    }
}
