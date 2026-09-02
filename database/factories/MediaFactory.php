<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MediaProcessingStatus;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = Str::lower(Str::ulid()->toString());

        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'alt_text' => fake()->sentence(4),
            'description' => null,
            'disk' => 'public',
            'path' => "biblioteca/{$id}.png",
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size' => fake()->numberBetween(20_000, 500_000),
            'width' => 1200,
            'height' => 630,
            'variants' => [
                'thumbnail' => "biblioteca/variantes/{$id}-thumbnail.png",
                'medium' => "biblioteca/variantes/{$id}-medium.png",
            ],
            'processing_status' => MediaProcessingStatus::Done,
            'processing_error' => null,
            'uploaded_by' => User::factory()->editor(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'processing_status' => MediaProcessingStatus::Pending,
            'variants' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'processing_status' => MediaProcessingStatus::Failed,
            'processing_error' => 'No se ha podido leer la imagen.',
            'variants' => null,
        ]);
    }
}
