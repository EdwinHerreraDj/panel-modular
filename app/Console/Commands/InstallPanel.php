<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Throwable;

use function Laravel\Prompts\confirm;

/**
 * Instalación del panel en un alojamiento nuevo.
 *
 * Reúne en un comando los pasos que hay que dar en cada cliente. No sustituye a
 * la guía (docs/DESPLIEGUE.md), que explica lo que hay que hacer *fuera* del
 * proyecto: subdominio, base de datos y certificado.
 */
class InstallPanel extends Command
{
    protected $signature = 'panel:instalar
                            {--sin-superadmin : No pide crear la cuenta de superadministrador}
                            {--sin-cache : No deja la configuración cacheada}';

    protected $description = 'Prepara la instalación en un alojamiento nuevo';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <options=bold>Instalación del panel</>');
        $this->newLine();

        if (! $this->ensureEnvFile()) {
            return self::FAILURE;
        }

        $this->ensureAppKey();

        if (! $this->migrate()) {
            return self::FAILURE;
        }

        $this->seedRoles();
        $this->linkStorage();

        if (! $this->option('sin-superadmin')) {
            $this->createSuperAdmin();
        }

        if (! $this->option('sin-cache')) {
            $this->cacheConfiguration();
        }

        $this->newLine();
        $this->line('  <options=bold>Repaso final</>');
        $this->newLine();

        // La comprobación es la que manda: si algo quedó a medias, sale aquí.
        $result = Artisan::call('panel:comprobar', [], $this->getOutput());

        if ($result !== self::SUCCESS) {
            $this->line('  <fg=yellow>Resuelve lo anterior y vuelve a ejecutar `php artisan panel:comprobar`.</>');
            $this->newLine();
        }

        return $result;
    }

    private function ensureEnvFile(): bool
    {
        if (File::exists(base_path('.env'))) {
            $this->components->info('El archivo .env ya existe.');

            return true;
        }

        if (! File::exists(base_path('.env.example'))) {
            $this->components->error('No hay .env ni .env.example. Sube el proyecto completo.');

            return false;
        }

        File::copy(base_path('.env.example'), base_path('.env'));
        $this->components->info('Creado .env a partir de .env.example.');
        $this->components->warn('Rellena los datos de la base de datos y APP_URL, y vuelve a ejecutar este comando.');

        return false;
    }

    private function ensureAppKey(): void
    {
        if (config('app.key') !== null && config('app.key') !== '') {
            $this->components->info('APP_KEY ya estaba definida.');

            return;
        }

        Artisan::call('key:generate', ['--force' => true]);
        $this->components->info('Generada APP_KEY.');
    }

    private function migrate(): bool
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->components->info('Base de datos al día.');

            return true;
        } catch (Throwable $exception) {
            $this->components->error('No se han podido aplicar las migraciones: '.$exception->getMessage());
            $this->line('  <fg=gray>→ Revisa DB_DATABASE, DB_USERNAME y DB_PASSWORD en el .env.</>');

            return false;
        }
    }

    private function seedRoles(): void
    {
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
            '--force' => true,
        ]);

        $this->components->info('Roles y permisos creados.');
    }

    /**
     * Enlace de `public/storage`, sin el que no se ve ninguna imagen.
     *
     * En algunos alojamientos compartidos los enlaces simbólicos están
     * capados. Cuando falla se dice qué hacer en lugar de dejar el error de
     * PHP a la vista.
     */
    private function linkStorage(): void
    {
        if (file_exists(public_path('storage'))) {
            $this->components->info('El enlace public/storage ya existía.');

            return;
        }

        try {
            Artisan::call('storage:link');
            $this->components->info('Creado el enlace public/storage.');
        } catch (Throwable) {
            $this->components->warn('No se ha podido crear el enlace simbólico.');
            $this->line('  <fg=gray>→ Este alojamiento no admite enlaces. Mira la sección');
            $this->line('    «Si no se pueden crear enlaces» de docs/DESPLIEGUE.md.</>');
        }
    }

    private function createSuperAdmin(): void
    {
        if (! $this->input->isInteractive()) {
            $this->components->warn('Sin terminal interactiva: crea la cuenta con `php artisan panel:crear-superadmin`.');

            return;
        }

        if (! confirm('¿Creamos ahora la cuenta de superadministrador?', default: true)) {
            return;
        }

        Artisan::call('panel:crear-superadmin', [], $this->getOutput());
    }

    private function cacheConfiguration(): void
    {
        foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
            Artisan::call($command);
        }

        $this->components->info('Configuración, rutas y vistas cacheadas.');
        $this->line('  <fg=gray>→ Repite estos tres comandos cada vez que cambies el .env.</>');
    }
}
