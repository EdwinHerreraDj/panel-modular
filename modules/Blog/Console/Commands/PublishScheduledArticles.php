<?php

declare(strict_types=1);

namespace Modules\Blog\Console\Commands;

use Illuminate\Console\Command;
use Modules\Blog\Actions\PublishDueArticles;
use Modules\Blog\Models\Article;

/**
 * Publica los artículos programados cuya fecha ya ha llegado.
 *
 * Pensado para el planificador, con una única entrada en cron. Es idempotente:
 * si se lanza dos veces seguidas, la segunda no encuentra nada que hacer.
 */
class PublishScheduledArticles extends Command
{
    protected $signature = 'articulos:publicar-programados';

    protected $description = 'Publica los artículos programados cuya fecha de publicación ya ha pasado';

    public function handle(PublishDueArticles $publish): int
    {
        $published = $publish->handle();

        if ($published->isEmpty()) {
            $this->info('No hay artículos programados pendientes de publicar.');

            return self::SUCCESS;
        }

        $published->each(fn (Article $article) => $this->line("Publicado: {$article->title}"));

        $this->info("Artículos publicados: {$published->count()}.");

        return self::SUCCESS;
    }
}
