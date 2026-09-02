<?php

declare(strict_types=1);

use App\Actions\Media\StoreMedia;
use App\Enums\MediaProcessingStatus;
use App\Jobs\GenerateMediaVariants;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Generación de variantes. Se ejecuta el trabajo directamente para comprobar
 * el resultado en disco, sin depender de que haya un worker.
 */
beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->editor = User::factory()->editor()->create();
    Storage::fake('public');
});

/**
 * Crea una imagen real del tamaño indicado y la sube a la biblioteca.
 */
function uploadRealImage(User $uploader, int $width, int $height, string $format = 'png'): Media
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 40, 100, 200));

    $path = tempnam(sys_get_temp_dir(), 'media').'.'.$format;

    if ($format === 'png') {
        imagepng($image, $path);
    } else {
        imagejpeg($image, $path);
    }

    imagedestroy($image);

    $file = new UploadedFile(
        $path,
        "prueba.{$format}",
        $format === 'png' ? 'image/png' : 'image/jpeg',
        null,
        true,
    );

    return app(StoreMedia::class)->handle($file, $uploader);
}

it('genera las variantes y las marca como listas', function (): void {
    $media = uploadRealImage($this->editor, 2000, 1200);

    (new GenerateMediaVariants($media->id))->handle();

    $media->refresh();

    expect($media->processing_status)->toBe(MediaProcessingStatus::Done)
        ->and($media->width)->toBe(2000)
        ->and($media->height)->toBe(1200);

    $variants = $media->variants ?? [];

    expect($variants)->toHaveKeys(['thumbnail', 'medium', 'large'])
        ->and($variants)->toHaveKeys(['thumbnail.webp', 'medium.webp', 'large.webp', 'original.webp']);

    foreach ($variants as $path) {
        Storage::disk('public')->assertExists($path);
    }
});

it('no genera variantes mayores que el original', function (): void {
    // 500 px de ancho: solo cabe la miniatura de 300.
    $media = uploadRealImage($this->editor, 500, 400);

    (new GenerateMediaVariants($media->id))->handle();

    $variants = $media->fresh()->variants ?? [];

    expect($variants)->toHaveKey('thumbnail')
        ->and($variants)->not->toHaveKey('medium')
        ->and($variants)->not->toHaveKey('large');
});

it('devuelve la URL de la variante pedida y recurre al original si no existe', function (): void {
    $media = uploadRealImage($this->editor, 2000, 1200);

    (new GenerateMediaVariants($media->id))->handle();
    $media->refresh();

    expect($media->variantUrl('thumbnail'))->toContain('thumbnail')
        ->and($media->variantUrl('thumbnail', preferWebp: true))->toContain('.webp');

    // Una imagen pequeña no tiene variante grande: se sirve el original.
    $small = uploadRealImage($this->editor, 200, 200);
    (new GenerateMediaVariants($small->id))->handle();

    expect($small->fresh()->variantUrl('large'))->toContain($small->path);
});

it('marca el archivo como fallido si el original ya no está', function (): void {
    $media = Media::factory()->pending()->create();

    // No se ha escrito nada en el disco falso.
    (new GenerateMediaVariants($media->id))->handle();

    $media->refresh();

    expect($media->processing_status)->toBe(MediaProcessingStatus::Failed)
        ->and($media->processing_error)->toContain('ya no está en el disco');
});

it('no falla si el archivo se ha eliminado antes de procesarlo', function (): void {
    $media = Media::factory()->pending()->create();
    $id = $media->id;
    $media->forceDelete();

    // No debe lanzar ninguna excepción.
    (new GenerateMediaVariants($id))->handle();

    expect(true)->toBeTrue();
});

it('la variante WebP pesa menos que el original en JPEG', function (): void {
    $media = uploadRealImage($this->editor, 1600, 900, 'jpg');

    (new GenerateMediaVariants($media->id))->handle();
    $media->refresh();

    $webpPath = ($media->variants ?? [])['original.webp'] ?? null;

    expect($webpPath)->not->toBeNull();

    $originalSize = Storage::disk('public')->size($media->path);
    $webpSize = Storage::disk('public')->size((string) $webpPath);

    expect($webpSize)->toBeLessThan($originalSize);
});
