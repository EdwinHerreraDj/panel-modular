<?php

declare(strict_types=1);

namespace App\Support\Installation;

/**
 * Resultado de una comprobación del entorno.
 */
final class Check
{
    private function __construct(
        public readonly string $name,
        public readonly string $level,
        public readonly string $message,
        public readonly ?string $fix = null,
    ) {}

    public static function ok(string $name, string $message): self
    {
        return new self($name, 'ok', $message);
    }

    /**
     * Algo que conviene revisar pero no impide funcionar.
     */
    public static function warning(string $name, string $message, ?string $fix = null): self
    {
        return new self($name, 'warning', $message, $fix);
    }

    /**
     * Algo que va a fallar en producción.
     */
    public static function error(string $name, string $message, ?string $fix = null): self
    {
        return new self($name, 'error', $message, $fix);
    }

    public function isError(): bool
    {
        return $this->level === 'error';
    }

    public function isWarning(): bool
    {
        return $this->level === 'warning';
    }
}
