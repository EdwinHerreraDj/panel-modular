<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Actions\RecordSlugChange;

/**
 * Deja una redirección cada vez que cambia la dirección del contenido.
 *
 * Lo usa cualquier modelo con slug que se publique en la web: un artículo y un
 * producto pierden posiciones en Google exactamente igual si su dirección
 * cambia sin dejar rastro.
 *
 * Se engancha a `updated` y no a `saving` porque solo interesa cuando el cambio
 * ya está guardado: si la transacción se deshace, no debe quedar una
 * redirección apuntando a una dirección que nunca existió.
 *
 * Va siempre acompañado de HasAutomaticSlug: de ahí salen el slug y el
 * candado que indica que la dirección ya fue pública.
 */
trait RecordsSlugRedirects
{
    public static function bootRecordsSlugRedirects(): void
    {
        static::updated(function (self $model): void {
            if (! $model->wasChanged('slug')) {
                return;
            }

            // Solo interesan las direcciones que llegaron a ser públicas. Un
            // borrador renombrado cinco veces mientras se escribe dejaría cinco
            // redirecciones hacia direcciones que nadie ha visto nunca, y esa
            // lista se la descarga entera la web pública.
            //
            // El slug se congela al publicar y no se descongela, así que estar
            // congelado equivale a «esta dirección estuvo publicada».
            if (! $model->slugIsLocked()) {
                return;
            }

            $previous = $model->getOriginal('slug');

            if (! is_string($previous) || $previous === '') {
                return;
            }

            app(RecordSlugChange::class)->handle($model, $previous, $model->slug);
        });
    }
}
