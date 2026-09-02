<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->superAdmin = User::factory()->superAdmin()->create();

    Storage::fake('public');
    Setting::current();
});

/**
 * Crea un archivo con contenido arbitrario y la extensión indicada.
 * Sirve para comprobar que no basta con disfrazar la extensión.
 */
function fakeFileWithExtension(string $name, string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $contents);

    return new UploadedFile($path, $name, 'image/png', null, true);
}

it('sube el logotipo y guarda su ruta', function (): void {
    $this->actingAs($this->superAdmin)
        ->post(route('settings.update'), settingsPayload([
            'logo' => UploadedFile::fake()->image('mi-logo.png', 400, 120),
        ]))
        ->assertSessionHasNoErrors();

    $setting = Setting::query()->firstOrFail();

    expect($setting->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($setting->logo_path);
});

it('genera un nombre propio y descarta el del navegador', function (): void {
    $this->actingAs($this->superAdmin)
        ->post(route('settings.update'), settingsPayload([
            'logo' => UploadedFile::fake()->image('../../etc/passwd.png', 400, 120),
        ]))
        ->assertSessionHasNoErrors();

    $path = Setting::query()->firstOrFail()->logo_path;

    expect($path)->toStartWith('settings/')
        ->and($path)->not->toContain('passwd')
        ->and($path)->not->toContain('..')
        ->and($path)->toEndWith('.png');
});

it('rechaza un archivo que solo aparenta ser una imagen', function (): void {
    // Extensión y content-type de PNG, pero el contenido es un script PHP.
    $file = fakeFileWithExtension('inocente.png', '<?php echo "hola"; ?>');

    $this->actingAs($this->superAdmin)
        ->from(route('settings.edit'))
        ->post(route('settings.update'), settingsPayload(['logo' => $file]))
        ->assertSessionHasErrors('logo');

    expect(Setting::query()->firstOrFail()->logo_path)->toBeNull();
});

it('rechaza un formato de imagen no admitido', function (): void {
    $this->actingAs($this->superAdmin)
        ->from(route('settings.edit'))
        ->post(route('settings.update'), settingsPayload([
            'logo' => UploadedFile::fake()->create('animacion.gif', 20, 'image/gif'),
        ]))
        ->assertSessionHasErrors('logo');
});

it('no admite SVG ni siquiera con el content-type correcto', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $svg);

    $file = new UploadedFile($path, 'logo.svg', 'image/svg+xml', null, true);

    $this->actingAs($this->superAdmin)
        ->from(route('settings.edit'))
        ->post(route('settings.update'), settingsPayload(['logo' => $file]))
        ->assertSessionHasErrors('logo');
});

it('rechaza una imagen demasiado pequeña', function (): void {
    $this->actingAs($this->superAdmin)
        ->from(route('settings.edit'))
        ->post(route('settings.update'), settingsPayload([
            'logo' => UploadedFile::fake()->image('diminuto.png', 8, 8),
        ]))
        ->assertSessionHasErrors('logo');
});

it('rechaza una imagen demasiado grande en dimensiones', function (): void {
    $this->actingAs($this->superAdmin)
        ->from(route('settings.edit'))
        ->post(route('settings.update'), settingsPayload([
            'logo' => UploadedFile::fake()->image('enorme.png', 3000, 1000),
        ]))
        ->assertSessionHasErrors('logo');
});

it('rechaza un archivo que supera el peso permitido', function (): void {
    $file = UploadedFile::fake()->image('pesado.png', 400, 120)->size(2048);

    $this->actingAs($this->superAdmin)
        ->from(route('settings.edit'))
        ->post(route('settings.update'), settingsPayload(['logo' => $file]))
        ->assertSessionHasErrors('logo');
});

it('exige que la imagen de Open Graph tenga el tamaño mínimo recomendado', function (): void {
    $this->actingAs($this->superAdmin)
        ->from(route('settings.edit'))
        ->post(route('settings.update'), settingsPayload([
            'og_image' => UploadedFile::fake()->image('og.png', 400, 200),
        ]))
        ->assertSessionHasErrors('og_image');

    $this->actingAs($this->superAdmin)
        ->post(route('settings.update'), settingsPayload([
            'og_image' => UploadedFile::fake()->image('og.png', 1200, 630),
        ]))
        ->assertSessionHasNoErrors();

    expect(Setting::query()->firstOrFail()->og_image_path)->not->toBeNull();
});

it('borra el archivo anterior al sustituir una imagen', function (): void {
    $this->actingAs($this->superAdmin)->post(route('settings.update'), settingsPayload([
        'logo' => UploadedFile::fake()->image('primero.png', 400, 120),
    ]));

    $first = Setting::query()->firstOrFail()->logo_path;

    $this->actingAs($this->superAdmin)->post(route('settings.update'), settingsPayload([
        'logo' => UploadedFile::fake()->image('segundo.png', 400, 120),
    ]));

    $second = Setting::query()->firstOrFail()->logo_path;

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

it('elimina la imagen cuando se marca para borrar', function (): void {
    $this->actingAs($this->superAdmin)->post(route('settings.update'), settingsPayload([
        'logo' => UploadedFile::fake()->image('logo.png', 400, 120),
    ]));

    $path = Setting::query()->firstOrFail()->logo_path;

    $this->actingAs($this->superAdmin)
        ->post(route('settings.update'), settingsPayload(['remove_logo' => true]))
        ->assertSessionHasNoErrors();

    expect(Setting::query()->firstOrFail()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('conserva la imagen si no se envía ni archivo ni marca de borrado', function (): void {
    $this->actingAs($this->superAdmin)->post(route('settings.update'), settingsPayload([
        'logo' => UploadedFile::fake()->image('logo.png', 400, 120),
    ]));

    $path = Setting::query()->firstOrFail()->logo_path;

    $this->actingAs($this->superAdmin)
        ->post(route('settings.update'), settingsPayload(['site_name' => 'Otro nombre']))
        ->assertSessionHasNoErrors();

    expect(Setting::query()->firstOrFail()->logo_path)->toBe($path);
    Storage::disk('public')->assertExists($path);
});

it('el favicon solo admite PNG', function (): void {
    $this->actingAs($this->superAdmin)
        ->from(route('settings.edit'))
        ->post(route('settings.update'), settingsPayload([
            'favicon' => UploadedFile::fake()->image('icono.jpg', 64, 64),
        ]))
        ->assertSessionHasErrors('favicon');

    $this->actingAs($this->superAdmin)
        ->post(route('settings.update'), settingsPayload([
            'favicon' => UploadedFile::fake()->image('icono.png', 64, 64),
        ]))
        ->assertSessionHasNoErrors();
});
