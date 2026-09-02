<?php

declare(strict_types=1);

use App\Actions\Media\SyncMediaUsage;
use App\Enums\ActivityEvent;
use App\Enums\MediaProcessingStatus;
use App\Jobs\GenerateMediaVariants;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Modules\Blog\Models\Article;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->editor()->create();
    Storage::fake('public');
});

it('muestra la biblioteca a quien puede gestionar medios', function (): void {
    Media::factory()->count(3)->create();

    $this->actingAs($this->editor)
        ->get(route('media.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Media/Index')
            ->has('media.data', 3)
            ->has('constraints.hint'));
});

it('sube una imagen y deja el procesado en cola', function (): void {
    Queue::fake();

    $this->actingAs($this->editor)
        ->post(route('media.store'), [
            'files' => [UploadedFile::fake()->image('mi-foto.png', 1600, 900)],
        ])
        ->assertSessionHasNoErrors();

    $media = Media::query()->firstOrFail();

    expect($media->name)->toBe('Mi-foto')
        ->and($media->mime_type)->toBe('image/png')
        ->and($media->processing_status)->toBe(MediaProcessingStatus::Pending)
        ->and($media->uploaded_by)->toBe($this->editor->id);

    Storage::disk('public')->assertExists($media->path);
    Queue::assertPushed(GenerateMediaVariants::class);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => (new Media)->getMorphClass(),
        'subject_id' => $media->id,
        'event' => ActivityEvent::Created->value,
    ]);
});

it('genera un nombre de archivo propio y descarta el del navegador', function (): void {
    Queue::fake();

    $this->actingAs($this->editor)->post(route('media.store'), [
        'files' => [UploadedFile::fake()->image('../../etc/passwd.png', 800, 600)],
    ]);

    $path = Media::query()->firstOrFail()->path;

    expect($path)->toStartWith('biblioteca/')
        ->and($path)->not->toContain('passwd')
        ->and($path)->not->toContain('..');
});

it('admite varias imágenes en una sola subida', function (): void {
    Queue::fake();

    $this->actingAs($this->editor)
        ->post(route('media.store'), [
            'files' => [
                UploadedFile::fake()->image('una.png', 800, 600),
                UploadedFile::fake()->image('otra.jpg', 800, 600),
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(Media::query()->count())->toBe(2);
});

it('rechaza un archivo que solo aparenta ser una imagen', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, '<?php echo "no soy una imagen"; ?>');

    $this->actingAs($this->editor)
        ->from(route('media.index'))
        ->post(route('media.store'), [
            'files' => [new UploadedFile($path, 'falsa.png', 'image/png', null, true)],
        ])
        ->assertSessionHasErrors('files.0');

    expect(Media::query()->count())->toBe(0);
});

it('rechaza formatos no admitidos', function (): void {
    $this->actingAs($this->editor)
        ->from(route('media.index'))
        ->post(route('media.store'), [
            'files' => [UploadedFile::fake()->create('animacion.gif', 20, 'image/gif')],
        ])
        ->assertSessionHasErrors('files.0');
});

it('limita el número de archivos por subida', function (): void {
    $files = [];

    foreach (range(1, 11) as $index) {
        $files[] = UploadedFile::fake()->image("imagen-{$index}.png", 400, 400);
    }

    $this->actingAs($this->editor)
        ->from(route('media.index'))
        ->post(route('media.store'), ['files' => $files])
        ->assertSessionHasErrors('files');
});

it('permite editar el nombre y el texto alternativo', function (): void {
    $media = Media::factory()->create();

    $this->actingAs($this->editor)
        ->from(route('media.index'))
        ->put(route('media.update', $media), [
            'name' => 'Portada de persianas',
            'alt_text' => 'Persianas instaladas en un salón',
            'description' => 'Foto del catálogo 2026',
        ])
        ->assertSessionHasNoErrors();

    $media->refresh();

    expect($media->name)->toBe('Portada de persianas')
        ->and($media->alt_text)->toBe('Persianas instaladas en un salón');
});

it('busca por nombre y por texto alternativo', function (): void {
    Media::factory()->create(['name' => 'Persianas enrollables', 'alt_text' => 'Salón']);
    Media::factory()->create(['name' => 'Puertas de interior', 'alt_text' => 'Pasillo']);

    $this->actingAs($this->editor)
        ->get(route('media.index', ['search' => 'persianas']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('media.data', 1));

    $this->actingAs($this->editor)
        ->get(route('media.index', ['search' => 'pasillo']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('media.data', 1)
            ->where('media.data.0.name', 'Puertas de interior'));
});

it('filtra por archivos sin usar', function (): void {
    requiresModule('blog');

    $used = Media::factory()->create(['name' => 'En uso']);
    Media::factory()->create(['name' => 'Sin usar']);

    $article = Article::factory()->create();
    $article->featured_media_id = $used->id;
    $article->save();
    app(SyncMediaUsage::class)->set($article, 'featured', $used->id);

    $this->actingAs($this->editor)
        ->get(route('media.index', ['extra' => 'unused']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('media.data', 1)
            ->where('media.data.0.name', 'Sin usar'));

    $this->actingAs($this->editor)
        ->get(route('media.index', ['extra' => 'used']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('media.data', 1)
            ->where('media.data.0.name', 'En uso'));
});

it('deniega el acceso a la biblioteca a quien no puede gestionar medios', function (): void {
    $reader = User::factory()->reader()->create();

    $this->actingAs($reader)->get(route('media.index'))->assertForbidden();
    $this->actingAs($reader)->post(route('media.store'), ['files' => []])->assertForbidden();
});

it('permite al autor subir imágenes pero no eliminarlas', function (): void {
    Queue::fake();

    $author = User::factory()->author()->create();
    $media = Media::factory()->create();

    $this->actingAs($author)
        ->post(route('media.store'), ['files' => [UploadedFile::fake()->image('foto.png', 800, 600)]])
        ->assertSessionHasNoErrors();

    $this->actingAs($author)->delete(route('media.destroy', $media))->assertForbidden();
});
