<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los permisos del contenido pasan a llevar el prefijo de su módulo.
 *
 * `articles.view` se convierte en `blog.articles.view`. Con un solo tipo de
 * contenido daba igual; con dos, un cliente quiere poder dar el catálogo a
 * alguien sin darle el blog, y para eso los permisos tienen que decir de quién
 * son.
 *
 * Se renombra y no se recrea: `role_has_permissions` y `model_has_permissions`
 * referencian el permiso por su identificador, así que cambiando el nombre no
 * se pierde ninguna asignación de rol ni de usuario.
 */
return new class extends Migration
{
    /**
     * Nombre antiguo => nombre nuevo.
     *
     * @var array<string, string>
     */
    private const RENAMED = [
        'articles.view' => 'blog.articles.view',
        'articles.create' => 'blog.articles.create',
        'articles.update.own' => 'blog.articles.update.own',
        'articles.update.any' => 'blog.articles.update.any',
        'articles.delete' => 'blog.articles.delete',
        'articles.restore' => 'blog.articles.restore',
        'articles.publish' => 'blog.articles.publish',
        'articles.schedule' => 'blog.articles.schedule',
        'articles.approve' => 'blog.articles.approve',
        'categories.manage' => 'blog.categories.manage',
        'tags.manage' => 'blog.tags.manage',
    ];

    public function up(): void
    {
        $this->rename(self::RENAMED);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::RENAMED));
    }

    /**
     * @param  array<string, string>  $map
     */
    private function rename(array $map): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach ($map as $from => $to) {
            // Si el destino ya existe —una instalación sembrada de cero con el
            // código nuevo— no hay nada que renombrar y forzarlo rompería el
            // índice único de (name, guard_name).
            $exists = DB::table('permissions')->where('name', $to)->exists();

            if ($exists) {
                continue;
            }

            DB::table('permissions')->where('name', $from)->update(['name' => $to]);
        }
    }
};
