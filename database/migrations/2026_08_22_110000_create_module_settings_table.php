<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes propios de cada módulo.
 *
 * La tabla `settings` guarda lo que es del sitio: cómo se llama, su logotipo,
 * su SEO. Lo que solo entiende un módulo —cuántos artículos por página— vive
 * aquí, en filas suyas que desaparecen con él y que no ensucian el núcleo.
 *
 * Es un almacén de clave y valor y no una columna por ajuste a propósito: son
 * un puñado de filas que se leen enteras de una vez y nunca se ordenan ni se
 * filtran. Aquí no se paga nada por no tener columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_settings', function (Blueprint $table) {
            $table->id();
            $table->string('module', 40);
            $table->string('key', 60);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['module', 'key']);
        });

        // `articles_per_page` era del blog viviendo en la configuración del
        // sitio. Se lleva su valor actual para que ninguna instalación pierda
        // lo que tenía puesto.
        if (Schema::hasColumn('settings', 'articles_per_page')) {
            $current = DB::table('settings')->value('articles_per_page');

            if ($current !== null) {
                DB::table('module_settings')->insert([
                    'module' => 'blog',
                    'key' => 'articles_per_page',
                    'value' => (string) $current,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn('articles_per_page');
            });
        }

        // La configuración se cachea en crudo, con los nombres de columna tal y
        // como estaban. Sin vaciarla, la instancia seguiría trayendo la clave
        // vieja y el sitio se quedaría sin nombre hasta el primer guardado.
        Cache::forget(Setting::CACHE_KEY);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('settings', 'articles_per_page')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->unsignedSmallInteger('articles_per_page')->default(12)->after('timezone');
            });

            $previous = DB::table('module_settings')
                ->where('module', 'blog')
                ->where('key', 'articles_per_page')
                ->value('value');

            if ($previous !== null) {
                DB::table('settings')->update(['articles_per_page' => (int) $previous]);
            }
        }

        Schema::dropIfExists('module_settings');
    }
};
