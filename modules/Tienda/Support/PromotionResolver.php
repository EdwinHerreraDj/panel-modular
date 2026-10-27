<?php

declare(strict_types=1);

namespace Modules\Tienda\Support;

use Modules\Tienda\Enums\PromotionScope;
use Modules\Tienda\Enums\PromotionType;
use Modules\Tienda\Models\Product;
use Modules\Tienda\Models\Promotion;

/**
 * Qué promoción le toca a cada artículo ahora mismo.
 *
 * Las promociones en vigor se leen una vez por petición, con sus categorías ya
 * desplegadas a todas las subcategorías. Después, saber si un artículo está de
 * oferta es mirar un par de arrays: un listado de 50 artículos no lanza 50
 * consultas.
 *
 * Si alcanzan varias, gana la que más descuenta sobre ese precio concreto.
 */
final class PromotionResolver
{
    private const KEY = 'tienda.promotions';

    /**
     * @param  list<array{promotion: Promotion, all: bool, categories: array<int, true>, products: array<int, true>}>  $rules
     */
    private function __construct(private readonly array $rules) {}

    public static function current(): self
    {
        $request = request();
        $resolver = $request->attributes->get(self::KEY);

        if (! $resolver instanceof self) {
            $resolver = self::load();
            $request->attributes->set(self::KEY, $resolver);
        }

        return $resolver;
    }

    public static function forget(): void
    {
        request()->attributes->remove(self::KEY);
    }

    private static function load(): self
    {
        $tree = CategoryTree::current();
        $rules = [];

        $promotions = Promotion::query()
            ->running()
            ->with(['categories:id', 'products:id'])
            ->get();

        foreach ($promotions as $promotion) {
            $categories = [];

            foreach ($promotion->categories as $category) {
                foreach ($tree->descendantIds($category->id) as $id) {
                    $categories[$id] = true;
                }
            }

            $rules[] = [
                'promotion' => $promotion,
                'all' => $promotion->scope === PromotionScope::All,
                'categories' => $categories,
                'products' => array_fill_keys($promotion->products->pluck('id')->all(), true),
            ];
        }

        return new self($rules);
    }

    /**
     * La mejor promoción para este artículo y este precio, con lo que descuenta.
     *
     * @return array{promotion: Promotion, discount: float}|null
     */
    public function bestFor(Product $product, float $price): ?array
    {
        if ($price <= 0) {
            return null;
        }

        $best = null;

        foreach ($this->rules as $rule) {
            if (! $this->applies($rule, $product)) {
                continue;
            }

            $discount = self::discount($rule['promotion'], $price);

            if ($discount > 0 && ($best === null || $discount > $best['discount'])) {
                $best = ['promotion' => $rule['promotion'], 'discount' => $discount];
            }
        }

        return $best;
    }

    /**
     * Lo que alcanzan las promociones en vigor, para filtrar «en oferta» con
     * una consulta.
     *
     * @return array{all: bool, categories: list<int>, products: list<int>}
     */
    public function reach(): array
    {
        $all = false;
        $categories = [];
        $products = [];

        foreach ($this->rules as $rule) {
            $all = $all || $rule['all'];
            $categories += $rule['categories'];
            $products += $rule['products'];
        }

        return ['all' => $all, 'categories' => array_keys($categories), 'products' => array_keys($products)];
    }

    /**
     * Cuánto descuenta una promoción sobre un precio. Nunca más que el precio:
     * 10 € de descuento sobre un artículo de 8 € lo deja gratis, no en negativo.
     */
    public static function discount(Promotion $promotion, float $price): float
    {
        $amount = $promotion->type === PromotionType::Percentage
            ? $price * (float) $promotion->value / 100
            : (float) $promotion->value;

        return round(min($amount, $price), 2);
    }

    /**
     * @param  array{promotion: Promotion, all: bool, categories: array<int, true>, products: array<int, true>}  $rule
     */
    private function applies(array $rule, Product $product): bool
    {
        if ($rule['all'] || isset($rule['products'][$product->id])) {
            return true;
        }

        if ($rule['categories'] === []) {
            return false;
        }

        foreach ($product->categoryIds() as $id) {
            if (isset($rule['categories'][$id])) {
                return true;
            }
        }

        return false;
    }
}
