<?php

declare(strict_types=1);

namespace Modules\Tienda\Http\Resources;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tienda\Models\Category;
use Modules\Tienda\Models\Product;
use Modules\Tienda\Models\ProductImage;
use Modules\Tienda\Models\ProductOption;
use Modules\Tienda\Models\ProductVariant;
use Modules\Tienda\Support\CategoryTree;
use Modules\Tienda\Support\Price;
use Modules\Tienda\Support\TiendaSettings;
use Modules\Tienda\Support\WhatsApp;

/**
 * Un artículo tal y como lo recibe la web de la tienda.
 *
 * Mismas reglas que el resto de la API: claves en español y lo que está a null
 * no se envía. Los booleanos sí van siempre, también cuando son `false`.
 *
 * El precio llega ya calculado, con la promoción aplicada: la web no tiene que
 * saber qué promociones hay ni cómo se combinan. Solo pinta `precio` y, si
 * llega, tacha `precio_anterior`.
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
        ['price' => $price, 'from' => $from] = $product->displayPrice();
        $status = $product->stockStatus();
        $cover = $product->cover();

        return self::withoutNulls([
            'id' => $product->id,
            'nombre' => $product->name,
            'slug' => $product->slug,
            'referencia' => $product->sku,
            'marca' => $product->brand,
            'resumen' => $product->summary,
            'categoria' => self::category($product->category_id),
            ...self::price($price),
            // Con variantes de precios distintos es el más barato: «desde 19 €».
            'precio_desde' => $from,
            'disponibilidad' => $status->value,
            'disponibilidad_texto' => $status->label(),
            'unidades' => self::units($product->track_stock, $product->stock),
            'destacado' => $product->is_featured,
            'tiene_variantes' => $product->has_variants,
            'imagen' => $cover === null ? null : self::image($cover->media, $cover->alt ?? $product->name),
            'whatsapp_url' => WhatsApp::forProduct($product),
            'actualizado_en' => $product->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * La ficha completa: lo del listado y todo lo que solo hace falta al abrir
     * el artículo.
     *
     * @param  list<Product>  $related
     * @return array<string, mixed>
     */
    public static function detail(Product $product, array $related, Request $request): array
    {
        $tree = CategoryTree::current();

        $categories = [];

        foreach ($product->categoryIds() as $id) {
            if (($category = self::category($id)) !== null) {
                $categories[] = $category;
            }
        }

        $path = $product->category_id !== null && $tree->isPublic($product->category_id)
            ? array_map(
                fn (Category $category): array => ['nombre' => $category->name, 'slug' => $category->slug],
                $tree->path($product->category_id),
            )
            : [];

        $options = $product->options;

        return (new self($product))->toArray($request) + self::withoutNulls([
            'descripcion_html' => $product->description,
            'imagenes' => $product->images
                ->map(fn (ProductImage $image): ?array => self::image($image->media, $image->alt ?? $product->name))
                ->filter()
                ->values()
                ->all(),
            'categorias' => $categories,
            'ruta' => $path,
            'opciones' => $options->map(fn (ProductOption $option): array => [
                'nombre' => $option->name,
                'valores' => $option->values,
            ])->all(),
            'variantes' => $product->activeVariants()
                ->map(fn (ProductVariant $variant): array => self::variant($product, $variant, $options->all()))
                ->all(),
            'seo' => self::withoutNulls([
                'titulo' => $product->meta_title,
                'descripcion' => $product->meta_description ?? $product->summary,
            ]),
            'relacionados' => self::collection($related)->resolve(),
        ]);
    }

    /**
     * @param  list<ProductOption>  $options
     * @return array<string, mixed>
     */
    private static function variant(Product $product, ProductVariant $variant, array $options): array
    {
        $variant->setRelation('product', $product);
        $status = $variant->stockStatus();
        $values = $variant->values();
        $named = [];

        foreach ($options as $index => $option) {
            if (isset($values[$index])) {
                $named[$option->name] = $values[$index];
            }
        }

        return self::withoutNulls([
            'id' => $variant->id,
            'nombre' => $variant->label(),
            'referencia' => $variant->sku,
            // { "Talla": "M", "Color": "Rojo" }: la web encuentra la variante
            // elegida comparando con lo que ha marcado quien compra.
            'opciones' => $named,
            ...self::price(Price::of($product, $variant)),
            'disponibilidad' => $status->value,
            'disponibilidad_texto' => $status->label(),
            'unidades' => self::units($product->track_stock, $variant->stock),
            'imagen' => self::image($variant->media, $product->name.' '.$variant->label()),
            'whatsapp_url' => WhatsApp::forProduct($product, $variant),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function price(Price $price): array
    {
        return [
            'precio' => $price->final,
            'precio_texto' => $price->formatted(),
            'precio_anterior' => $price->previous(),
            'precio_anterior_texto' => $price->formattedPrevious(),
            'descuento_porcentaje' => $price->discountPercentage(),
            'promocion' => $price->promotion === null ? null : self::withoutNulls([
                'nombre' => $price->promotion->name,
                'descripcion' => $price->promotion->description,
                'termina_en' => $price->promotion->ends_at?->toIso8601String(),
            ]),
        ];
    }

    /**
     * Solo si la tienda ha elegido enseñar cuántas quedan. Hay quien prefiere
     * no dar la cifra y quedarse en «quedan pocas».
     */
    private static function units(bool $tracked, int $units): ?int
    {
        return $tracked && TiendaSettings::showStockQuantity() ? max(0, $units) : null;
    }

    /**
     * @return array{nombre: string, slug: string}|null
     */
    private static function category(?int $id): ?array
    {
        $tree = CategoryTree::current();
        $category = $tree->find($id);

        if ($category === null || ! $tree->isPublic($category->id)) {
            return null;
        }

        return ['nombre' => $category->name, 'slug' => $category->slug];
    }

    /**
     * @return array<string, string>|null
     */
    public static function image(?Media $media, string $alt): ?array
    {
        if ($media === null) {
            return null;
        }

        return self::withoutNulls([
            'alt' => $alt,
            'miniatura' => $media->variantUrl('thumbnail'),
            'mediana' => $media->variantUrl('medium'),
            'grande' => $media->variantUrl('large'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function withoutNulls(array $data): array
    {
        return array_filter($data, fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
