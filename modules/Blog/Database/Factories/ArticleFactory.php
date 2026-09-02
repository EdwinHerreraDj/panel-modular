<?php

declare(strict_types=1);

namespace Modules\Blog\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Blog\Enums\ArticleStatus;
use Modules\Blog\Enums\ArticleVisibility;
use Modules\Blog\Models\Article;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $content = '<p>'.implode('</p><p>', fake()->paragraphs(3)).'</p>';

        return [
            'title' => ucfirst(fake()->unique()->sentence(5)),
            'excerpt' => fake()->sentence(),
            'content' => $content,
            'content_text' => strip_tags($content),
            'featured_media_id' => null,
            'featured_image_alt' => null,
            'author_id' => User::factory()->author(),
            'status' => ArticleStatus::Draft,
            'visibility' => ArticleVisibility::Public,
            'published_at' => null,
            'scheduled_for' => null,
            'reading_minutes' => 2,
            'position' => 0,
            'meta_title' => null,
            'meta_description' => null,
            'canonical_url' => null,
            'og_title' => null,
            'og_description' => null,
            'og_image_path' => null,
            'is_indexable' => true,
            'is_slug_locked' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => ArticleStatus::Published,
            'published_at' => now()->subDay(),
            'is_slug_locked' => true,
        ]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn (): array => ['status' => ArticleStatus::PendingReview]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => ArticleStatus::Approved]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => ArticleStatus::Archived,
            'published_at' => now()->subMonth(),
        ]);
    }

    /**
     * Programado para una fecha futura, salvo que se indique otra.
     */
    public function scheduled(?string $when = null): static
    {
        return $this->state(fn (): array => [
            'status' => ArticleStatus::Scheduled,
            'scheduled_for' => $when ?? now()->addDay(),
        ]);
    }

    public function unlisted(): static
    {
        return $this->state(fn (): array => ['visibility' => ArticleVisibility::Unlisted]);
    }

    public function by(User $author): static
    {
        return $this->state(fn (): array => ['author_id' => $author->getKey()]);
    }
}
