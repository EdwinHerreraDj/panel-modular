<?php

declare(strict_types=1);

namespace Modules\Catalogo\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalogo\Enums\ProductAvailability;
use Modules\Catalogo\Models\Product;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /** @var class-string<Product> */
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'reference' => strtoupper(fake()->unique()->bothify('??-####')),
            'summary' => fake()->sentence(),
            'description' => '<p>'.fake()->paragraph().'</p>',
            'price' => fake()->randomFloat(2, 5, 900),
            'sale_price' => null,
            'availability' => ProductAvailability::Available,
            'is_visible' => false,
            'position' => 0,
        ];
    }

    public function onSale(float $salePrice = 99.0): static
    {
        return $this->state(fn (): array => ['price' => 149.0, 'sale_price' => $salePrice]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (): array => ['availability' => ProductAvailability::OutOfStock]);
    }

    public function visible(): static
    {
        return $this->state(fn (): array => ['is_visible' => true]);
    }

    public function withoutPrice(): static
    {
        return $this->state(fn (): array => ['price' => null]);
    }
}
