<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers;

use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Blog\Http\Requests\StoreCategoryRequest;
use Modules\Blog\Http\Requests\UpdateCategoryRequest;
use Modules\Blog\Models\Category;

class CategoryController extends Controller
{
    /** @var list<string> */
    private const SORTABLE = ['name', 'position', 'created_at'];

    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Category::class);

        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => '',
            'trashed' => $request->boolean('trashed'),
            'sort' => in_array($request->string('sort')->toString(), self::SORTABLE, true)
                ? $request->string('sort')->toString()
                : 'position',
            'direction' => $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc',
        ];

        $categories = Category::query()
            ->search($filters['search'])
            ->when($filters['trashed'], fn ($query) => $query->onlyTrashed())
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Category $category): array => $this->toListItem($request, $category));

        return Inertia::render('Blog::Categories/Index', [
            'categories' => $categories,
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Category::class);

        return Inertia::render('Blog::Categories/Create', [
            'nextPosition' => (int) Category::query()->max('position') + 1,
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = DB::transaction(function () use ($request): Category {
            $category = Category::create($request->validated());

            $this->recordActivity->handle(
                $category,
                ActivityEvent::Created,
                "Alta de la categoría «{$category->name}»",
            );

            return $category;
        });

        return redirect()
            ->route('categories.index')
            ->with('success', "Categoría «{$category->name}» creada.");
    }

    public function edit(Category $category): Response
    {
        $this->authorize('update', $category);

        return Inertia::render('Blog::Categories/Edit', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'meta_title' => $category->meta_title,
                'meta_description' => $category->meta_description,
                'position' => $category->position,
                'is_slug_locked' => $category->is_slug_locked,
            ],
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        DB::transaction(function () use ($request, $category): void {
            $category->update($request->validated());

            if ($category->wasChanged()) {
                $this->recordActivity->forUpdate($category, "Modificación de la categoría «{$category->name}»");
            }
        });

        return redirect()
            ->route('categories.index')
            ->with('success', 'Categoría actualizada correctamente.');
    }

    /**
     * Vuelve a generar el slug a partir del nombre actual.
     *
     * Cambia una URL pública, así que es una acción explícita y no un efecto
     * secundario de renombrar.
     */
    public function regenerateSlug(Category $category): RedirectResponse
    {
        $this->authorize('regenerateSlug', $category);

        $previous = $category->slug;

        DB::transaction(function () use ($category, $previous): void {
            $category->regenerateSlug();

            $this->recordActivity->handle(
                $category,
                ActivityEvent::Updated,
                "Slug regenerado para «{$category->name}»",
                ['slug' => [$previous, $category->slug]],
            );
        });

        return back()->with(
            'success',
            "La dirección de la categoría es ahora «{$category->slug}». Recuerda actualizar los enlaces que apunten a la anterior.",
        );
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        DB::transaction(function () use ($category): void {
            $category->delete();

            $this->recordActivity->handle(
                $category,
                ActivityEvent::Deleted,
                "Categoría «{$category->name}» enviada a la papelera",
            );
        });

        return redirect()
            ->route('categories.index')
            ->with('success', 'Categoría enviada a la papelera.');
    }

    public function restore(Category $category): RedirectResponse
    {
        $this->authorize('restore', $category);

        DB::transaction(function () use ($category): void {
            $category->restore();

            $this->recordActivity->handle(
                $category,
                ActivityEvent::Restored,
                "Categoría «{$category->name}» restaurada",
            );
        });

        return redirect()
            ->route('categories.index')
            ->with('success', 'Categoría restaurada correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Request $request, Category $category): array
    {
        $user = $request->user();

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'position' => $category->position,
            'is_slug_locked' => $category->is_slug_locked,
            'deleted_at' => $category->deleted_at?->toIso8601String(),
            'can' => [
                'update' => $user?->can('update', $category) ?? false,
                'delete' => $user?->can('delete', $category) ?? false,
                'restore' => $user?->can('restore', $category) ?? false,
            ],
        ];
    }
}
