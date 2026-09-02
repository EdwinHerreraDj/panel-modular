<?php

declare(strict_types=1);

namespace Modules\Blog\Actions;

use App\Actions\Media\SyncMediaUsage;
use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Models\Media;
use App\Models\Revision;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Modules\Blog\Enums\ArticleVisibility;
use Modules\Blog\Models\Article;

/**
 * Devuelve un artículo al estado de una versión anterior.
 *
 * Antes de sobrescribir se guarda una versión del estado actual: restaurar por
 * error no debe hacer perder nada.
 *
 * El estado editorial **no se restaura**. Recuperar un texto antiguo no debe
 * despublicar ni publicar nada por su cuenta: eso sigue siendo una decisión
 * explícita con su permiso.
 */
final class RestoreArticleRevision
{
    public function __construct(
        private readonly RecordArticleRevision $recordRevision,
        private readonly RecordActivity $recordActivity,
        private readonly HtmlSanitizer $sanitizer,
        private readonly SyncMediaUsage $syncMediaUsage,
    ) {}

    public function handle(Article $article, Revision $revision, User $actor): Article
    {
        return DB::transaction(function () use ($article, $revision, $actor): Article {
            // Salvaguarda del estado actual antes de pisarlo.
            $this->recordRevision->manual($article, $actor);

            $snapshot = $revision->snapshot;

            // El contenido vuelve a pasar por el sanitizador: la instantánea
            // pudo guardarse con otras reglas de las vigentes hoy.
            $content = $this->sanitizer->sanitize($snapshot['content'] ?? null);

            $article->fill([
                'title' => $snapshot['title'] ?? $article->title,
                'excerpt' => $snapshot['excerpt'] ?? null,
                'visibility' => ArticleVisibility::tryFrom((string) ($snapshot['visibility'] ?? ''))
                    ?? $article->visibility,
                'position' => $snapshot['position'] ?? 0,
                'featured_image_alt' => $snapshot['featured_image_alt'] ?? null,
                'meta_title' => $snapshot['meta_title'] ?? null,
                'meta_description' => $snapshot['meta_description'] ?? null,
                'canonical_url' => $snapshot['canonical_url'] ?? null,
                'og_title' => $snapshot['og_title'] ?? null,
                'og_description' => $snapshot['og_description'] ?? null,
                'is_indexable' => $snapshot['is_indexable'] ?? true,
            ]);

            $article->content = $content;
            $article->content_text = $this->sanitizer->toPlainText($content);
            $article->reading_minutes = $this->sanitizer->readingMinutes($content);
            // La imagen destacada puede haberse eliminado de la biblioteca
            // desde que se guardó la versión: si ya no está, se deja vacía.
            $mediaId = $snapshot['featured_media_id'] ?? null;
            $article->featured_media_id = $mediaId !== null && Media::query()->whereKey($mediaId)->exists()
                ? $mediaId
                : null;

            $article->save();

            $this->syncMediaUsage->set($article, 'featured', $article->featured_media_id);

            $article->categories()->sync($snapshot['category_ids'] ?? []);
            $article->tags()->sync($snapshot['tag_ids'] ?? []);
            $article->related()->sync($snapshot['related_ids'] ?? []);

            $this->recordActivity->handle(
                $article,
                ActivityEvent::Updated,
                sprintf(
                    'Restaurada la versión del %s del artículo «%s»',
                    $revision->created_at?->format('d/m/Y H:i') ?? 'desconocida',
                    $article->title,
                ),
            );

            return $article;
        });
    }
}
