<?php

declare(strict_types=1);

namespace Modules\Tienda\Actions;

use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Support\ApiCache;
use Illuminate\Support\Facades\DB;
use Modules\Tienda\Enums\PromotionScope;
use Modules\Tienda\Models\Promotion;
use Modules\Tienda\Support\PromotionResolver;

/**
 * Crea o actualiza una promoción.
 *
 * Solo se guardan las categorías o los artículos que corresponden al alcance
 * elegido: si se cambia de «categorías» a «toda la tienda», las categorías de
 * antes no se quedan escondidas dentro esperando a confundir a alguien.
 */
final class SavePromotion
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Promotion $promotion, array $data): Promotion
    {
        $isNew = $promotion === null;

        $promotion = DB::transaction(function () use ($promotion, $data, $isNew): Promotion {
            $promotion ??= new Promotion;

            $promotion->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'value' => $data['value'],
                'scope' => $data['scope'],
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $changes = $isNew ? null : array_diff_key($promotion->getDirty(), ['updated_at' => true]);

            $promotion->save();

            $promotion->categories()->sync(
                $promotion->scope === PromotionScope::Categories ? array_map('intval', $data['category_ids'] ?? []) : [],
            );

            $promotion->products()->sync(
                $promotion->scope === PromotionScope::Products ? array_map('intval', $data['product_ids'] ?? []) : [],
            );

            $this->recordActivity->handle(
                $promotion,
                $isNew ? ActivityEvent::Created : ActivityEvent::Updated,
                ($isNew ? 'Promoción creada: ' : 'Promoción modificada: ').$promotion->name,
                $changes === [] ? null : $changes,
            );

            return $promotion;
        });

        // Las tablas intermedias no disparan eventos del modelo: sin esto,
        // añadir una categoría a una promoción no se vería en la web hasta que
        // caducara la caché.
        PromotionResolver::forget();
        ApiCache::flush();

        return $promotion;
    }
}
