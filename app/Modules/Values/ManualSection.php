<?php

declare(strict_types=1);

namespace App\Modules\Values;

/**
 * Una sección del manual del cliente.
 *
 * Misma forma que las del núcleo, para que la pantalla de Ayuda y el archivo
 * descargable no tengan que distinguir de dónde sale cada una.
 */
final readonly class ManualSection
{
    /**
     * @param  string|null  $permission  Permiso necesario para que le sirva de algo
     * @param  list<array{type: string, content: mixed}>  $blocks
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $icon,
        public ?string $permission,
        public array $blocks,
        public int $position = 100,
    ) {}

    /**
     * @return array{key: string, title: string, icon: string, permission: string|null, blocks: list<array{type: string, content: mixed}>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'icon' => $this->icon,
            'permission' => $this->permission,
            'blocks' => $this->blocks,
        ];
    }
}
