<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Restricciones de una imagen subible, leídas de `config/media.php`.
 */
final readonly class ImageConstraints
{
    /**
     * @param  list<string>  $mimeTypes
     */
    private function __construct(
        public string $directory,
        public array $mimeTypes,
        public int $maxKilobytes,
        public int $minWidth,
        public int $minHeight,
        public int $maxWidth,
        public int $maxHeight,
    ) {}

    public static function for(string $type): self
    {
        /** @var array<string, mixed>|null $config */
        $config = config("media.images.{$type}");

        if ($config === null) {
            throw new InvalidArgumentException("No hay restricciones definidas para «{$type}».");
        }

        /** @var list<string> $mimeTypes */
        $mimeTypes = $config['mimetypes'];

        return new self(
            directory: (string) $config['directory'],
            mimeTypes: $mimeTypes,
            maxKilobytes: (int) $config['max_kilobytes'],
            minWidth: (int) $config['min_width'],
            minHeight: (int) $config['min_height'],
            maxWidth: (int) $config['max_width'],
            maxHeight: (int) $config['max_height'],
        );
    }

    /**
     * Reglas de validación de Laravel equivalentes.
     *
     * `mimetypes` comprueba el contenido real del archivo mediante finfo, no la
     * extensión que envía el navegador.
     *
     * @return list<string>
     */
    public function validationRules(): array
    {
        return [
            'file',
            'image',
            'mimetypes:'.implode(',', $this->mimeTypes),
            'max:'.$this->maxKilobytes,
            sprintf(
                'dimensions:min_width=%d,min_height=%d,max_width=%d,max_height=%d',
                $this->minWidth,
                $this->minHeight,
                $this->maxWidth,
                $this->maxHeight,
            ),
        ];
    }

    /**
     * Formatos admitidos, en texto, para los mensajes y la ayuda del formulario.
     */
    public function formatsLabel(): string
    {
        /** @var array<string, string> $extensions */
        $extensions = config('media.extensions');

        $labels = array_map(
            fn (string $mime): string => strtoupper($extensions[$mime] ?? $mime),
            $this->mimeTypes,
        );

        return implode(', ', $labels);
    }

    /**
     * Resumen legible de los requisitos, para mostrarlo bajo el campo.
     */
    public function hint(): string
    {
        return sprintf(
            '%s · hasta %s · entre %d×%d y %d×%d px',
            $this->formatsLabel(),
            $this->maxKilobytes >= 1024
                ? round($this->maxKilobytes / 1024, 1).' MB'
                : $this->maxKilobytes.' KB',
            $this->minWidth,
            $this->minHeight,
            $this->maxWidth,
            $this->maxHeight,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'formats' => $this->formatsLabel(),
            'max_kilobytes' => $this->maxKilobytes,
            'hint' => $this->hint(),
        ];
    }
}
