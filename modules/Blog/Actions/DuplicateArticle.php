<?php

declare(strict_types=1);

namespace Modules\Blog\Actions;

use App\Actions\Media\SyncMediaUsage;
use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Blog\Enums\ArticleStatus;
use Modules\Blog\Models\Article;

/**
 * Crea una copia de un artículo lista para editar.
 *
 * La copia nace siempre como borrador y sin fechas: duplicar no debe publicar
 * nada por su cuenta.
 */
final class DuplicateArticle
{
    public function __construct(
        private readonly RecordActivity $recordActivity,
        private readonly SyncMediaUsage $syncMediaUsage,
    ) {}

    public function handle(Article $original, User $actor): Article
    {
        return DB::transaction(function () use ($original, $actor): Article {
            $copy = new Article;

            $copy->fill([
                'title' => Str::limit($original->title.' (copia)', 200, ''),
                'excerpt' => $original->excerpt,
                'visibility' => $original->visibility,
                'position' => $original->position,
                'featured_image_alt' => $original->featured_image_alt,
                'meta_title' => $original->meta_title,
                'meta_description' => $original->meta_description,
                // La URL canónica no se copia: apuntaría al original y la copia
                // quedaría marcada como duplicado desde el primer día.
                'canonical_url' => null,
                'og_title' => $original->og_title,
                'og_description' => $original->og_description,
                'is_indexable' => $original->is_indexable,
            ]);

            $copy->content = $original->content;
            $copy->content_text = $original->content_text;
            $copy->reading_minutes = $original->reading_minutes;
            $copy->author_id = $actor->getKey();
            $copy->status = ArticleStatus::Draft;
            $copy->published_at = null;
            $copy->scheduled_for = null;
            // El slug se genera del título nuevo y queda libre para seguir
            // cambiando mientras la copia no se publique.
            $copy->is_slug_locked = false;

            // La imagen destacada se comparte: la biblioteca existe justo para
            // reutilizar archivos entre artículos, y el registro de usos evita
            // que se elimine mientras alguno la necesite.
            $copy->featured_media_id = $original->featured_media_id;

            $copy->save();

            $this->syncMediaUsage->set($copy, 'featured', $copy->featured_media_id);

            $copy->categories()->sync($original->categories()->pluck('categories.id')->all());
            $copy->tags()->sync($original->tags()->pluck('tags.id')->all());
            $copy->related()->sync($original->related()->pluck('articles.id')->all());

            $this->recordActivity->handle(
                $copy,
                ActivityEvent::Created,
                sprintf('Artículo «%s» duplicado a partir de «%s»', $copy->title, $original->title),
            );

            return $copy;
        });
    }
}
