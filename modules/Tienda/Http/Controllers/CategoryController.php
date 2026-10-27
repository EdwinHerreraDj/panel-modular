<?php

declare(strict_types=1);

namespace Modules\Tienda\Http\Controllers;

use App\Actions\Media\SyncMediaUsage;
use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tienda\Actions\SaveCategory;
use Modules\Tienda\Http\Requests\CategoryRequest;
use Modules\Tienda\Models\Category;
use Modules\Tienda\Models\Product;
use Modules\Tienda\Support\CategoryTree;

/**
 * Las categorías de la tienda.
 *
 * El listado es el árbol entero y sin paginar: con unas decenas de categorías,
 * partirlo en páginas escondería justo lo que se quiere ver, qué cuelga de qué.
 */
class CategoryController extends Controller
{
    public function __construct(
        private readonly SaveCategory $saveCategory,
        private readonly RecordActivity $recordActivity,
        private readonly SyncMediaUsage $syncMediaUsage,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Category::class);

        $user = $request->user();
        $tree = CategoryTree::current();

        // Artículos por categoría principal, de una vez.
        /** @var array<int, int> $counts */
        $counts = Product::query()
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->selectRaw('category_id, count(*) as total')
            ->pluck('total', 'category_id')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        $rows = [];

        foreach ($tree->options() as $option) {
            $category = $tree->find((int) $option['value']);

            if ($category === null) {
                continue;
            }

            $rows[] = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'depth' => $option['depth'],
                'thumbnail_url' => $category->imageUrl('thumbnail'),
                'products_count' => $counts[$category->id] ?? 0,
                'children_count' => count($tree->childrenOf($category->id)),
                'is_visible' => $category->is_visible,
                'is_public' => $tree->isPublic($category->id),
                'position' => $category->position,
                'can' => [
                    'update' => $user?->can('update', $category) ?? false,
                    'delete' => $user?->can('delete', $category) ?? false,
                ],
            ];
        }

        $trashed = Category::onlyTrashed()
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'deleted_at' => $category->deleted_at?->toIso8601String(),
                'can_restore' => $user?->can('restore', $category) ?? false,
            ])
            ->all();

        return Inertia::render('Tienda::Categories/Index', [
            'categories' => $rows,
            'trashed' => $trashed,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Category::class);

        $parent = CategoryTree::current()->find($request->integer('parent') ?: null);

        return Inertia::render('Tienda::Categories/Create', [
            'parentOptions' => $this->parentOptions(null),
            'defaultParent' => $parent === null ? '' : (string) $parent->id,
            'nextPosition' => (int) Category::query()->where('parent_id', $parent?->id)->max('position') + 1,
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        $category = $this->saveCategory->handle(null, $request->validated());

        return redirect()
            ->route('tienda.categories.index')
            ->with('success', "Categoría «{$category->name}» creada.");
    }

    public function edit(Category $category): Response
    {
        $this->authorize('update', $category);

        return Inertia::render('Tienda::Categories/Edit', [
            'parentOptions' => $this->parentOptions($category),
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'is_slug_locked' => $category->is_slug_locked,
                'parent_id' => $category->parent_id === null ? '' : (string) $category->parent_id,
                'description' => $category->description ?? '',
                'image_media_id' => $category->image_media_id,
                'image_url' => $category->imageUrl('medium'),
                'is_visible' => $category->is_visible,
                'position' => $category->position,
                'meta_title' => $category->meta_title ?? '',
                'meta_description' => $category->meta_description ?? '',
            ],
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $this->saveCategory->handle($category, $request->validated());

        return redirect()
            ->route('tienda.categories.index')
            ->with('success', 'Categoría guardada.');
    }

    public function regenerateSlug(Category $category): RedirectResponse
    {
        $this->authorize('regenerateSlug', $category);

        $previous = $category->slug;
        $category->regenerateSlug();

        if ($category->slug === $previous) {
            return back()->with('status', 'La dirección ya era la que corresponde al nombre.');
        }

        $this->recordActivity->handle(
            $category,
            ActivityEvent::Updated,
            "Dirección de la categoría «{$category->name}» regenerada",
            ['slug' => [$previous, $category->slug]],
        );

        return back()->with('success', "Dirección actualizada a «{$category->slug}».");
    }

    /**
     * A la papelera, salvo que tenga subcategorías: dejarlas colgando de algo
     * borrado las haría desaparecer del árbol sin que nadie lo haya pedido.
     *
     * Los artículos no se tocan. Los que la tenían como principal se quedan sin
     * categoría a la vista hasta que se restaure o se les asigne otra.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        if ($category->children()->exists()) {
            return back()->with(
                'error',
                "«{$category->name}» tiene subcategorías. Muévelas o bórralas antes.",
            );
        }

        DB::transaction(function () use ($category): void {
            $this->syncMediaUsage->forget($category);
            $category->delete();

            $this->recordActivity->handle(
                $category,
                ActivityEvent::Deleted,
                "Categoría de la tienda «{$category->name}» enviada a la papelera",
            );
        });

        return back()->with('success', "Categoría «{$category->name}» enviada a la papelera.");
    }

    public function restore(Category $category): RedirectResponse
    {
        $this->authorize('restore', $category);

        DB::transaction(function () use ($category): void {
            // Si la de arriba sigue en la papelera, vuelve a la raíz en lugar
            // de colgar de algo que no se ve.
            if ($category->parent_id !== null && ! Category::query()->whereKey($category->parent_id)->exists()) {
                $category->parent_id = null;
            }

            $category->restore();
            $this->syncMediaUsage->set($category, 'image', $category->image_media_id);

            $this->recordActivity->handle(
                $category,
                ActivityEvent::Restored,
                "Categoría de la tienda «{$category->name}» restaurada",
            );
        });

        return back()->with('success', "Categoría «{$category->name}» restaurada.");
    }

    /**
     * Dónde se puede colgar: cualquiera salvo ella misma, sus hijas y las que
     * ya están en el último nivel.
     *
     * @return list<array{value: string, label: string}>
     */
    private function parentOptions(?Category $category): array
    {
        $tree = CategoryTree::current();
        $height = $category === null ? 0 : $tree->height($category->id);
        $options = [['value' => '', 'label' => 'Ninguna (categoría principal)']];

        foreach ($tree->options($category?->id) as $option) {
            if ($option['depth'] + 1 + 1 + $height > CategoryTree::MAX_DEPTH) {
                continue;
            }

            $options[] = ['value' => $option['value'], 'label' => $option['label']];
        }

        return $options;
    }
}
