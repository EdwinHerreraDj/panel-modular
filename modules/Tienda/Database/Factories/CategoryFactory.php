<?php

declare(strict_types=1);

namespace Modules\Tienda\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tienda\Models\Category;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /** @var class-string<Category> */
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(),
            'is_visible' => true,
            'position' => 0,
        ];
    }

    public function childOf(Category $parent): static
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id]);
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => ['is_visible' => false]);
    }
}
