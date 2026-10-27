<?php

declare(strict_types=1);

namespace Modules\Tienda\Actions;

use App\Actions\Media\SyncMediaUsage;
use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Support\ApiCache;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Modules\Tienda\Enums\StockMovementType;
use Modules\Tienda\Models\Product;
use Modules\Tienda\Models\ProductVariant;

/**
 * Crea o actualiza un artículo con todo lo que cuelga de él.
 *
 * Un artículo se guarda de una vez —datos, galería, opciones, variantes y
 * existencias— y dentro de una transacción: si falla algo a mitad, no puede
 * quedar un artículo con la mitad de sus tallas.
 *
 * El stock no se escribe directamente. Si el número del formulario no
 * coincide con el que había, se registra un recuento: así el historial explica
 * cualquier cambio, también los hechos desde la ficha.
 */
final class SaveProduct
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
        private readonly SyncMediaUsage $syncMediaUsage,
        private readonly RecordActivity $recordActivity,
        private readonly RegisterStockMovement $registerStockMovement,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  bool  $applyStock  Solo quien puede gestionar el stock lo cambia desde la ficha
     */
    public function handle(?Product $product, array $data, bool $applyStock = true): Product
    {
        $isNew = $product === null;

        $product = DB::transaction(function () use ($product, $data, $isNew, $applyStock): Product {
            $product ??= new Product;

            /** @var list<array{name: string, values: list<string>}> $options */
            $options = array_values($data['options'] ?? []);
            $hasVariants = $options !== [];

            $product->fill([
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'],
                'sku' => $data['sku'] ?? null,
                'brand' => $data['brand'] ?? null,
                'summary' => $data['summary'] ?? null,
                // Nunca se guarda el HTML tal y como llega.
                'description' => $this->sanitizer->sanitize($data['description'] ?? null),
                'price' => $data['price'] ?? null,
                'has_variants' => $hasVariants,
                'track_stock' => (bool) ($data['track_stock'] ?? true),
                'low_stock_threshold' => $data['low_stock_threshold'] ?? null,
                'is_visible' => (bool) ($data['is_visible'] ?? false),
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'position' => (int) ($data['position'] ?? 0),
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
            ]);

            $changes = $isNew ? null : array_diff_key($product->getDirty(), ['updated_at' => true]);

            $product->save();

            // Una vez en la web, la dirección deja de seguir al nombre.
            if ($product->is_visible) {
                $product->lockSlug();
            }

            // La principal no se repite entre las adicionales.
            $product->categories()->sync(array_values(array_diff(
                array_map('intval', $data['category_ids'] ?? []),
                [(int) $product->category_id],
            )));

            $this->syncImages($product, $data['images'] ?? []);

            if ($hasVariants) {
                $this->syncVariants($product, $options, array_values($data['variants'] ?? []), $applyStock);
            } else {
                $this->removeVariants($product);

                if ($applyStock && ($data['stock'] ?? null) !== null) {
                    $this->setStock($product, null, (int) $data['stock'], $data['stock_loaded'] ?? null, $isNew);
                }
            }

            $this->syncMediaUsages($product);

            $this->recordActivity->handle(
                $product,
                $isNew ? ActivityEvent::Created : ActivityEvent::Updated,
                ($isNew ? 'Artículo creado: ' : 'Artículo modificado: ').$product->name,
                $changes === [] ? null : $changes,
            );

            return $product->refresh();
        });

        // Las categorías y la galería van en otras tablas, que no avisan a la
        // caché de la API. Se vacía al final, ya confirmada la transacción.
        ApiCache::flush();

        return $product;
    }

    /**
     * La galería se reescribe entera: son unas pocas filas y el orden es
     * justo lo que suele cambiar.
     *
     * @param  list<array{media_id: int, alt?: string|null}>  $images
     */
    private function syncImages(Product $product, array $images): void
    {
        $product->images()->delete();

        foreach ($images as $position => $image) {
            $product->images()->create([
                'media_id' => (int) $image['media_id'],
                'alt' => $image['alt'] ?? null,
                'position' => $position,
            ]);
        }
    }

    /**
     * Opciones y variantes.
     *
     * Una variante que ya existía se reconoce por su id o, si no llega, por la
     * combinación de valores: así sobrevive a que se añada una talla nueva y
     * conserva su stock y su historial.
     *
     * @param  list<array{name: string, values: list<string>}>  $options
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncVariants(Product $product, array $options, array $rows, bool $applyStock): void
    {
        $product->options()->delete();

        foreach ($options as $index => $option) {
            $product->options()->create([
                'name' => $option['name'],
                'values' => $option['values'],
                'position' => $index + 1,
            ]);
        }

        $existing = $product->variants()->get();
        $byId = $existing->keyBy('id');
        $byKey = $existing->keyBy(fn (ProductVariant $variant): string => self::key($variant->values()));
        $kept = [];

        foreach ($rows as $position => $row) {
            /** @var list<string> $values */
            $values = array_values($row['values'] ?? []);

            $variant = $byId->get($row['id'] ?? null) ?? $byKey->get(self::key($values));
            $isNewVariant = $variant === null;
            $variant ??= new ProductVariant;

            $variant->fill([
                'option1' => $values[0] ?? null,
                'option2' => $values[1] ?? null,
                'option3' => $values[2] ?? null,
                'sku' => $row['sku'] ?? null,
                'price' => $row['price'] ?? null,
                'media_id' => $row['media_id'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? true),
                'position' => $position,
            ]);

            $variant->product()->associate($product);
            $variant->save();
            $kept[] = $variant->id;

            if ($applyStock && ($row['stock'] ?? null) !== null) {
                $this->setStock($product, $variant, (int) $row['stock'], $row['stock_loaded'] ?? null, $isNewVariant);
            }
        }

        $product->variants()->whereNotIn('id', $kept === [] ? [0] : $kept)->delete();
        $product->refreshStockFromVariants();
    }

    /**
     * Si deja de tener variantes, el stock vuelve a ser el del artículo, que
     * se rellena con el número del formulario.
     */
    private function removeVariants(Product $product): void
    {
        $product->options()->delete();
        $product->variants()->delete();
    }

    /**
     * Lo nuevo entra como «stock inicial»; lo que ya existía y cambia, como
     * recuento. Si el número no cambia no se apunta nada.
     *
     * `$loaded` es el número que había cuando se abrió la ficha. Si quien la
     * guarda no lo ha tocado, no se aplica: mientras tenía el formulario
     * abierto pudo registrarse una venta, y reescribir el número la borraría.
     */
    private function setStock(Product $product, ?ProductVariant $variant, int $units, mixed $loaded, bool $isNew): void
    {
        if (! $isNew && is_numeric($loaded) && (int) $loaded === $units) {
            return;
        }

        $current = $isNew ? 0 : (int) ($variant === null ? $product->stock : $variant->stock);

        if ($units === $current) {
            return;
        }

        $this->registerStockMovement->handle(
            $product,
            $variant,
            $isNew ? StockMovementType::Entry : StockMovementType::Count,
            $units,
            $isNew ? 'Stock inicial' : 'Ajuste desde la ficha del artículo',
        );
    }

    /**
     * Dónde se usa cada foto, para que la biblioteca no deje borrarla.
     */
    private function syncMediaUsages(Product $product): void
    {
        $this->syncMediaUsage->forget($product);

        foreach ($product->images()->get() as $image) {
            $this->syncMediaUsage->set($product, 'gallery-'.$image->position, $image->media_id);
        }

        foreach ($product->variants()->whereNotNull('media_id')->get() as $variant) {
            $this->syncMediaUsage->set($product, 'variant-'.$variant->id, $variant->media_id);
        }
    }

    /**
     * @param  list<string>  $values
     */
    private static function key(array $values): string
    {
        return mb_strtolower(implode("\x1F", array_map('trim', $values)));
    }
}
