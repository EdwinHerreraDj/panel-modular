<?php

declare(strict_types=1);

namespace App\Support\Installation;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use App\Modules\ModuleRegistry;
use App\Support\ApiDocumentation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Comprueba que la instalación está lista para atender público.
 *
 * Este panel se instala una vez por cliente, así que la pregunta «¿me he dejado
 * algo?» se hace muchas veces. Aquí está la respuesta en un comando, con las
 * mismas comprobaciones siempre y con la solución escrita al lado de cada
 * problema.
 */
class EnvironmentReport
{
    /** @var list<string> Extensiones de PHP que la aplicación necesita. */
    private const EXTENSIONS = ['pdo_mysql', 'gd', 'exif', 'fileinfo', 'mbstring', 'intl', 'json'];

    /**
     * @return list<Check>
     */
    public function run(): array
    {
        return array_merge(
            $this->php(),
            $this->application(),
            $this->database(),
            $this->storage(),
            $this->mail(),
            $this->security(),
            $this->morphAliases(),
            $this->content(),
        );
    }

    /**
     * @return list<Check>
     */
    private function php(): array
    {
        $checks = [];

        // Se usa `phpversion()` y no la constante PHP_VERSION porque el
        // análisis estático la resuelve con la versión de esta máquina y da la
        // comprobación por superada. Aquí interesa la del servidor de destino.
        $version = (string) phpversion();

        $checks[] = version_compare($version, '8.3', '>=')
            ? Check::ok('PHP', 'Versión '.$version)
            : Check::error(
                'PHP',
                'Versión '.$version.', y hace falta 8.3 o superior',
                'Cambia la versión de PHP en el panel del alojamiento.',
            );

        $missing = array_values(array_filter(
            self::EXTENSIONS,
            fn (string $extension): bool => ! extension_loaded($extension),
        ));

        $checks[] = $missing === []
            ? Check::ok('Extensiones', 'Todas las necesarias están activas')
            : Check::error(
                'Extensiones',
                'Faltan: '.implode(', ', $missing),
                'Actívalas en el panel del alojamiento. Sin `gd` no se pueden procesar imágenes.',
            );

        // El límite de memoria condiciona el tamaño de imagen admisible.
        $limit = $this->memoryLimitInBytes();

        $checks[] = match (true) {
            $limit === null => Check::ok('Memoria', 'Sin límite'),
            $limit >= 128 * 1024 * 1024 => Check::ok('Memoria', ini_get('memory_limit').' disponibles'),
            $limit >= 64 * 1024 * 1024 => Check::warning(
                'Memoria',
                ini_get('memory_limit').' puede quedarse corto con imágenes grandes',
                'Sube `memory_limit` a 128M, o baja el máximo con MEDIA_MAX_PROCESSING_MEMORY.',
            ),
            default => Check::error(
                'Memoria',
                ini_get('memory_limit').' es insuficiente para procesar imágenes',
                'Sube `memory_limit` a 128M en el panel del alojamiento.',
            ),
        };

        return $checks;
    }

    /**
     * @return list<Check>
     */
    private function application(): array
    {
        $checks = [];

        $checks[] = config('app.key') === null || config('app.key') === ''
            ? Check::error('Clave', 'APP_KEY está vacía', 'Ejecuta `php artisan key:generate`.')
            : Check::ok('Clave', 'APP_KEY definida');

        $checks[] = config('app.env') === 'production'
            ? Check::ok('Entorno', 'production')
            : Check::error(
                'Entorno',
                'APP_ENV es «'.config('app.env').'»',
                'Ponlo en `production` antes de abrir al público.',
            );

        $checks[] = config('app.debug') === false
            ? Check::ok('Depuración', 'Desactivada')
            : Check::error(
                'Depuración',
                'APP_DEBUG está activo y enseñaría la configuración en cualquier error',
                'Pon `APP_DEBUG=false`.',
            );

        $url = (string) config('app.url');

        if (ApiDocumentation::addressIsLocal()) {
            $checks[] = Check::error(
                'Dirección',
                'APP_URL apunta a '.$url,
                'Ponlo con el subdominio real, por ejemplo https://blog.tucliente.com.',
            );
        } elseif (! Str::startsWith($url, 'https://')) {
            $checks[] = Check::warning(
                'Dirección',
                'APP_URL no usa HTTPS',
                'Contrata o activa el certificado y cambia la dirección a https://.',
            );
        } else {
            $checks[] = Check::ok('Dirección', $url);
        }

        return $checks;
    }

    /**
     * @return list<Check>
     */
    private function database(): array
    {
        $checks = [];

        try {
            DB::connection()->getPdo();
            $checks[] = Check::ok('Base de datos', 'Conexión correcta');
        } catch (Throwable $exception) {
            return [Check::error(
                'Base de datos',
                'No se puede conectar: '.Str::limit($exception->getMessage(), 80),
                'Revisa DB_DATABASE, DB_USERNAME y DB_PASSWORD en el .env.',
            )];
        }

        // Los dos relojes tienen que coincidir. Si no, todo lo que dependa de
        // una fecha (publicar, programar, podar) se desvía sin avisar.
        try {
            /** @var object{n: string} $row */
            $row = DB::selectOne('SELECT NOW() AS n');
            $difference = abs(now()->diffInMinutes($row->n));

            $checks[] = $difference <= 2
                ? Check::ok('Hora', 'La base de datos y la aplicación coinciden')
                : Check::error(
                    'Hora',
                    'La base de datos va '.round($difference).' minutos desviada',
                    'Ajusta DB_TIMEZONE para que coincida con APP_TIMEZONE. Hazlo antes de guardar datos.',
                );
        } catch (Throwable) {
            $checks[] = Check::warning('Hora', 'No se ha podido comprobar la hora de la base de datos');
        }

        $pending = $this->pendingMigrations();

        $checks[] = $pending === 0
            ? Check::ok('Migraciones', 'Todas aplicadas')
            : Check::error(
                'Migraciones',
                $pending.' sin aplicar',
                'Ejecuta `php artisan migrate --force`.',
            );

        foreach (['cache', 'sessions'] as $table) {
            if (! Schema::hasTable($table)) {
                $checks[] = Check::error(
                    'Tabla '.$table,
                    'No existe, y la configuración la necesita',
                    'Ejecuta `php artisan migrate --force`.',
                );
            }
        }

        return $checks;
    }

    /**
     * @return list<Check>
     */
    private function storage(): array
    {
        $checks = [];

        foreach (['storage' => storage_path(), 'bootstrap/cache' => base_path('bootstrap/cache')] as $name => $path) {
            $checks[] = is_writable($path)
                ? Check::ok('Permisos '.$name, 'Se puede escribir')
                : Check::error(
                    'Permisos '.$name,
                    'No se puede escribir en '.$path,
                    'Dale permisos de escritura a la carpeta (755, y que el dueño sea el usuario de PHP).',
                );
        }

        // Sin este enlace las imágenes de la biblioteca no se ven en la web.
        $link = public_path('storage');

        $checks[] = file_exists($link)
            ? Check::ok('Enlace de archivos', 'public/storage existe')
            : Check::error(
                'Enlace de archivos',
                'Falta public/storage y las imágenes no se verán',
                'Ejecuta `php artisan storage:link`. Si el alojamiento no admite enlaces simbólicos, mira docs/DESPLIEGUE.md.',
            );

        try {
            $disk = Storage::disk((string) config('media.disk'));
            $probe = 'comprobacion-'.Str::random(8).'.txt';
            $disk->put($probe, 'ok');
            $disk->delete($probe);

            $checks[] = Check::ok('Biblioteca', 'Se pueden guardar archivos');
        } catch (Throwable $exception) {
            $checks[] = Check::error(
                'Biblioteca',
                'No se pueden guardar archivos: '.Str::limit($exception->getMessage(), 60),
                'Revisa los permisos de storage/app/public.',
            );
        }

        return $checks;
    }

    /**
     * @return list<Check>
     */
    private function mail(): array
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['log', 'array', 'null'], true)) {
            return [Check::error(
                'Correo',
                'MAIL_MAILER es «'.$mailer.'», así que no se envía nada',
                'Configura el SMTP del alojamiento. Sin esto, quien pierda la contraseña no puede recuperarla.',
            )];
        }

        $from = (string) config('mail.from.address');

        if ($from === '' || Str::endsWith($from, ['.local', '.test', 'example.com'])) {
            return [Check::warning(
                'Correo',
                'La dirección del remitente («'.$from.'») no parece real',
                'Pon una del dominio del cliente, o los correos acabarán en spam.',
            )];
        }

        return [Check::ok('Correo', $mailer.' desde '.$from)];
    }

    /**
     * @return list<Check>
     */
    private function security(): array
    {
        $checks = [];

        $checks[] = config('api.require_key') === true
            ? Check::ok('API', 'Exige clave')
            : Check::warning(
                'API',
                'La API responde sin clave a cualquiera',
                'Pon `API_REQUIRE_KEY=true` salvo que sea a propósito.',
            );

        $https = Str::startsWith((string) config('app.url'), 'https://');

        if ($https) {
            $checks[] = config('security.hsts.enabled') === true
                ? Check::ok('HSTS', 'Activo')
                : Check::warning(
                    'HSTS',
                    'Desactivado, aunque el sitio va por HTTPS',
                    'Pon `SECURITY_HSTS_ENABLED=true` cuando el certificado esté estable.',
                );
        }

        $checks[] = config('queue.default') === 'sync'
            ? Check::ok('Colas', 'sync, sin procesos en segundo plano')
            : Check::warning(
                'Colas',
                'QUEUE_CONNECTION es «'.config('queue.default').'» y haría falta un worker',
                'En alojamiento compartido debe ser `sync`.',
            );

        return $checks;
    }

    /**
     * Las columnas polimórficas deben guardar alias, no nombres de clase.
     *
     * Una fila con el nombre de clase antiguo no da ningún error: simplemente
     * deja de encontrarse. Un usuario conserva su rol en la base de datos y se
     * comporta como si no tuviera ninguno; un archivo pierde el registro de
     * dónde se usa. Y no se nota en las pruebas, porque allí las filas se
     * escriben y se leen con el mapa ya puesto.
     *
     * Por eso se comprueba en la instalación: es barato y avisa de un desajuste
     * que de otro modo aparecería como «el panel ha perdido el menú».
     *
     * @return list<Check>
     */
    private function morphAliases(): array
    {
        $columns = [
            'media_usages' => 'usable_type',
            'activity_logs' => 'subject_type',
            'revisions' => 'revisionable_type',
            'redirects' => 'redirectable_type',
            'model_has_roles' => 'model_type',
            'model_has_permissions' => 'model_type',
        ];

        $known = array_keys(app(ModuleRegistry::class)->morphMap());
        $stale = [];

        foreach ($columns as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            try {
                $found = DB::table($table)
                    ->whereNotNull($column)
                    ->whereNotIn($column, $known)
                    ->distinct()
                    ->pluck($column)
                    ->all();
            } catch (Throwable) {
                continue;
            }

            foreach ($found as $value) {
                $stale[] = $table.'.'.$column.' = '.$value;
            }
        }

        if ($stale === []) {
            return [Check::ok('Tipos polimórficos', 'Todos usan el alias de su módulo')];
        }

        return [Check::error(
            'Tipos polimórficos',
            count($stale).' valor(es) sin convertir: '.implode(', ', array_slice($stale, 0, 3)),
            'Falta una migración que traduzca esos valores al alias del módulo, o el módulo que los '
            .'declaraba no está en MODULES_ENABLED. Mira database/migrations/*use_morph_aliases*.',
        )];
    }

    /**
     * @return list<Check>
     */
    private function content(): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        $superAdmins = User::query()
            ->where('status', UserStatus::Active->value)
            ->whereHas('roles', fn ($query) => $query->where('name', RoleName::SuperAdmin->value))
            ->count();

        $checks = [
            $superAdmins > 0
                ? Check::ok('Superadministrador', $superAdmins.' cuenta(s) activa(s)')
                : Check::error(
                    'Superadministrador',
                    'No hay ninguna cuenta activa y nadie podría entrar',
                    'Ejecuta `php artisan panel:crear-superadmin`.',
                ),
        ];

        $mustChange = User::query()->where('must_change_password', true)->count();

        if ($mustChange > 0) {
            $checks[] = Check::warning(
                'Contraseñas',
                $mustChange.' cuenta(s) con contraseña provisional sin cambiar',
                'Recuérdales que la cambien al entrar: la conoce quien se la puso.',
            );
        }

        return $checks;
    }

    private function pendingMigrations(): int
    {
        if (! Schema::hasTable('migrations')) {
            return count(glob(database_path('migrations/*.php')) ?: []);
        }

        $applied = DB::table('migrations')->count();
        $files = count(glob(database_path('migrations/*.php')) ?: []);

        return max(0, $files - $applied);
    }

    private function memoryLimitInBytes(): ?int
    {
        $limit = trim((string) ini_get('memory_limit'));

        if ($limit === '' || $limit === '-1') {
            return null;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
