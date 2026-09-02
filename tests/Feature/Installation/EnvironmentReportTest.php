<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Installation\Check;
use App\Support\Installation\EnvironmentReport;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Comprobación por su nombre.
 *
 * @param  list<Check>  $checks
 */
function check(array $checks, string $name): Check
{
    foreach ($checks as $check) {
        if ($check->name === $name) {
            return $check;
        }
    }

    throw new RuntimeException("No existe la comprobación «{$name}».");
}

/**
 * Deja el entorno como si fuera una instalación bien hecha.
 */
function productionEnvironment(): void
{
    config()->set('app.env', 'production');
    config()->set('app.debug', false);
    config()->set('app.url', 'https://blog.tucliente.com');
    config()->set('mail.default', 'smtp');
    config()->set('mail.from.address', 'blog@tucliente.com');
    config()->set('api.require_key', true);
    config()->set('queue.default', 'sync');
    config()->set('security.hsts.enabled', true);
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    User::factory()->superAdmin()->create();
});

/*
|--------------------------------------------------------------------------
| Lo que impide abrir al público
|--------------------------------------------------------------------------
*/

it('da por buena una instalación bien configurada', function (): void {
    productionEnvironment();

    $checks = app(EnvironmentReport::class)->run();
    $errors = array_filter($checks, fn (Check $check): bool => $check->isError());

    expect($errors)->toBe([]);
});

it('avisa de que la depuración está activa', function (): void {
    productionEnvironment();
    config()->set('app.debug', true);

    expect(check(app(EnvironmentReport::class)->run(), 'Depuración')->isError())->toBeTrue();
});

it('avisa de que el entorno no es producción', function (): void {
    productionEnvironment();
    config()->set('app.env', 'local');

    expect(check(app(EnvironmentReport::class)->run(), 'Entorno')->isError())->toBeTrue();
});

it('avisa de que la dirección todavía es local', function (string $url): void {
    productionEnvironment();
    config()->set('app.url', $url);

    $check = check(app(EnvironmentReport::class)->run(), 'Dirección');

    expect($check->isError())->toBeTrue()
        ->and($check->fix)->toContain('subdominio real');
})->with([
    'localhost' => 'http://localhost:8000',
    'dominio .test' => 'http://blog.test',
]);

it('avisa si la dirección no usa HTTPS', function (): void {
    productionEnvironment();
    config()->set('app.url', 'http://blog.tucliente.com');

    expect(check(app(EnvironmentReport::class)->run(), 'Dirección')->isWarning())->toBeTrue();
});

it('avisa de que el correo no se envía', function (string $mailer): void {
    productionEnvironment();
    config()->set('mail.default', $mailer);

    $check = check(app(EnvironmentReport::class)->run(), 'Correo');

    expect($check->isError())->toBeTrue()
        ->and($check->fix)->toContain('recuperarla');
})->with(['log', 'array', 'null']);

it('avisa de un remitente que no parece real', function (): void {
    productionEnvironment();
    config()->set('mail.from.address', 'no-reply@panel-blogs.local');

    expect(check(app(EnvironmentReport::class)->run(), 'Correo')->isWarning())->toBeTrue();
});

it('avisa de que no hay ningún superadministrador activo', function (): void {
    productionEnvironment();

    User::query()->update(['status' => UserStatus::Suspended->value]);

    $check = check(app(EnvironmentReport::class)->run(), 'Superadministrador');

    expect($check->isError())->toBeTrue()
        ->and($check->fix)->toContain('panel:crear-superadmin');
});

/*
|--------------------------------------------------------------------------
| Avisos
|--------------------------------------------------------------------------
*/

it('avisa de que la API está abierta sin clave', function (): void {
    productionEnvironment();
    config()->set('api.require_key', false);

    expect(check(app(EnvironmentReport::class)->run(), 'API')->isWarning())->toBeTrue();
});

it('avisa de que las colas necesitarían un worker', function (): void {
    productionEnvironment();
    config()->set('queue.default', 'database');

    $check = check(app(EnvironmentReport::class)->run(), 'Colas');

    expect($check->isWarning())->toBeTrue()
        ->and($check->fix)->toContain('sync');
});

it('avisa de contraseñas provisionales sin cambiar', function (): void {
    productionEnvironment();

    User::factory()->editor()->create(['must_change_password' => true]);

    expect(check(app(EnvironmentReport::class)->run(), 'Contraseñas')->isWarning())->toBeTrue();
});

it('no avisa de HSTS si el sitio no va por HTTPS', function (): void {
    productionEnvironment();
    config()->set('app.url', 'http://blog.tucliente.com');
    config()->set('security.hsts.enabled', false);

    $names = array_map(fn (Check $check): string => $check->name, app(EnvironmentReport::class)->run());

    expect($names)->not->toContain('HSTS');
});

/*
|--------------------------------------------------------------------------
| Comprobaciones que siempre se hacen
|--------------------------------------------------------------------------
*/

it('comprueba la base de datos, la hora y las migraciones', function (): void {
    productionEnvironment();

    $checks = app(EnvironmentReport::class)->run();

    expect(check($checks, 'Base de datos')->isError())->toBeFalse()
        ->and(check($checks, 'Hora')->isError())->toBeFalse()
        ->and(check($checks, 'Migraciones')->isError())->toBeFalse();
});

it('comprueba que se puede escribir donde hace falta', function (): void {
    productionEnvironment();

    $checks = app(EnvironmentReport::class)->run();

    expect(check($checks, 'Permisos storage')->isError())->toBeFalse()
        ->and(check($checks, 'Biblioteca')->isError())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| El comando
|--------------------------------------------------------------------------
*/

it('el comando falla mientras quede algo por resolver', function (): void {
    config()->set('app.debug', true);

    $this->artisan('panel:comprobar')->assertFailed();
});

it('el comando pasa cuando todo está en orden', function (): void {
    productionEnvironment();

    $this->artisan('panel:comprobar')->assertSuccessful();
});

it('el comando explica cómo resolver cada problema', function (): void {
    config()->set('mail.default', 'log');

    $this->artisan('panel:comprobar')
        ->expectsOutputToContain('Configura el SMTP')
        ->assertFailed();
});

/*
|--------------------------------------------------------------------------
| Tipos polimórficos
|--------------------------------------------------------------------------
*/

it('da por buenos los tipos polimórficos cuando todos son alias', function (): void {
    productionEnvironment();

    expect(check(app(EnvironmentReport::class)->run(), 'Tipos polimórficos')->isError())->toBeFalse();
});

it('avisa de una columna polimórfica que guarda el nombre de una clase', function (): void {
    productionEnvironment();

    // Así quedaban las filas antes de pasar a alias. Una de estas no da ningún
    // error: simplemente deja de encontrarse, y el rol de una cuenta se
    // evapora en silencio.
    DB::table('activity_logs')->insert([
        'user_id' => null,
        'subject_type' => 'App\Models\Article',
        'subject_id' => 1,
        'event' => 'updated',
        'description' => 'Con el tipo antiguo',
        'ip_address' => '10.0.0.1',
        'created_at' => now(),
    ]);

    $check = check(app(EnvironmentReport::class)->run(), 'Tipos polimórficos');

    expect($check->isError())->toBeTrue()
        ->and($check->message)->toContain('App\Models\Article');
});

it('avisa también de las tablas de roles y permisos', function (): void {
    productionEnvironment();

    DB::table('model_has_roles')
        ->where('model_type', (new User)->getMorphClass())
        ->update(['model_type' => 'App\Models\User']);

    expect(check(app(EnvironmentReport::class)->run(), 'Tipos polimórficos')->isError())->toBeTrue();
});
