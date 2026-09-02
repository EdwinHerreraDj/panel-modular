<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Redirect;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Guarda una redirección cuando cambia la dirección de un contenido.
 *
 * Vale para cualquier modelo: un producto que cambia de dirección rompe los
 * enlaces igual que un artículo, y la tabla de redirecciones es del núcleo.
 *
 * Lo que parece obvio —crear una fila de la vieja a la nueva— es lo fácil. Lo
 * que hay que cuidar es todo lo demás:
 *
 * - **Cadenas**: si `a` ya apuntaba a `b` y `b` pasa a ser `c`, hay que dejar
 *   `a → c`, no `a → b → c`. Un navegador solo sigue unos pocos saltos, y cada
 *   uno cuesta una petición.
 * - **Bucles**: si la dirección vuelve a un valor anterior, la redirección que
 *   salía de él sobra y se borra. Si no, la web mandaría al visitante fuera del
 *   contenido que acaba de encontrar.
 * - **Direcciones ocupadas**: si la dirección nueva tenía una redirección
 *   propia, se elimina: ahora hay un contenido de verdad ahí.
 */
final class RecordSlugChange
{
    public function handle(Model $model, string $previousSlug, string $newSlug): ?Redirect
    {
        $from = Redirect::normalize($previousSlug);
        $to = Redirect::normalize($newSlug);

        if ($from === '' || $to === '' || $from === $to) {
            return null;
        }

        return DB::transaction(function () use ($model, $from, $to): Redirect {
            // La dirección nueva ya no puede redirigir a ningún sitio: a partir
            // de ahora ahí vive el contenido.
            Redirect::query()->where('from_path', $to)->delete();

            // Lo que apuntaba a la vieja pasa a apuntar a la nueva, en un solo
            // salto.
            Redirect::query()
                ->where('to_path', $from)
                ->where('from_path', '!=', $to)
                ->update(['to_path' => $to]);

            // Y la de siempre. `updateOrCreate` porque la dirección vieja pudo
            // haberse usado antes.
            return Redirect::updateOrCreate(
                ['from_path' => $from],
                [
                    'to_path' => $to,
                    'status_code' => 301,
                    'is_automatic' => true,
                    'redirectable_type' => $model->getMorphClass(),
                    'redirectable_id' => $model->getKey(),
                ],
            );
        });
    }
}
