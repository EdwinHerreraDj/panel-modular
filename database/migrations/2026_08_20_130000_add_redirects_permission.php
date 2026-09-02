<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las redirecciones pasan a tener su propio permiso.
 *
 * Estaban protegidas por `articles.update.any`: con un solo tipo de contenido
 * tenía sentido —quien edita artículos gestiona sus redirecciones—, pero con
 * varios módulos, quien lleva el catálogo también necesita tocar las
 * redirecciones de sus fichas sin que le den el blog entero.
 *
 * Se concede a quien ya podía hacerlo, así que nadie pierde acceso.
 */
return new class extends Migration
{
    private const NAME = 'redirects.manage';

    private const REPLACES = 'blog.articles.update.any';

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $id = DB::table('permissions')->where('name', self::NAME)->value('id');

        if ($id === null) {
            $id = DB::table('permissions')->insertGetId([
                'name' => self::NAME,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $previous = DB::table('permissions')->where('name', self::REPLACES)->value('id');

        if ($previous === null) {
            return;
        }

        // Los mismos roles que podían editar cualquier artículo.
        $roles = DB::table('role_has_permissions')
            ->where('permission_id', $previous)
            ->pluck('role_id');

        foreach ($roles as $roleId) {
            $exists = DB::table('role_has_permissions')
                ->where('permission_id', $id)
                ->where('role_id', $roleId)
                ->exists();

            if (! $exists) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $id,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $id = DB::table('permissions')->where('name', self::NAME)->value('id');

        if ($id !== null) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
