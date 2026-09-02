<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El papel del usuario pasa a gestionarse con roles (Spatie), de modo que haya
 * una única fuente de verdad: se elimina la columna `is_super_admin`.
 *
 * Se añade además `must_change_password`, que obliga a cambiar la contraseña
 * inicial que fija el superadministrador al dar de alta una cuenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_super_admin']);
            $table->dropColumn('is_super_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('locale');
            $table->index('is_super_admin');
            $table->dropColumn('must_change_password');
        });
    }
};
