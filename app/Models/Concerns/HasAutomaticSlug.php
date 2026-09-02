<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\SlugGenerator;

/**
 * Slug generado automáticamente a partir de un campo del modelo.
 *
 * Mientras el contenido no se usa, el slug sigue al nombre: así una errata
 * recién corregida no queda para siempre en la URL. En cuanto empieza a usarse
 * se congela con `lockSlug()` y deja de cambiar solo.
 */
trait HasAutomaticSlug
{
    public static function bootHasAutomaticSlug(): void
    {
        static::saving(function (self $model): void {
            $current = $model->getAttribute('slug');

            // Sin slug no se puede guardar: al crear siempre se genera, aunque
            // el registro nazca ya congelado.
            if ($current === null || $current === '') {
                $model->setAttribute('slug', $model->generateSlug());

                return;
            }

            // Al renombrar solo se regenera si todavía no está congelado.
            if ($model->isDirty($model->slugSource()) && ! $model->slugIsLocked()) {
                $model->setAttribute('slug', $model->generateSlug());
            }
        });
    }

    /**
     * Campo del que se deriva el slug.
     */
    public function slugSource(): string
    {
        return 'name';
    }

    public function slugIsLocked(): bool
    {
        return (bool) $this->getAttribute('is_slug_locked');
    }

    /**
     * Congela el slug. Es irreversible a propósito: una vez publicada, la URL
     * no debe cambiar sola nunca más.
     */
    public function lockSlug(): void
    {
        if ($this->slugIsLocked()) {
            return;
        }

        $this->forceFill(['is_slug_locked' => true])->save();
    }

    /**
     * Vuelve a generar el slug a partir del nombre actual, aunque esté
     * congelado. Solo debe llamarse desde una acción explícita de alguien con
     * permiso, porque cambia una URL pública.
     */
    public function regenerateSlug(): void
    {
        $this->setAttribute('slug', $this->generateSlug());
        $this->save();
    }

    private function generateSlug(): string
    {
        return SlugGenerator::unique(
            (string) $this->getAttribute($this->slugSource()),
            $this->getTable(),
            $this->getKey(),
        );
    }
}
