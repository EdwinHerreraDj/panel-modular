<?php

declare(strict_types=1);

namespace Modules\Blog\Actions;

use App\Enums\RevisionType;
use App\Models\Revision;
use App\Models\User;
use Modules\Blog\Models\Article;

/**
 * Guarda una instantánea del artículo en el historial.
 *
 * La instantánea incluye la clasificación y los relacionados, de modo que
 * restaurarla devuelva el artículo exactamente al estado que tenía.
 */
final class RecordArticleRevision
{
    /**
     * Versión guardada al pulsar Guardar.
     */
    public function manual(Article $article, ?User $author): Revision
    {
        $revision = Revision::create([
            'revisionable_type' => $article->getMorphClass(),
            'revisionable_id' => $article->getKey(),
            'user_id' => $author?->getKey(),
            'type' => RevisionType::Manual,
            'snapshot' => $this->snapshot($article),
        ]);

        $this->prune($article);

        return $revision;
    }

    /**
     * Copia automática mientras se escribe.
     *
     * Solo se conserva la última: se reemplaza en cada guardado automático
     * para no llenar el historial de estados intermedios.
     *
     * @param  array<string, mixed>  $data
     */
    public function autosave(Article $article, ?User $author, array $data): Revision
    {
        $article->revisions()
            ->where('type', RevisionType::Autosave->value)
            ->delete();

        return Revision::create([
            'revisionable_type' => $article->getMorphClass(),
            'revisionable_id' => $article->getKey(),
            'user_id' => $author?->getKey(),
            'type' => RevisionType::Autosave,
            'snapshot' => [...$this->snapshot($article), ...$data],
        ]);
    }

    /**
     * Estado completo del artículo, tal y como está en base de datos.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Article $article): array
    {
        return [
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'content' => $article->content,
            'status' => $article->status->value,
            'visibility' => $article->visibility->value,
            'published_at' => $article->published_at?->toIso8601String(),
            'scheduled_for' => $article->scheduled_for?->toIso8601String(),
            'position' => $article->position,
            'featured_media_id' => $article->featured_media_id,
            'featured_image_alt' => $article->featured_image_alt,
            'meta_title' => $article->meta_title,
            'meta_description' => $article->meta_description,
            'canonical_url' => $article->canonical_url,
            'og_title' => $article->og_title,
            'og_description' => $article->og_description,
            'is_indexable' => $article->is_indexable,
            'category_ids' => $article->categories()->pluck('categories.id')->all(),
            'tag_ids' => $article->tags()->pluck('tags.id')->all(),
            'related_ids' => $article->related()->pluck('articles.id')->all(),
        ];
    }

    /**
     * Descarta las versiones manuales más antiguas por encima del límite.
     */
    private function prune(Article $article): void
    {
        $keep = max(1, (int) config('articles.max_revisions', 30));

        // Identificador de la versión más antigua que se conserva. Al ser
        // autoincremental, ordenar por id equivale a ordenar por fecha.
        $oldestKeptId = $article->revisions()
            ->where('type', RevisionType::Manual->value)
            ->orderByDesc('id')
            ->skip($keep - 1)
            ->take(1)
            ->value('id');

        if ($oldestKeptId === null) {
            return;
        }

        $article->revisions()
            ->where('type', RevisionType::Manual->value)
            ->where('id', '<', $oldestKeptId)
            ->delete();
    }
}
