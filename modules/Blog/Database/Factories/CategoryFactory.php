<?php

declare(strict_types=1);

namespace Modules\Blog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Blog\Models\Category;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(),
            'meta_title' => null,
            'meta_description' => null,
            'position' => 0,
            // El slug lo genera el modelo a partir del nombre.
            'is_slug_locked' => false,
        ];
    }

    /**
     * Categoría ya en uso: su slug no debe cambiar al renombrarla.
     */
    public function slugLocked(): static
    {
        return $this->state(fn (): array => ['is_slug_locked' => true]);
    }
}
