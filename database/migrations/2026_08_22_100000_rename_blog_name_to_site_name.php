<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * La configuración es del sitio, no del blog.
 *
 * La columna nació llamándose `blog_name` cuando el panel solo servía para un
 * blog. Ahora un cliente puede tener solo el catálogo, y seguiría teniendo que
 * rellenar el «nombre del blog» para que su web supiera cómo se llama.
 *
 * La clave que sale a la API pública sigue siendo `nombre`: esto es un cambio
 * de dentro y ninguna web integrada se entera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->renameColumn('blog_name', 'site_name');
        });

        // La configuración se cachea en crudo, con los nombres de columna tal y
        // como estaban. Sin vaciarla, la instancia seguiría trayendo la clave
        // vieja y el sitio se quedaría sin nombre hasta el primer guardado.
        Cache::forget(Setting::CACHE_KEY);
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->renameColumn('site_name', 'blog_name');
        });
    }
};
