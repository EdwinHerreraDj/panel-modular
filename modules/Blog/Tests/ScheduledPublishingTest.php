<?php

declare(strict_types=1);

use Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Blog\Enums\ArticleStatus;
use Modules\Blog\Models\Article;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Tag;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('publica los artículos cuya fecha programada ya ha pasado', function (): void {
    $due = Article::factory()->scheduled()->create(['title' => 'Le toca ya']);
    $due->forceFill(['scheduled_for' => now()->subMinute()])->save();

    $future = Article::factory()->scheduled()->create(['title' => 'Todavía no']);

    $this->artisan('articulos:publicar-programados')
        ->expectsOutputToContain('Le toca ya')
        ->assertSuccessful();

    $due->refresh();
    $future->refresh();

    expect($due->status)->toBe(ArticleStatus::Published)
        ->and($due->published_at)->not->toBeNull()
        ->and($due->scheduled_for)->toBeNull()
        ->and($future->status)->toBe(ArticleStatus::Scheduled);
});

it('respeta la hora programada como fecha de publicación', function (): void {
    $when = now()->subMinutes(5);
    $article = Article::factory()->scheduled()->create();
    $article->forceFill(['scheduled_for' => $when])->save();

    $this->artisan('articulos:publicar-programados')->assertSuccessful();

    expect($article->fresh()->published_at?->format('Y-m-d H:i'))->toBe($when->format('Y-m-d H:i'));
});

it('congela los slugs al publicar automáticamente', function (): void {
    $category = Category::factory()->create();
    $tag = Tag::factory()->create();

    $article = Article::factory()->scheduled()->create();
    $article->forceFill(['scheduled_for' => now()->subMinute()])->save();
    $article->categories()->attach($category);
    $article->tags()->attach($tag);

    $this->artisan('articulos:publicar-programados')->assertSuccessful();

    expect($article->fresh()->is_slug_locked)->toBeTrue()
        ->and($category->fresh()->is_slug_locked)->toBeTrue()
        ->and($tag->fresh()->is_slug_locked)->toBeTrue();
});

it('deja constancia de la publicación automática', function (): void {
    $article = Article::factory()->scheduled()->create(['title' => 'Programado']);
    $article->forceFill(['scheduled_for' => now()->subMinute()])->save();

    $this->artisan('articulos:publicar-programados')->assertSuccessful();

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => (new Article)->getMorphClass(),
        'subject_id' => $article->id,
        // Sin usuario en sesión: el evento queda sin autor.
        'user_id' => null,
    ]);
});

it('no toca nada si no hay artículos pendientes', function (): void {
    Article::factory()->published()->create();
    Article::factory()->create();

    $this->artisan('articulos:publicar-programados')
        ->expectsOutputToContain('No hay artículos programados pendientes')
        ->assertSuccessful();
});

it('se puede ejecutar dos veces seguidas sin efectos adicionales', function (): void {
    $article = Article::factory()->scheduled()->create();
    $article->forceFill(['scheduled_for' => now()->subMinute()])->save();

    $this->artisan('articulos:publicar-programados')->assertSuccessful();
    $publishedAt = $article->fresh()->published_at;

    $this->artisan('articulos:publicar-programados')->assertSuccessful();

    expect($article->fresh()->published_at?->toIso8601String())->toBe($publishedAt?->toIso8601String());
});
