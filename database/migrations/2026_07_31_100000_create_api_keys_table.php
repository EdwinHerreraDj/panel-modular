<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claves de acceso a la API pública.
 *
 * La clave nunca se guarda en claro: se almacena su hash, igual que una
 * contraseña. El prefijo sí se guarda para poder reconocerla en la pantalla
 * («blog_a1b2…») sin poder reconstruirla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            // Los primeros caracteres, solo para identificarla visualmente.
            $table->string('prefix', 16);
            $table->string('key_hash', 64)->unique();

            // Dominios desde los que se acepta esta clave. Vacío = cualquiera.
            $table->json('allowed_origins')->nullable();

            // Límite propio; si es nulo se usa el general de config/api.php.
            $table->unsignedSmallInteger('rate_limit')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->unsignedBigInteger('requests_count')->default(0);

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->index('prefix');
            $table->index('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
