<?php

declare(strict_types=1);

namespace Modules\Blog;

use App\Modules\Module;
use App\Modules\ModuleProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Blog\Console\Commands\PublishScheduledArticles;
use Modules\Blog\Models\Article;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Tag;
use Modules\Blog\Policies\ArticlePolicy;
use Modules\Blog\Policies\CategoryPolicy;
use Modules\Blog\Policies\TagPolicy;

class ModuleServiceProvider extends ModuleProvider
{
    public function register(): void
    {
        // Los ajustes del blog viajan con el blog. Se fusionan, así que una
        // instalación puede seguir sobrescribiéndolos desde su `.env`.
        $this->mergeConfigFrom($this->modulePath('config/articles.php'), 'articles');
    }

    public function module(): Module
    {
        return new BlogModule;
    }

    /**
     * Fuera de `App\`, Laravel no descubre las policies solo.
     */
    protected function policies(): array
    {
        return [
            Article::class => ArticlePolicy::class,
            Category::class => CategoryPolicy::class,
            Tag::class => TagPolicy::class,
        ];
    }

    protected function consoleCommands(): array
    {
        return [PublishScheduledArticles::class];
    }

    protected function schedule(Schedule $schedule): void
    {
        // Cada minuto, para que la hora de publicación se respete con
        // precisión. Sin cron lo cubre `deferredTasks()`, con la contrapartida
        // de que se publica cuando alguien visita el panel.
        $schedule->command('articulos:publicar-programados')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();
    }
}
