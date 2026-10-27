<?php

declare(strict_types=1);

namespace Modules\Tienda\Support;

use Modules\Tienda\Models\Product;
use Modules\Tienda\Models\ProductVariant;
use Modules\Tienda\Models\Promotion;

/**
 * El precio de un artículo o de una variante, ya con la promoción aplicada.
 *
 * `final` es siempre lo que se paga hoy. `base` solo se enseña tachado cuando
 * hay oferta. Sin precio, los dos son null: «precio a consultar».
 */
final readonly class Price
{
    public function __construct(
        public ?float $base,
        public ?float $final,
        public ?Promotion $promotion = null,
    ) {}

    public static function of(Product $product, ?ProductVariant $variant = null): self
    {
        $amount = $variant->price ?? $product->price;

        if ($amount === null) {
            return new self(null, null);
        }

        $base = (float) $amount;
        $best = PromotionResolver::current()->bestFor($product, $base);

        if ($best === null) {
            return new self($base, $base);
        }

        return new self($base, round($base - $best['discount'], 2), $best['promotion']);
    }

    public function isOnSale(): bool
    {
        return $this->base !== null && $this->final !== null && $this->final < $this->base;
    }

    /**
     * El precio tachado, solo si hay oferta.
     */
    public function previous(): ?float
    {
        return $this->isOnSale() ? $this->base : null;
    }

    /**
     * Cuánto se ahorra, redondeado. Para el «-20 %» de la web.
     */
    public function discountPercentage(): ?int
    {
        if (! $this->isOnSale() || ! $this->base) {
            return null;
        }

        return (int) round(($this->base - (float) $this->final) / $this->base * 100);
    }

    public function formatted(): ?string
    {
        return Money::format($this->final);
    }

    public function formattedPrevious(): ?string
    {
        return Money::format($this->previous());
    }
}
