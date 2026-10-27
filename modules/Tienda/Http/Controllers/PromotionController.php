<?php

declare(strict_types=1);

namespace Modules\Tienda\Http\Controllers;

use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ApiCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tienda\Actions\SavePromotion;
use Modules\Tienda\Enums\PromotionScope;
use Modules\Tienda\Enums\PromotionType;
use Modules\Tienda\Http\Requests\PromotionRequest;
use Modules\Tienda\Models\Product;
use Modules\Tienda\Models\Promotion;
use Modules\Tienda\Support\CategoryTree;

/**
 * Promociones de la tienda.
 */
class PromotionController extends Controller
{
    public function __construct(
        private readonly SavePromotion $savePromotion,
        private readonly RecordActivity $recordActivity,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Promotion::class);

        $user = $request->user();
        $timezone = Setting::current()->timezone;

        $promotions = Promotion::query()
            ->withCount(['categories', 'products'])
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Promotion $promotion): array => [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'value_label' => $promotion->valueLabel(),
                'scope_label' => match ($promotion->scope) {
                    PromotionScope::All => 'Toda la tienda',
                    PromotionScope::Categories => $promotion->categories_count === 1
                        ? '1 categoría'
                        : "{$promotion->categories_count} categorías",
                    PromotionScope::Products => $promotion->products_count === 1
                        ? '1 artículo'
                        : "{$promotion->products_count} artículos",
                },
                'starts_at' => $promotion->starts_at?->setTimezone($timezone)->format('d/m/Y H:i'),
                'ends_at' => $promotion->ends_at?->setTimezone($timezone)->format('d/m/Y H:i'),
                'status' => $promotion->status(),
                'can' => [
                    'update' => $user?->can('update', $promotion) ?? false,
                    'delete' => $user?->can('delete', $promotion) ?? false,
                ],
            ]);

        return Inertia::render('Tienda::Promotions/Index', [
            'promotions' => $promotions,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Promotion::class);

        return Inertia::render('Tienda::Promotions/Create', $this->formOptions());
    }

    public function store(PromotionRequest $request): RedirectResponse
    {
        $this->authorize('create', Promotion::class);

        $promotion = $this->savePromotion->handle(null, $request->validated());

        return redirect()
            ->route('tienda.promotions.index')
            ->with('success', "Promoción «{$promotion->name}» creada.");
    }

    public function edit(Promotion $promotion): Response
    {
        $this->authorize('update', $promotion);

        $promotion->load(['categories:id', 'products:id']);
        $timezone = Setting::current()->timezone;

        return Inertia::render('Tienda::Promotions/Edit', [
            ...$this->formOptions(),
            'promotion' => [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'description' => $promotion->description ?? '',
                'type' => $promotion->type->value,
                'value' => rtrim(rtrim(number_format((float) $promotion->value, 2, ',', ''), '0'), ','),
                'scope' => $promotion->scope->value,
                'category_ids' => $promotion->categories->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
                'product_ids' => $promotion->products->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
                'starts_at' => self::forInput($promotion->starts_at, $timezone),
                'ends_at' => self::forInput($promotion->ends_at, $timezone),
                'is_active' => $promotion->is_active,
            ],
        ]);
    }

    public function update(PromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        $this->authorize('update', $promotion);

        $this->savePromotion->handle($promotion, $request->validated());

        return redirect()
            ->route('tienda.promotions.index')
            ->with('success', 'Promoción guardada.');
    }

    /**
     * Una promoción se borra de verdad: no hay nada que recuperar que no se
     * vuelva a crear en un minuto, y la auditoría guarda que existió.
     */
    public function destroy(Promotion $promotion): RedirectResponse
    {
        $this->authorize('delete', $promotion);

        $name = $promotion->name;
        $promotion->delete();
        ApiCache::flush();

        $this->recordActivity->handle($promotion, ActivityEvent::Deleted, "Promoción «{$name}» eliminada");

        return back()->with('success', "Promoción «{$name}» eliminada.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $tree = CategoryTree::current();

        return [
            'typeOptions' => PromotionType::options(),
            'scopeOptions' => PromotionScope::options(),
            'categoryOptions' => array_map(
                fn (array $option): array => [
                    'value' => $option['value'],
                    'label' => $tree->label((int) $option['value']),
                ],
                $tree->options(),
            ),
            // Para el selector múltiple. Con cientos de artículos sigue siendo
            // una lista corta en JSON; el buscador del componente hace el resto.
            'productOptions' => Product::query()
                ->orderBy('name')
                ->limit(2000)
                ->get(['id', 'name', 'sku'])
                ->map(fn (Product $product): array => [
                    'value' => (string) $product->id,
                    'label' => $product->sku === null ? $product->name : "{$product->name} ({$product->sku})",
                ])
                ->all(),
            'timezone' => Setting::current()->timezone,
        ];
    }

    /**
     * De UTC a la hora del sitio, en el formato de `datetime-local`.
     */
    private static function forInput(?Carbon $date, string $timezone): string
    {
        return $date === null ? '' : $date->copy()->setTimezone($timezone)->format('Y-m-d\TH:i');
    }
}
