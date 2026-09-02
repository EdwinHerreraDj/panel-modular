<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers;

use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Blog\Actions\DuplicateArticle;
use Modules\Blog\Actions\SaveArticle;
use Modules\Blog\Enums\ArticleStatus;
use Modules\Blog\Enums\ArticleVisibility;
use Modules\Blog\Enums\BlogPermission;
use Modules\Blog\Http\Requests\ArticleRequest;
use Modules\Blog\Models\Article;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Tag;

class ArticleController extends Controller
{
    /** @var list<string> */
    private const SORTABLE = ['title', 'status', 'published_at', 'updated_at', 'created_at'];

    public function __construct(
        private readonly SaveArticle $saveArticle,
        private readonly RecordActivity $recordActivity,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Article::class);

        $user = $request->user();

        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => $request->string('status')->toString(),
            'extra' => $request->string('extra')->toString(),
            'trashed' => $request->boolean('trashed'),
            'mine' => $request->boolean('mine'),
            'sort' => in_array($request->string('sort')->toString(), self::SORTABLE, true)
                ? $request->string('sort')->toString()
                : 'updated_at',
            'direction' => $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc',
        ];

        $statuses = array_column(ArticleStatus::cases(), 'value');

        $articles = Article::query()
            ->with(['author:id,name', 'categories:id,name'])
            ->search($filters['search'])
            ->when(
                in_array($filters['status'], $statuses, true),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->when(
                $filters['extra'] !== '' && ctype_digit($filters['extra']),
                fn ($query) => $query->whereHas(
                    'categories',
                    fn ($inner) => $inner->where('categories.id', (int) $filters['extra']),
                ),
            )
            ->when($filters['mine'] && $user !== null, fn ($query) => $query->where('author_id', $user?->getKey()))
            ->when($filters['trashed'], fn ($query) => $query->onlyTrashed())
            ->orderBy($filters['sort'], $filters['direction'])
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Article $article): array => $this->toListItem($request, $article));

        return Inertia::render('Blog::Articles/Index', [
            'articles' => $articles,
            'filters' => $filters,
            'statusOptions' => ArticleStatus::options(),
            'categoryOptions' => $this->categoryOptions(),
            'can' => [
                'create' => $user?->can('create', Article::class) ?? false,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Article::class);

        return Inertia::render('Blog::Articles/Create', [
            'relatedOptions' => $this->relatedOptions(),
            ...$this->formOptions($request->user()),
        ]);
    }

    public function store(ArticleRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $article = $this->saveArticle->handle(null, $request->validated(), $user);

        return redirect()
            ->route('articles.edit', $article)
            ->with('success', "Artículo «{$article->title}» creado.");
    }

    public function edit(Request $request, Article $article): Response
    {
        $this->authorize('update', $article);

        $article->load([
            'categories:id',
            'tags:id',
            'related:id,title',
            'author:id,name',
            'autosave',
            'featuredMedia',
        ]);

        $autosave = $article->autosave;

        // Guardar el artículo borra su copia automática, así que cualquiera
        // que exista está pendiente de recuperar. La comparación admite la
        // igualdad para no perderla cuando comparte segundo con el guardado.
        $hasNewerAutosave = $autosave !== null
            && $autosave->created_at?->greaterThanOrEqualTo($article->updated_at ?? now()->subCentury());

        return Inertia::render('Blog::Articles/Edit', [
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'content' => $article->content,
                'status' => $article->status->value,
                'status_label' => $article->status->label(),
                'visibility' => $article->visibility->value,
                'published_at' => $article->published_at?->format('Y-m-d\TH:i'),
                'scheduled_for' => $article->scheduled_for?->format('Y-m-d\TH:i'),
                'position' => $article->position,
                'reading_minutes' => $article->reading_minutes,
                'category_ids' => $article->categories->pluck('id')->all(),
                'tag_ids' => $article->tags->pluck('id')->all(),
                'related_ids' => $article->related->pluck('id')->all(),
                'featured_media_id' => $article->featured_media_id,
                'featured_media' => $article->featuredMedia === null ? null : [
                    'id' => $article->featuredMedia->id,
                    'name' => $article->featuredMedia->name,
                    'url' => $article->featuredMedia->variantUrl('medium'),
                ],
                'featured_image_alt' => $article->featured_image_alt,
                'meta_title' => $article->meta_title,
                'meta_description' => $article->meta_description,
                'canonical_url' => $article->canonical_url,
                'og_title' => $article->og_title,
                'og_description' => $article->og_description,
                'is_indexable' => $article->is_indexable,
                'is_slug_locked' => $article->is_slug_locked,
                'author_name' => $article->author?->name,
                'updated_at' => $article->updated_at?->toIso8601String(),
                'revisions_count' => $article->revisions()->count(),
            ],
            // `$hasNewerAutosave` ya garantiza que la copia existe y tiene fecha.
            'autosave' => $hasNewerAutosave ? [
                'saved_at' => $autosave->created_at->toIso8601String(),
                'title' => $autosave->snapshot['title'] ?? null,
                'excerpt' => $autosave->snapshot['excerpt'] ?? null,
                'content' => $autosave->snapshot['content'] ?? null,
            ] : null,
            'relatedOptions' => $this->relatedOptions($article),
            'autosaveSeconds' => (int) config('articles.autosave_seconds', 30),
            ...$this->formOptions($request->user(), $article->status),
        ]);
    }

    public function update(ArticleRequest $request, Article $article): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->saveArticle->handle($article, $request->validated(), $user);

        return redirect()
            ->route('articles.edit', $article)
            ->with('success', 'Artículo guardado correctamente.');
    }

    /**
     * Crea una copia editable del artículo.
     */
    public function duplicate(Request $request, Article $article, DuplicateArticle $duplicate): RedirectResponse
    {
        $this->authorize('create', Article::class);
        $this->authorize('view', $article);

        /** @var User $user */
        $user = $request->user();

        $copy = $duplicate->handle($article, $user);

        return redirect()
            ->route('articles.edit', $copy)
            ->with('success', 'Copia creada como borrador. Revísala antes de publicarla.');
    }

    public function regenerateSlug(Article $article): RedirectResponse
    {
        $this->authorize('regenerateSlug', $article);

        $previous = $article->slug;

        DB::transaction(function () use ($article, $previous): void {
            $article->regenerateSlug();

            $this->recordActivity->handle(
                $article,
                ActivityEvent::Updated,
                "Slug regenerado para «{$article->title}»",
                ['slug' => [$previous, $article->slug]],
            );
        });

        return back()->with(
            'success',
            "La dirección del artículo es ahora «{$article->slug}». Recuerda actualizar los enlaces que apunten a la anterior.",
        );
    }

    public function destroy(Article $article): RedirectResponse
    {
        $this->authorize('delete', $article);

        DB::transaction(function () use ($article): void {
            $article->delete();

            $this->recordActivity->handle(
                $article,
                ActivityEvent::Deleted,
                "Artículo «{$article->title}» enviado a la papelera",
            );
        });

        return redirect()
            ->route('articles.index')
            ->with('success', 'Artículo enviado a la papelera.');
    }

    public function restore(Article $article): RedirectResponse
    {
        $this->authorize('restore', $article);

        DB::transaction(function () use ($article): void {
            $article->restore();

            $this->recordActivity->handle(
                $article,
                ActivityEvent::Restored,
                "Artículo «{$article->title}» restaurado",
            );
        });

        return redirect()
            ->route('articles.index')
            ->with('success', 'Artículo restaurado correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Request $request, Article $article): array
    {
        $user = $request->user();

        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'status' => $article->status->value,
            'status_label' => $article->status->label(),
            'status_tone' => $article->status->tone(),
            'visibility' => $article->visibility->value,
            'author_name' => $article->author?->name,
            'categories' => $article->categories->pluck('name')->all(),
            'published_at' => $article->published_at?->toIso8601String(),
            'scheduled_for' => $article->scheduled_for?->toIso8601String(),
            'updated_at' => $article->updated_at?->toIso8601String(),
            'reading_minutes' => $article->reading_minutes,
            'deleted_at' => $article->deleted_at?->toIso8601String(),
            'is_mine' => $user !== null && $article->belongsToAuthor($user),
            'can' => [
                'update' => $user?->can('update', $article) ?? false,
                'delete' => $user?->can('delete', $article) ?? false,
                'restore' => $user?->can('restore', $article) ?? false,
            ],
        ];
    }

    /**
     * Opciones y permisos compartidos por el alta y la edición.
     *
     * @return array<string, mixed>
     */
    private function formOptions(?User $user, ArticleStatus $current = ArticleStatus::Draft): array
    {
        // Solo se ofrecen los estados a los que se puede pasar de verdad, con
        // el permiso que hace falta ya comprobado.
        $statuses = array_values(array_filter(
            ArticleStatus::cases(),
            function (ArticleStatus $status) use ($current, $user): bool {
                if (! $current->canTransitionTo($status)) {
                    return false;
                }

                $permission = $status->requiredPermission();

                return $permission === null || ($user?->can($permission->value) ?? false);
            },
        ));

        return [
            'statusOptions' => array_map(
                fn (ArticleStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                $statuses,
            ),
            'visibilityOptions' => ArticleVisibility::options(),
            'categoryOptions' => $this->categoryOptions(),
            'tagOptions' => Tag::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Tag $tag): array => ['value' => (string) $tag->id, 'label' => $tag->name])
                ->all(),
            'can' => [
                'publish' => $user?->can(BlogPermission::ArticlesPublish->value) ?? false,
                'schedule' => $user?->can(BlogPermission::ArticlesSchedule->value) ?? false,
                'approve' => $user?->can(BlogPermission::ArticlesApprove->value) ?? false,
                'manageTags' => $user?->can(BlogPermission::TagsManage->value) ?? false,
            ],
        ];
    }

    /**
     * Artículos que se pueden relacionar, excluyendo el actual.
     *
     * @return list<array{value: string, label: string}>
     */
    private function relatedOptions(?Article $current = null): array
    {
        return Article::query()
            ->when($current !== null, fn ($query) => $query->whereKeyNot($current?->getKey()))
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get(['id', 'title'])
            ->map(fn (Article $article): array => [
                'value' => (string) $article->id,
                'label' => $article->title,
            ])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function categoryOptions(): array
    {
        return Category::query()
            ->ordered()
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => [
                'value' => (string) $category->id,
                'label' => $category->name,
            ])
            ->all();
    }
}
