<?php

declare(strict_types=1);

namespace Modules\Catalogo\Actions;

use App\Actions\Media\SyncMediaUsage;
use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Modules\Catalogo\Enums\ProductAvailability;
use Modules\Catalogo\Models\Product;

/**
 * Crea o actualiza un producto.
 *
 * Lo poco que hay que cuidar está aquí y no en el controlador: limpiar el HTML
 * de la descripción, registrar dónde se usa la imagen para que la biblioteca no
 * deje borrarla, y congelar la dirección en cuanto el producto se hace visible.
 */
final class SaveProduct
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
        private readonly SyncMediaUsage $syncMediaUsage,
        private readonly RecordActivity $recordActivity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Product $product, array $data): Product
    {
        $isNew = $product === null;

        return DB::transaction(function () use ($product, $data, $isNew): Product {
            $product ??= new Product;

            $product->fill([
                'name' => $data['name'],
                'reference' => $data['reference'] ?? null,
                'summary' => $data['summary'] ?? null,
                // Nunca se guarda el HTML tal y como llega.
                'description' => $this->sanitizer->sanitize($data['description'] ?? null),
                'price' => $data['price'] ?? null,
                'sale_price' => $data['sale_price'] ?? null,
                'availability' => $data['availability'] ?? ProductAvailability::Available->value,
                'image_alt' => $data['image_alt'] ?? null,
                'is_visible' => (bool) ($data['is_visible'] ?? false),
                'position' => (int) ($data['position'] ?? 0),
            ]);

            $product->image_media_id = $data['image_media_id'] ?? null;
            $product->save();

            // Una vez visible, la dirección deja de seguir al nombre: si
            // cambiara, se romperían los enlaces que ya haya compartido la
            // gente. Un cambio posterior deja su redirección sola.
            if ($product->is_visible) {
                $product->lockSlug();
            }

            $this->syncMediaUsage->set($product, 'image', $product->image_media_id);

            $this->recordActivity->handle(
                $product,
                $isNew ? ActivityEvent::Created : ActivityEvent::Updated,
                ($isNew ? 'Producto creado: ' : 'Producto modificado: ').$product->name,
                $isNew ? null : $product->getChanges(),
            );

            return $product;
        });
    }
}
