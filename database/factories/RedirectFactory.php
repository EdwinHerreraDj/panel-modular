<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Redirect>
 */
class RedirectFactory extends Factory
{
    protected $model = Redirect::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_path' => Str::slug(fake()->unique()->sentence(3)),
            'to_path' => Str::slug(fake()->sentence(3)),
            'status_code' => 301,
            'is_automatic' => false,
            'redirectable_type' => null,
            'redirectable_id' => null,
            'hits' => 0,
            'last_hit_at' => null,
        ];
    }

    public function automatic(): static
    {
        return $this->state(fn (): array => ['is_automatic' => true]);
    }
}
