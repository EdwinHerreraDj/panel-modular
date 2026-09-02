<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biblioteca multimedia.
 *
 * `media_usages` registra dónde se está usando cada archivo, para poder avisar
 * antes de eliminarlo en lugar de dejar imágenes rotas por el blog.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            // Nombre visible, editable; el del archivo en disco es otro.
            $table->string('name');
            $table->string('alt_text')->nullable();
            $table->text('description')->nullable();

            $table->string('disk', 30);
            $table->string('path');
            $table->string('mime_type', 80);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // Rutas de las variantes generadas, por clave de tamaño y formato.
            $table->json('variants')->nullable();
            $table->string('processing_status', 20)->default('pending');
            $table->text('processing_error')->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('mime_type');
            $table->index('processing_status');
            $table->index('created_at');
        });

        Schema::create('media_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->morphs('usable');
            // Para qué se usa: portada, galería, logotipo…
            $table->string('role', 40)->default('content');
            $table->timestamps();

            $table->unique(['media_id', 'usable_type', 'usable_id', 'role'], 'media_usages_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('media');
    }
};
