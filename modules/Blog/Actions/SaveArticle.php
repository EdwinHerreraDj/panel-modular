<?php

declare(strict_types=1);

namespace Modules\Blog\Actions;

use App\Actions\Media\SyncMediaUsage;
use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Enums\RevisionType;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Modules\Blog\Enums\ArticleStatus;
use Modules\Blog\Models\Article;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Tag;

/**
 * Alta y modificación de un artículo.
 *
 * Concentra lo que no puede olvidarse en ningún camino: sanitizar el HTML,
 * recalcular el tiempo de lectura, fijar la fecha de publicación al publicar y
 * congelar los slugs de lo que pasa a ser público.
 */
final class SaveArticle
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
        private readonly RecordActivity $recordActivity,
        private readonly RecordArticleRevision $recordRevision,
        private readonly SyncMediaUsage $syncMediaUsage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Article $article, array $data, User $actor): Article
    {
        return DB::transaction(function () use ($article, $data, $actor): Article {
            $isNew = $article === null;
            $article ??= new Article;

            $previousStatus = $isNew ? null : $article->status;

            // El HTML nunca se guarda tal cual llega del navegador.
            $content = $this->sanitizer->sanitize($data['content'] ?? null);

            $article->fill([
                'title' => $data['title'],
                'excerpt' => $data['excerpt'] ?? null,
                'visibility' => $data['visibility'],
                'position' => $data['position'] ?? 0,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'canonical_url' => $data['canonical_url'] ?? null,
                'og_title' => $data['og_title'] ?? null,
                'og_description' => $data['og_description'] ?? null,
                'featured_image_alt' => $data['featured_image_alt'] ?? null,
                'is_indexable' => $data['is_indexable'] ?? true,
            ]);

            $article->content = $content;
            $article->content_text = $this->sanitizer->toPlainText($content);
            $article->reading_minutes = $this->sanitizer->readingMinutes($content);

            if ($isNew) {
                $article->author_id = $actor->getKey();
            }

            $this->applyStatus($article, ArticleStatus::from((string) $data['status']), $data);

            // La imagen destacada es ahora una referencia a la biblioteca.
            $article->featured_media_id = $data['featured_media_id'] ?? null;

            $article->save();

            $this->syncMediaUsage->set($article, 'featured', $article->featured_media_id);
            $this->syncTaxonomies($article, $data);

            // Cuando el artículo es público, sus categorías y etiquetas ya no
            // pueden cambiar de dirección sin romper enlaces.
            if ($article->isPublished()) {
                $this->lockTaxonomySlugs($article);
                $article->lockSlug();
            }

            // Cada guardado deja su versión en el historial, y la copia
            // automática pendiente deja de tener sentido.
            //
            // Se borra por tipo y no a través de la relación `autosave`, que al
            // ser un `latestOfMany` genera una consulta con subconsulta que no
            // se traduce de forma fiable a un DELETE.
            $this->recordRevision->manual($article, $actor);
            $article->revisions()->where('type', RevisionType::Autosave->value)->delete();

            $this->recordActivity->handle(
                $article,
                $isNew ? ActivityEvent::Created : ActivityEvent::Updated,
                $this->describe($article, $isNew, $previousStatus),
            );

            return $article;
        });
    }

    /**
     * Aplica el estado y las fechas que lleva asociadas.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyStatus(Article $article, ArticleStatus $status, array $data): void
    {
        $article->status = $status;

        $article->scheduled_for = $status === ArticleStatus::Scheduled
            ? ($data['scheduled_for'] ?? null)
            : null;

        if ($status === ArticleStatus::Published) {
            // Se respeta la fecha indicada; si no hay ninguna, se sella ahora.
            $article->published_at = $data['published_at'] ?? $article->published_at ?? now();

            return;
        }

        if ($status === ArticleStatus::Archived) {
            // Se conserva la fecha original: el artículo estuvo publicado.
            return;
        }

        // Al volver a borrador o a revisión deja de estar publicado.
        if ($status !== ArticleStatus::Scheduled) {
            $article->published_at = $data['published_at'] ?? null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncTaxonomies(Article $article, array $data): void
    {
        if (array_key_exists('category_ids', $data)) {
            $article->categories()->sync($data['category_ids'] ?? []);
        }

        if (array_key_exists('tag_ids', $data)) {
            $article->tags()->sync($data['tag_ids'] ?? []);
        }

        if (array_key_exists('related_ids', $data)) {
            /** @var list<int> $related */
            $related = $data['related_ids'] ?? [];

            // Se descarta el propio artículo por si llegara en la petición: la
            // validación ya lo impide, pero esta acción también se usa fuera.
            $related = array_values(array_filter(
                $related,
                fn (int $id): bool => $id !== $article->getKey(),
            ));

            $article->related()->sync(
                array_combine($related, array_map(
                    fn (int $position): array => ['position' => $position],
                    array_keys($related),
                )),
            );
        }
    }

    /**
     * Congela el slug de las categorías y etiquetas del artículo publicado.
     */
    private function lockTaxonomySlugs(Article $article): void
    {
        $article->categories()->get()->each(fn (Category $category) => $category->lockSlug());
        $article->tags()->get()->each(fn (Tag $tag) => $tag->lockSlug());
    }

    private function describe(Article $article, bool $isNew, ?ArticleStatus $previous): string
    {
        if ($isNew) {
            return sprintf('Alta del artículo «%s» en estado %s', $article->title, $article->status->label());
        }

        if ($previous !== null && $previous !== $article->status) {
            return sprintf(
                'Artículo «%s»: %s → %s',
                $article->title,
                $previous->label(),
                $article->status->label(),
            );
        }

        return sprintf('Modificación del artículo «%s»', $article->title);
    }
}
