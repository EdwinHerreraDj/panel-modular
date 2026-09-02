<?php

declare(strict_types=1);

namespace Modules\Blog\Actions;

use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Blog\Enums\ArticleStatus;
use Modules\Blog\Models\Article;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Tag;

/**
 * Publica los artículos programados cuya fecha ya ha llegado.
 *
 * La usan tanto el comando del planificador como la red de seguridad que
 * comprueba lo pendiente durante las visitas al panel, para que la
 * programación funcione también en alojamientos sin cron.
 */
final class PublishDueArticles
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /**
     * @return Collection<int, Article> Artículos publicados en esta pasada.
     */
    public function handle(): Collection
    {
        $due = Article::query()
            ->where('status', ArticleStatus::Scheduled->value)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->get();

        foreach ($due as $article) {
            DB::transaction(function () use ($article): void {
                $article->forceFill([
                    'status' => ArticleStatus::Published,
                    'published_at' => $article->scheduled_for,
                    'scheduled_for' => null,
                ])->save();

                // Al hacerse público, ni su dirección ni las de sus categorías
                // y etiquetas deben cambiar ya solas.
                $article->lockSlug();
                $article->categories()->get()->each(fn (Category $category) => $category->lockSlug());
                $article->tags()->get()->each(fn (Tag $tag) => $tag->lockSlug());

                // Sin usuario en sesión el evento queda sin autor, y la
                // descripción indica que fue automático.
                $this->recordActivity->handle(
                    $article,
                    ActivityEvent::Updated,
                    "Publicación automática del artículo «{$article->title}»",
                );
            });
        }

        return $due;
    }

    /**
     * ¿Hay algo pendiente de publicar? Consulta barata sobre un índice.
     */
    public function hasDue(): bool
    {
        return Article::query()
            ->where('status', ArticleStatus::Scheduled->value)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->exists();
    }
}
