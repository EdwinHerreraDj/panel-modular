<?php

declare(strict_types=1);

namespace Modules\Blog;

use App\Enums\RoleName;
use App\Models\User;
use App\Modules\BaseModule;
use App\Modules\ModuleSettings;
use App\Modules\Values\ApiEndpoint;
use App\Modules\Values\ContentType;
use App\Modules\Values\DashboardAction;
use App\Modules\Values\DashboardAlert;
use App\Modules\Values\DashboardCard;
use App\Modules\Values\DashboardContribution;
use App\Modules\Values\DashboardWidget;
use App\Modules\Values\DeferredTask;
use App\Modules\Values\ManualSection;
use App\Modules\Values\MenuItem;
use App\Modules\Values\ModulePermission;
use App\Modules\Values\ModuleSetting;
use App\Modules\Values\PublicCount;
use Illuminate\Database\Eloquent\Builder;
use Modules\Blog\Actions\PublishDueArticles;
use Modules\Blog\Enums\ArticleStatus;
use Modules\Blog\Enums\BlogPermission;
use Modules\Blog\Models\Article;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Tag;

/**
 * El blog como módulo.
 *
 * Durante la conversión, el código del blog todavía vive en `app/` y solo sus
 * declaraciones están aquí. La fase siguiente mueve los archivos sin tener que
 * volver a declarar nada.
 */
class BlogModule extends BaseModule
{
    public function key(): string
    {
        return 'blog';
    }

    public function name(): string
    {
        return 'Blog';
    }

    /**
     * Quién recibe cada permiso al sembrar.
     *
     * El superadministrador no aparece: lo resuelve antes el `Gate::before`, y
     * el sembrador le concede todo de todas formas.
     */
    public function permissions(): array
    {
        $roles = [
            BlogPermission::ArticlesView->value => [RoleName::Editor, RoleName::Reviewer, RoleName::Author, RoleName::Reader],
            BlogPermission::ArticlesCreate->value => [RoleName::Editor, RoleName::Author],
            BlogPermission::ArticlesUpdateOwn->value => [RoleName::Author],
            BlogPermission::ArticlesUpdateAny->value => [RoleName::Editor],
            BlogPermission::ArticlesDelete->value => [RoleName::Editor],
            BlogPermission::ArticlesRestore->value => [RoleName::Editor],
            BlogPermission::ArticlesPublish->value => [RoleName::Editor],
            BlogPermission::ArticlesSchedule->value => [RoleName::Editor],
            BlogPermission::ArticlesApprove->value => [RoleName::Editor, RoleName::Reviewer],
            BlogPermission::CategoriesManage->value => [RoleName::Editor],
            BlogPermission::TagsManage->value => [RoleName::Editor],
        ];

        return array_map(
            fn (BlogPermission $permission): ModulePermission => new ModulePermission(
                value: $permission->value,
                label: $permission->label(),
                group: $permission->group(),
                roles: $roles[$permission->value],
            ),
            BlogPermission::cases(),
        );
    }

    public function menu(): array
    {
        return [
            new MenuItem(
                label: 'Artículos',
                route: 'articles.index',
                icon: 'article',
                activePattern: 'articles.*',
                permission: BlogPermission::ArticlesView->value,
                position: 10,
            ),
            new MenuItem(
                label: 'Categorías',
                route: 'categories.index',
                icon: 'folder',
                activePattern: 'categories.*',
                permission: BlogPermission::ArticlesView->value,
                position: 20,
            ),
            new MenuItem(
                label: 'Etiquetas',
                route: 'tags.index',
                icon: 'tag',
                activePattern: 'tags.*',
                permission: BlogPermission::ArticlesView->value,
                position: 30,
            ),
        ];
    }

    /**
     * Lo único configurable que solo entiende el blog.
     */
    public function settings(): array
    {
        return [
            new ModuleSetting(
                key: 'articles_per_page',
                label: 'Artículos por página',
                type: ModuleSetting::NUMBER,
                rules: ['required', 'integer', 'min:1', 'max:100'],
                default: 12,
                hint: 'Cuántos artículos pide la web de una vez. La API nunca sirve más de '
                    .'50 aunque aquí pongas más.',
            ),
        ];
    }

    /**
     * El valor guardado, listo para usar.
     */
    public static function articlesPerPage(): int
    {
        return (int) app(ModuleSettings::class)->get('blog', 'articles_per_page', 12);
    }

    /**
     * Las secciones del manual que hablan del blog.
     *
     * El manual del cliente sale de una sola fuente para la pantalla de Ayuda
     * y para el archivo descargable, y cada sección declara qué permiso hace
     * falta para que le sirva a alguien.
     */
    public function manualSections(): array
    {
        return [
            $this->writing(),
            $this->statuses(),
            $this->versions(),
            $this->taxonomies(),
        ];
    }

    private function writing(): ManualSection
    {
        return new ManualSection(
            key: 'escribir',
            title: 'Escribir un artículo',
            icon: 'ri-quill-pen-line',
            permission: BlogPermission::ArticlesCreate->value,
            blocks: [
                ['type' => 'text', 'content' => 'En «Artículos» → «Nuevo artículo». Lo único obligatorio '
                    .'para empezar es el título: todo lo demás se puede rellenar después.'],

                ['type' => 'heading', 'content' => 'El título y la dirección'],
                ['type' => 'text', 'content' => 'La dirección del artículo en la web se genera sola a '
                    .'partir del título. No tienes que escribirla ni preocuparte por ella.'],
                ['type' => 'text', 'content' => 'Mientras el artículo no está publicado, la dirección se '
                    .'va ajustando si cambias el título. **En cuanto lo publicas, se queda fija**, aunque '
                    .'luego cambies el título. Es a propósito: si cambiara, se romperían los enlaces que '
                    .'ya haya compartido la gente y perderías posiciones en Google.'],
                ['type' => 'tip', 'content' => 'Si necesitas cambiarla de verdad, hay un botón para '
                    .'regenerarla. El panel guarda una redirección automática desde la anterior, así que '
                    .'quien llegue por un enlace antiguo acaba en el artículo igualmente.'],

                ['type' => 'heading', 'content' => 'El contenido'],
                ['type' => 'text', 'content' => 'El editor funciona como un procesador de textos: negrita, '
                    .'listas, títulos, enlaces e imágenes. También puedes pegar la dirección de un vídeo de '
                    .'YouTube o Vimeo y se inserta solo.'],
                ['type' => 'text', 'content' => 'Si pegas texto desde Word o desde otra web, el panel limpia '
                    .'el formato que traiga escondido. Es lo que evita que un artículo se vea distinto al '
                    .'resto sin saber por qué.'],

                ['type' => 'heading', 'content' => 'El extracto'],
                ['type' => 'text', 'content' => 'Es el resumen que aparece en los listados y en Google. Si '
                    .'lo dejas vacío se usa el principio del artículo, pero queda mejor escribirlo: son dos '
                    .'líneas que deciden si alguien entra o no.'],

                ['type' => 'heading', 'content' => 'La imagen destacada'],
                ['type' => 'text', 'content' => 'Es la que se ve en los listados y la que aparece al '
                    .'compartir el artículo en redes sociales. Ponla siempre: un artículo sin imagen se '
                    .'comparte mucho menos.'],
                ['type' => 'tip', 'content' => 'Rellena el texto alternativo describiendo lo que se ve. '
                    .'Lo usan los lectores de pantalla de personas ciegas, y Google también lo lee.'],

                ['type' => 'heading', 'content' => 'Vista previa'],
                ['type' => 'text', 'content' => 'El botón «Vista previa» enseña cómo va a quedar sin '
                    .'publicarlo. Puedes usarlo tantas veces como quieras.'],
            ],
            position: 20,
        );
    }

    private function statuses(): ManualSection
    {
        $rows = array_map(
            fn (ArticleStatus $status): array => [
                $status->label(),
                match ($status) {
                    ArticleStatus::Draft => 'Lo estás escribiendo. No se ve en la web.',
                    ArticleStatus::PendingReview => 'Lo has terminado y esperas a que alguien lo revise.',
                    ArticleStatus::Approved => 'Revisado y correcto, pero todavía sin publicar.',
                    ArticleStatus::Scheduled => 'Tiene fecha y hora: se publicará solo.',
                    ArticleStatus::Published => 'Visible en la web.',
                    ArticleStatus::Archived => 'Retirado de la web, pero no borrado.',
                },
            ],
            ArticleStatus::cases(),
        );

        return new ManualSection(
            key: 'estados',
            title: 'Los estados de un artículo',
            icon: 'ri-git-branch-line',
            permission: BlogPermission::ArticlesView->value,
            blocks: [
                ['type' => 'text', 'content' => 'Cada artículo pasa por varios estados. Solo los '
                    .'**publicados** se ven en la web.'],
                ['type' => 'table', 'content' => ['headers' => ['Estado', 'Qué significa'], 'rows' => $rows]],

                ['type' => 'heading', 'content' => 'Programar una publicación'],
                ['type' => 'text', 'content' => 'Elige «Programado» y pon la fecha y la hora. El artículo '
                    .'se publica solo, sin que tengas que entrar. Hasta ese momento no se ve en la web ni '
                    .'aunque alguien conozca su dirección.'],

                ['type' => 'heading', 'content' => 'Archivar en lugar de borrar'],
                ['type' => 'text', 'content' => 'Si un artículo ha dejado de tener sentido pero no quieres '
                    .'perderlo, archívalo: desaparece de la web y se conserva aquí. Siempre puedes '
                    .'volver a publicarlo.'],

                ['type' => 'heading', 'content' => 'Visibilidad'],
                ['type' => 'text', 'content' => 'Un artículo publicado puede ser **público** (aparece en '
                    .'los listados) o **solo con enlace** (no aparece en ningún sitio, pero funciona si '
                    .'compartes su dirección). Lo segundo sirve para enseñar algo a alguien concreto sin '
                    .'anunciarlo.'],
            ],
            position: 30,
        );
    }

    private function versions(): ManualSection
    {
        return new ManualSection(
            key: 'versiones',
            title: 'Guardado automático y versiones',
            icon: 'ri-history-line',
            permission: BlogPermission::ArticlesCreate->value,
            blocks: [
                ['type' => 'text', 'content' => 'Mientras escribes, el panel va guardando una copia cada '
                    .'pocos segundos. Si se te cierra el navegador o se va la luz, al volver te ofrece '
                    .'recuperar lo último que tenías.'],
                ['type' => 'warning', 'content' => 'Esa copia automática **no es lo mismo que guardar**. '
                    .'Sirve para no perder el trabajo, pero el artículo no cambia hasta que pulsas Guardar.'],

                ['type' => 'heading', 'content' => 'Volver atrás'],
                ['type' => 'text', 'content' => 'Cada vez que guardas se conserva una versión. En '
                    .'«Versiones» puedes ver cómo estaba el artículo antes y restaurar cualquiera de ellas. '
                    .'Restaurar no borra nada: crea una versión nueva con el contenido antiguo.'],
            ],
            position: 40,
        );
    }

    private function taxonomies(): ManualSection
    {
        return new ManualSection(
            key: 'categorias',
            title: 'Categorías y etiquetas',
            icon: 'ri-price-tag-3-line',
            permission: BlogPermission::CategoriesManage->value,
            blocks: [
                ['type' => 'text', 'content' => 'Las **categorías** son las secciones grandes del blog: '
                    .'unas pocas, fijas, y cada artículo suele ir en una. Las **etiquetas** son temas '
                    .'sueltos: puede haber muchas y un artículo lleva las que haga falta.'],
                ['type' => 'tip', 'content' => 'Si dudas, empieza con tres o cuatro categorías. Es fácil '
                    .'añadir más adelante y muy incómodo reorganizar cien artículos.'],
                ['type' => 'text', 'content' => 'En la web solo aparecen las categorías y etiquetas que '
                    .'tienen algún artículo publicado. Una recién creada y vacía no se ve: no es un fallo.'],
            ],
            position: 60,
        );
    }

    /**
     * Los tipos de contenido del blog.
     *
     * Una sola declaración alimenta el filtro de la auditoría, el enlace al
     * sujeto de un registro, la etiqueta de dónde se usa un archivo, el destino
     * de una redirección y el alias que se guarda en las columnas polimórficas.
     */
    public function contentTypes(): array
    {
        return [
            new ContentType(
                model: Article::class,
                alias: 'blog.article',
                singular: 'Artículo',
                plural: 'Artículos',
                editUrl: fn (int|string $id): string => route('articles.edit', $id),
            ),
            new ContentType(
                model: Category::class,
                alias: 'blog.category',
                singular: 'Categoría',
                plural: 'Categorías',
                editUrl: fn (int|string $id): string => route('categories.edit', $id),
            ),
            new ContentType(
                model: Tag::class,
                alias: 'blog.tag',
                singular: 'Etiqueta',
                plural: 'Etiquetas',
            ),
        ];
    }

    /**
     * Lo que el blog pone en el panel de inicio.
     *
     * Un autor solo cuenta lo suyo: enseñarle cifras globales que no puede
     * tocar no le sirve de nada. Esa decisión se toma aquí, una vez, y vale
     * para las tarjetas, los avisos y los dos bloques propios.
     */
    public function dashboard(User $user): DashboardContribution
    {
        if ($user->cannot(BlogPermission::ArticlesView->value)) {
            return DashboardContribution::empty();
        }

        $onlyOwn = $user->cannot(BlogPermission::ArticlesUpdateAny->value)
            && $user->can(BlogPermission::ArticlesUpdateOwn->value);

        $stats = $this->articleStats($user, $onlyOwn);
        $total = array_sum(array_column($stats, 'count'));
        $byStatus = array_column($stats, 'count', 'status');

        $scheduledSoon = $this->base($user, $onlyOwn)
            ->where('status', ArticleStatus::Scheduled->value)
            ->whereBetween('scheduled_for', [now(), now()->addDays(7)])
            ->count();

        return new DashboardContribution(
            cards: [
                new DashboardCard(
                    key: 'blog.total',
                    label: $onlyOwn ? 'Artículos tuyos' : 'Artículos en total',
                    count: $total,
                    icon: 'article',
                    tone: 'primary',
                    route: 'articles.index',
                    position: 10,
                ),
                new DashboardCard(
                    key: 'blog.published',
                    label: 'Publicados',
                    count: $byStatus[ArticleStatus::Published->value] ?? 0,
                    icon: 'check',
                    tone: 'success',
                    route: 'articles.index',
                    query: ['status' => ArticleStatus::Published->value],
                    position: 20,
                ),
                new DashboardCard(
                    key: 'blog.drafts',
                    label: 'Borradores',
                    count: $byStatus[ArticleStatus::Draft->value] ?? 0,
                    icon: 'pen',
                    tone: 'neutral',
                    route: 'articles.index',
                    query: ['status' => ArticleStatus::Draft->value],
                    position: 30,
                ),
                new DashboardCard(
                    key: 'blog.scheduled',
                    label: 'Se publican solos',
                    count: $scheduledSoon,
                    icon: 'clock',
                    tone: 'info',
                    route: 'articles.index',
                    query: ['status' => ArticleStatus::Scheduled->value],
                    hint: 'En los próximos 7 días',
                    position: 40,
                ),
            ],
            alerts: $this->alerts($user, $onlyOwn, $scheduledSoon),
            widgets: [
                new DashboardWidget(
                    component: 'Blog::RecentArticles',
                    props: [
                        'title' => $onlyOwn ? 'Tus últimos artículos' : 'Últimos artículos',
                        'allUrl' => route('articles.index'),
                        'articles' => $this->recentArticles($user, $onlyOwn),
                    ],
                    area: DashboardWidget::MAIN,
                    position: 10,
                ),
                new DashboardWidget(
                    component: 'Blog::ArticlesByStatus',
                    props: ['stats' => $stats, 'total' => $total],
                    area: DashboardWidget::SIDE,
                    position: 10,
                ),
            ],
            actions: $user->can(BlogPermission::ArticlesCreate->value)
                ? [new DashboardAction(
                    label: 'Escribir un artículo',
                    href: route('articles.create'),
                    icon: 'pen',
                    position: 10,
                )]
                : [],
        );
    }

    /**
     * Consulta base: un autor solo ve lo suyo.
     *
     * @return Builder<Article>
     */
    private function base(User $user, bool $onlyOwn): Builder
    {
        return Article::query()
            ->when($onlyOwn, fn ($query) => $query->where('author_id', $user->getKey()));
    }

    /**
     * Recuento de artículos por estado.
     *
     * @return list<array{status: string, label: string, tone: string, count: int}>
     */
    private function articleStats(User $user, bool $onlyOwn): array
    {
        $counts = $this->base($user, $onlyOwn)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return array_map(
            fn (ArticleStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
                'tone' => $status->tone(),
                'count' => (int) ($counts[$status->value] ?? 0),
            ],
            ArticleStatus::cases(),
        );
    }

    /**
     * Avisos accionables: lo pendiente de revisar y lo que se publicará solo.
     *
     * Solo se enseña lo que esta persona puede resolver.
     *
     * @return list<DashboardAlert>
     */
    private function alerts(User $user, bool $onlyOwn, int $scheduledSoon): array
    {
        $pending = $this->base($user, $onlyOwn)
            ->where('status', ArticleStatus::PendingReview->value)
            ->count();

        $approved = $this->base($user, $onlyOwn)
            ->where('status', ArticleStatus::Approved->value)
            ->count();

        $canReview = $user->can(BlogPermission::ArticlesApprove->value);
        $canPublish = $user->can(BlogPermission::ArticlesPublish->value);

        $alerts = [];

        if ($canReview && $pending > 0) {
            $alerts[] = new DashboardAlert(
                key: 'blog.review',
                text: $pending === 1
                    ? 'Hay 1 artículo esperando revisión.'
                    : "Hay {$pending} artículos esperando revisión.",
                tone: 'warning',
                actionLabel: 'Revisarlos',
                actionHref: route('articles.index', ['status' => ArticleStatus::PendingReview->value]),
                position: 10,
            );
        }

        if ($canPublish && $approved > 0) {
            $alerts[] = new DashboardAlert(
                key: 'blog.approved',
                text: $approved === 1
                    ? 'Hay 1 artículo aprobado sin publicar.'
                    : "Hay {$approved} artículos aprobados sin publicar.",
                tone: 'info',
                actionLabel: 'Publicarlos',
                actionHref: route('articles.index', ['status' => ArticleStatus::Approved->value]),
                position: 20,
            );
        }

        if (! $canReview && $pending > 0) {
            $alerts[] = new DashboardAlert(
                key: 'blog.own-review',
                text: $pending === 1
                    ? 'Tienes 1 artículo pendiente de que lo revisen.'
                    : "Tienes {$pending} artículos pendientes de que los revisen.",
                tone: 'info',
                position: 30,
            );
        }

        if ($scheduledSoon > 0) {
            $alerts[] = new DashboardAlert(
                key: 'blog.scheduled',
                text: $scheduledSoon === 1
                    ? 'Se publicará 1 artículo automáticamente esta semana.'
                    : "Se publicarán {$scheduledSoon} artículos automáticamente esta semana.",
                tone: 'info',
                actionLabel: 'Ver programados',
                actionHref: route('articles.index', ['status' => ArticleStatus::Scheduled->value]),
                position: 40,
            );
        }

        return $alerts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentArticles(User $user, bool $onlyOwn): array
    {
        return $this->base($user, $onlyOwn)
            ->with('author:id,name')
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(fn (Article $article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'status_label' => $article->status->label(),
                'status_tone' => $article->status->tone(),
                'author_name' => $article->author?->name,
                'updated_at' => $article->updated_at?->toIso8601String(),
                'edit_url' => $user->can('update', $article) ? route('articles.edit', $article->id) : null,
            ])
            ->all();
    }

    /**
     * Publicar lo que tenga la hora cumplida, cuando no hay cron.
     *
     * `ARTICLES_PUBLISH_ON_REQUEST=false` la desactiva en instalaciones que sí
     * tienen cron: allí lo hace el planificador, al minuto exacto.
     */
    public function deferredTasks(): array
    {
        if (! config('articles.publish_on_request', true)) {
            return [];
        }

        return [
            new DeferredTask(
                key: 'blog.publish-due',
                hasWork: static fn (): bool => app(PublishDueArticles::class)->hasDue(),
                run: static function (): void {
                    app(PublishDueArticles::class)->handle();
                },
            ),
        ];
    }

    /**
     * Lo que el blog aporta al endpoint de estado.
     */
    public function publicCounts(): array
    {
        return [
            new PublicCount(
                key: 'articulos_publicados',
                label: 'Artículos que sirve ahora mismo',
                count: Article::query()->public()->count(),
            ),
        ];
    }

    /**
     * Direcciones publicadas del blog, para el sitemap de la web.
     */
    public function sitemapSections(): array
    {
        $articles = Article::query()
            ->public()
            ->orderByDesc('published_at')
            ->get(['slug', 'updated_at', 'published_at', 'is_indexable']);

        return [
            'articulos' => $articles->map(fn (Article $article): array => [
                'slug' => $article->slug,
                'modificado_en' => $article->updated_at?->toIso8601String(),
                'publicado_en' => $article->published_at?->toIso8601String(),
                'indexable' => $article->is_indexable,
            ])->values()->all(),

            'categorias' => Category::query()
                ->withCount(['articles' => fn ($query) => $query->public()])
                ->having('articles_count', '>', 0)
                ->orderBy('position')
                ->get()
                ->map(fn (Category $category): array => [
                    'slug' => $category->slug,
                    'modificado_en' => $category->updated_at?->toIso8601String(),
                ])->values()->all(),

            'etiquetas' => Tag::query()
                ->withCount(['articles' => fn ($query) => $query->public()])
                ->having('articles_count', '>', 0)
                ->orderBy('name')
                ->get()
                ->map(fn (Tag $tag): array => [
                    'slug' => $tag->slug,
                    'modificado_en' => $tag->updated_at?->toIso8601String(),
                ])->values()->all(),
        ];
    }

    /**
     * Endpoints del blog en la API pública.
     *
     * El ejemplo de respuesta viaja con cada uno: de aquí salen el probador del
     * panel, la documentación descargable y el OpenAPI, y hay una prueba que
     * compara estas claves con las que devuelve la API de verdad.
     */
    public function apiEndpoints(): array
    {
        $article = [
            'id' => 12,
            'titulo' => 'Cómo elegir la masa de tu pizza',
            'slug' => 'como-elegir-la-masa-de-tu-pizza',
            'extracto' => 'Fina, napolitana o de molde: en qué se diferencian y cuándo usar cada una.',
            'minutos_lectura' => 4,
            'imagen_destacada' => [
                'alt' => 'Masa de pizza recién estirada sobre harina',
                'miniatura' => 'https://ejemplo.com/storage/biblioteca/variantes/masa-thumbnail.jpg',
                'mediana' => 'https://ejemplo.com/storage/biblioteca/variantes/masa-medium.jpg',
                'grande' => 'https://ejemplo.com/storage/biblioteca/variantes/masa-large.jpg',
            ],
            'autor' => ['nombre' => 'Lucía Ramos'],
            'categorias' => [['nombre' => 'Recetas', 'slug' => 'recetas']],
            'etiquetas' => [['nombre' => 'Masa', 'slug' => 'masa']],
            'seo' => [
                'titulo' => 'Cómo elegir la masa de tu pizza',
                'descripcion' => 'Fina, napolitana o de molde: en qué se diferencian.',
                'indexable' => true,
                'og_titulo' => 'Cómo elegir la masa de tu pizza',
                'og_descripcion' => 'Fina, napolitana o de molde.',
            ],
            'publicado_en' => '2026-07-14T09:00:00+00:00',
            'actualizado_en' => '2026-07-16T11:32:10+00:00',
        ];

        return [
            new ApiEndpoint(
                key: 'blog',
                path: 'blog',
                title: 'Datos del blog (heredado)',
                description: 'Lo mismo que `/sitio` más `articulos_por_pagina`. Existe solo para no '
                    .'romper las webs que se conectaron cuando esto era únicamente un blog. '
                    .'**En una integración nueva usa `/sitio`.**',
                example: [
                    'datos' => [
                        'nombre' => 'Alminares',
                        'lema' => 'Novedades del sector',
                        'url_publica' => 'https://www.ejemplo.com',
                        'idioma' => 'es',
                        'articulos_por_pagina' => 12,
                        'logo' => 'https://ejemplo.com/storage/settings/logo.png',
                        'logo_oscuro' => null,
                        'favicon' => 'https://ejemplo.com/storage/settings/favicon.png',
                        'seo' => [
                            'titulo' => 'Alminares',
                            'descripcion' => 'Novedades, productos y consejos.',
                            'plantilla_titulo' => ':title | Alminares',
                            'indexable' => true,
                            'og_imagen' => 'https://ejemplo.com/storage/settings/og.jpg',
                            'twitter_card' => 'summary_large_image',
                        ],
                        'organizacion' => [
                            'nombre' => 'Alminares S.L.',
                            'redes' => [['network' => 'instagram', 'url' => 'https://instagram.com/alminares']],
                        ],
                    ],
                ],
                notes: ['El número de artículos por página también llega en cada listado, dentro de '
                    .'`paginacion.por_pagina`, así que una web nueva no necesita este endpoint.'],
                position: 25,
            ),
            new ApiEndpoint(
                key: 'articulos',
                path: 'articulos',
                title: 'Listado de artículos',
                description: 'Artículos publicados, del más reciente al más antiguo. Solo sale lo '
                    .'que ya está publicado: un borrador o algo programado nunca aparece.',
                parameters: [
                    ['name' => 'pagina', 'description' => 'Número de página', 'example' => '1'],
                    ['name' => 'por_pagina', 'description' => 'Artículos por página (máximo 50)', 'example' => '12'],
                    ['name' => 'categoria', 'description' => 'Filtra por la dirección de una categoría', 'example' => 'noticias'],
                    ['name' => 'etiqueta', 'description' => 'Filtra por la dirección de una etiqueta', 'example' => 'novedades'],
                    ['name' => 'buscar', 'description' => 'Busca en título, extracto y contenido', 'example' => 'pizza'],
                ],
                example: [
                    'datos' => [$article],
                    'paginacion' => [
                        'pagina' => 1,
                        'por_pagina' => 12,
                        'total' => 37,
                        'ultima_pagina' => 4,
                        'hay_mas' => true,
                    ],
                ],
                notes: ['El máximo de `por_pagina` es **'.(int) config('api.max_per_page').'**; por encima se recorta sin avisar.'],
                position: 30,
            ),
            new ApiEndpoint(
                key: 'articulo',
                path: 'articulos/{slug}',
                title: 'Un artículo',
                description: 'El artículo completo con su contenido en HTML, ya limpio y seguro para '
                    .'insertar, además de sus relacionados.',
                parameters: [
                    ['name' => 'slug', 'description' => 'Dirección del artículo', 'example' => ''],
                ],
                example: [
                    'datos' => $article + [
                        'contenido_html' => '<p>La masa es la base de todo…</p><h2>Masa fina</h2><p>…</p>',
                    ],
                    'relacionados' => [],
                ],
                position: 40,
            ),
            new ApiEndpoint(
                key: 'categorias',
                path: 'categorias',
                title: 'Categorías',
                description: 'Categorías que tienen al menos un artículo publicado, en orden de '
                    .'presentación. Para el menú de la web.',
                example: [
                    'datos' => [
                        [
                            'nombre' => 'Recetas',
                            'slug' => 'recetas',
                            'descripcion' => 'Todo lo que se cocina en casa.',
                            'articulos' => 14,
                            'seo' => ['titulo' => 'Recetas', 'descripcion' => 'Todo lo que se cocina en casa.'],
                        ],
                    ],
                ],
                position: 50,
            ),
            new ApiEndpoint(
                key: 'etiquetas',
                path: 'etiquetas',
                title: 'Etiquetas',
                description: 'Etiquetas en uso, de la más usada a la menos.',
                example: [
                    'datos' => [['nombre' => 'Masa', 'slug' => 'masa', 'articulos' => 6]],
                ],
                position: 60,
            ),
        ];
    }
}
