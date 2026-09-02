<?php

declare(strict_types=1);

namespace Modules\Catalogo\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalogo\Models\Product;

/**
 * Un producto tal y como lo recibe la web pública.
 *
 * Las claves van en español, igual que el resto de la API. Lo que está a null
 * no se envía: la web no tiene que distinguir entre «sin precio» y «precio
 * cero» revisando tipos, ni preguntarse si `precio_anterior` significa que
 * hay oferta —si está, la hay—.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        return array_filter([
            'id' => $product->id,
            'nombre' => $product->name,
            'slug' => $product->slug,
            'referencia' => $product->reference,
            'resumen' => $product->summary,
            // `precio` es siempre lo que se paga hoy. Si hay oferta llega
            // además `precio_anterior`, que es lo que la web tacha.
            'precio' => $product->currentPrice() === null ? null : (float) $product->currentPrice(),
            'precio_texto' => $product->formattedPrice(),
            'precio_anterior' => $product->previousPrice() === null ? null : (float) $product->previousPrice(),
            'precio_anterior_texto' => $product->formattedPreviousPrice(),
            'descuento_porcentaje' => $product->discountPercentage(),
            'disponibilidad' => $product->availability->value,
            'disponibilidad_texto' => $product->availability->label(),
            'imagen' => $product->image === null ? null : array_filter([
                'alt' => $product->image_alt,
                'miniatura' => $product->imageUrl('thumbnail'),
                'mediana' => $product->imageUrl('medium'),
                'grande' => $product->imageUrl('large'),
            ], fn (mixed $v): bool => $v !== null),
            'actualizado_en' => $product->updated_at?->toIso8601String(),
        ], fn (mixed $value): bool => $value !== null);
    }
}
