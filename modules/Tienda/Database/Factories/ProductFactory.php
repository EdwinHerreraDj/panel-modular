<?php

declare(strict_types=1);

namespace Modules\Tienda\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tienda\Models\Category;
use Modules\Tienda\Models\Product;

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
            'sku' => strtoupper(fake()->unique()->bothify('??-####')),
            'summary' => fake()->sentence(),
            'description' => '<p>'.fake()->paragraph().'</p>',
            'price' => 50.0,
            'track_stock' => true,
            'stock' => 10,
            'is_visible' => false,
            'position' => 0,
        ];
    }

    public function visible(): static
    {
        return $this->state(fn (): array => ['is_visible' => true]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }

    public function in(Category $category): static
    {
        return $this->state(fn (): array => ['category_id' => $category->id]);
    }

    public function withStock(int $units): static
    {
        return $this->state(fn (): array => ['stock' => $units]);
    }

    public function withoutPrice(): static
    {
        return $this->state(fn (): array => ['price' => null]);
    }
}
