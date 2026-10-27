<?php

declare(strict_types=1);

namespace Modules\Tienda\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tienda\Enums\PromotionScope;
use Modules\Tienda\Enums\PromotionType;
use Modules\Tienda\Models\Promotion;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /** @var class-string<Promotion> */
    protected $model = Promotion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Rebajas '.fake()->unique()->word(),
            'type' => PromotionType::Percentage,
            'value' => 20,
            'scope' => PromotionScope::All,
            'is_active' => true,
        ];
    }

    public function fixed(float $amount): static
    {
        return $this->state(fn (): array => ['type' => PromotionType::Fixed, 'value' => $amount]);
    }

    public function percentage(float $percent): static
    {
        return $this->state(fn (): array => ['type' => PromotionType::Percentage, 'value' => $percent]);
    }

    public function forCategories(): static
    {
        return $this->state(fn (): array => ['scope' => PromotionScope::Categories]);
    }

    public function forProducts(): static
    {
        return $this->state(fn (): array => ['scope' => PromotionScope::Products]);
    }
}
