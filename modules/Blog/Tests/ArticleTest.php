<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Models\User;
use App\Modules\ModuleRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;
use Modules\Blog\Enums\ArticleStatus;
use Modules\Blog\Models\Article;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Tag;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->editor()->create();
});

// `articlePayload()` vive en tests/Pest.php: lo comparten varias pruebas.

it('lista los artículos a quien puede verlos', function (): void {
    Article::factory()->count(3)->create();

    $this->actingAs($this->editor)
        ->get(route('articles.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Blog::Articles/Index')
            ->has('articles.data', 3));
});

it('crea un artículo generando slug, texto plano y tiempo de lectura', function (): void {
    $this->actingAs($this->editor)
        ->post(route('articles.store'), articlePayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $article = Article::query()->firstOrFail();

    expect($article->slug)->toBe('como-elegir-las-persianas-de-casa')
        ->and($article->content_text)->toBe('El primer paso es medir el hueco.')
        ->and($article->reading_minutes)->toBe(1)
        ->and($article->author_id)->toBe($this->editor->id)
        ->and($article->status)->toBe(ArticleStatus::Draft);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => (new Article)->getMorphClass(),
        'subject_id' => $article->id,
        'event' => ActivityEvent::Created->value,
    ]);
});

it('sanitiza el contenido antes de guardarlo', function (): void {
    $this->actingAs($this->editor)
        ->post(route('articles.store'), articlePayload([
            'content' => '<p>Texto legítimo</p><script>alert("xss")</script><p onclick="robar()">Otro</p>',
        ]))
        ->assertSessionHasNoErrors();

    $content = (string) Article::query()->firstOrFail()->content;

    expect($content)->toContain('Texto legítimo')
        ->and($content)->not->toContain('<script')
        ->and($content)->not->toContain('onclick')
        ->and($content)->not->toContain('alert');
});

it('exige el título en español', function (): void {
    $this->actingAs($this->editor)
        ->from(route('articles.create'))
        ->post(route('articles.store'), articlePayload(['title' => '']))
        ->assertSessionHasErrors(['title' => 'El campo título es obligatorio.']);
});

it('asigna categorías y etiquetas', function (): void {
    $categories = Category::factory()->count(2)->create();
    $tags = Tag::factory()->count(3)->create();

    $this->actingAs($this->editor)
        ->post(route('articles.store'), articlePayload([
            'category_ids' => $categories->pluck('id')->all(),
            'tag_ids' => $tags->pluck('id')->all(),
        ]))
        ->assertSessionHasNoErrors();

    $article = Article::query()->firstOrFail();

    expect($article->categories)->toHaveCount(2)
        ->and($article->tags)->toHaveCount(3);
});

it('rechaza categorías que no existen', function (): void {
    $this->actingAs($this->editor)
        ->from(route('articles.create'))
        ->post(route('articles.store'), articlePayload(['category_ids' => [9999]]))
        ->assertSessionHasErrors('category_ids.0');
});

it('actualiza un artículo y recalcula el tiempo de lectura', function (): void {
    $article = Article::factory()->by($this->editor)->create();

    $words = '<p>'.implode(' ', array_fill(0, 600, 'palabra')).'</p>';

    $this->actingAs($this->editor)
        ->post(route('articles.update', $article), articlePayload([
            'title' => $article->title,
            'content' => $words,
        ]))
        ->assertSessionHasNoErrors();

    expect($article->fresh()->reading_minutes)->toBe(3);
});

it('sella la fecha de publicación al publicar', function (): void {
    $article = Article::factory()->by($this->editor)->create();

    expect($article->published_at)->toBeNull();

    $this->actingAs($this->editor)
        ->post(route('articles.update', $article), articlePayload([
            'title' => $article->title,
            'status' => 'published',
        ]))
        ->assertSessionHasNoErrors();

    $article->refresh();

    expect($article->status)->toBe(ArticleStatus::Published)
        ->and($article->published_at)->not->toBeNull();
});

it('congela el slug del artículo y de sus categorías al publicarlo', function (): void {
    $category = Category::factory()->create();
    $tag = Tag::factory()->create();
    $article = Article::factory()->by($this->editor)->create();

    expect($category->is_slug_locked)->toBeFalse();

    $this->actingAs($this->editor)
        ->post(route('articles.update', $article), articlePayload([
            'title' => $article->title,
            'status' => 'published',
            'category_ids' => [$category->id],
            'tag_ids' => [$tag->id],
        ]))
        ->assertSessionHasNoErrors();

    expect($article->fresh()->is_slug_locked)->toBeTrue()
        ->and($category->fresh()->is_slug_locked)->toBeTrue()
        ->and($tag->fresh()->is_slug_locked)->toBeTrue();
});

it('conserva el slug al cambiar el título de un artículo publicado', function (): void {
    $article = Article::factory()->published()->by($this->editor)->create(['title' => 'Título original']);
    $slug = $article->slug;

    $this->actingAs($this->editor)
        ->post(route('articles.update', $article), articlePayload([
            'title' => 'Título corregido',
            'status' => 'published',
        ]))
        ->assertSessionHasNoErrors();

    expect($article->fresh()->slug)->toBe($slug)
        ->and($article->fresh()->title)->toBe('Título corregido');
});

it('exige la fecha al programar y no la admite en el pasado', function (): void {
    $article = Article::factory()->by($this->editor)->create();

    $this->actingAs($this->editor)
        ->from(route('articles.edit', $article))
        ->post(route('articles.update', $article), articlePayload([
            'title' => $article->title,
            'status' => 'scheduled',
        ]))
        ->assertSessionHasErrors([
            'scheduled_for' => 'Indica la fecha y la hora en las que debe publicarse.',
        ]);

    $this->actingAs($this->editor)
        ->from(route('articles.edit', $article))
        ->post(route('articles.update', $article), articlePayload([
            'title' => $article->title,
            'status' => 'scheduled',
            'scheduled_for' => now()->subDay()->format('Y-m-d\TH:i'),
        ]))
        ->assertSessionHasErrors([
            'scheduled_for' => 'La fecha de publicación programada debe ser futura.',
        ]);
});

it('programa un artículo con una fecha futura', function (): void {
    $article = Article::factory()->by($this->editor)->create();
    $when = now()->addDays(2);

    $this->actingAs($this->editor)
        ->post(route('articles.update', $article), articlePayload([
            'title' => $article->title,
            'status' => 'scheduled',
            'scheduled_for' => $when->format('Y-m-d\TH:i'),
        ]))
        ->assertSessionHasNoErrors();

    $article->refresh();

    expect($article->status)->toBe(ArticleStatus::Scheduled)
        ->and($article->scheduled_for?->format('Y-m-d H:i'))->toBe($when->format('Y-m-d H:i'));
});

it('envía un artículo a la papelera y lo restaura', function (): void {
    $article = Article::factory()->by($this->editor)->create();

    $this->actingAs($this->editor)
        ->delete(route('articles.destroy', $article))
        ->assertRedirect(route('articles.index'));

    expect($article->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->editor)
        ->post(route('articles.restore', $article))
        ->assertRedirect(route('articles.index'));

    expect($article->fresh()->trashed())->toBeFalse();
});

it('filtra por estado y por categoría', function (): void {
    $category = Category::factory()->create();

    $published = Article::factory()->published()->create();
    $published->categories()->attach($category);

    Article::factory()->create();

    $this->actingAs($this->editor)
        ->get(route('articles.index', ['status' => 'published']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('articles.data', 1));

    $this->actingAs($this->editor)
        ->get(route('articles.index', ['extra' => (string) $category->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('articles.data', 1));
});

it('busca por título y por contenido', function (): void {
    Article::factory()->create(['title' => 'Persianas enrollables', 'content_text' => 'texto uno']);
    Article::factory()->create(['title' => 'Puertas de interior', 'content_text' => 'aluminio lacado']);

    $this->actingAs($this->editor)
        ->get(route('articles.index', ['search' => 'persianas']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('articles.data', 1));

    $this->actingAs($this->editor)
        ->get(route('articles.index', ['search' => 'aluminio']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('articles.data', 1)
            ->where('articles.data.0.title', 'Puertas de interior'));
});

it('ignora una columna de ordenación no permitida', function (): void {
    $this->actingAs($this->editor)
        ->get(route('articles.index', ['sort' => 'content']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('filters.sort', 'updated_at'));
});

it('declara sus tipos de contenido con su alias', function (): void {
    $registry = app(ModuleRegistry::class);

    expect($registry->morphMap())->toHaveKey('blog.article', Article::class)
        ->and($registry->contentType(Article::class)?->alias)->toBe('blog.article')
        ->and($registry->contentType('blog.article')?->urlFor(7))->toBe(route('articles.edit', 7));
});
