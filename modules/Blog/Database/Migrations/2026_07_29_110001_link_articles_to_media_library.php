<?php

use App\Enums\MediaProcessingStatus;
use App\Jobs\GenerateMediaVariants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * La imagen destacada de los artículos pasa a ser una referencia a la
 * biblioteca, para poder reutilizarla entre artículos y saber dónde se usa.
 *
 * Las imágenes ya subidas se dan de alta en la biblioteca conservando su
 * archivo: no se pierde nada de lo que hubiera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('featured_media_id')
                ->nullable()
                ->after('featured_image_alt')
                ->constrained('media')
                ->nullOnDelete();
        });

        $this->importExistingImages();

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('featured_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('featured_image_path')->nullable()->after('content_text');
        });

        // Se recupera la ruta desde la biblioteca antes de soltar la relación.
        DB::table('articles')
            ->join('media', 'media.id', '=', 'articles.featured_media_id')
            ->update(['articles.featured_image_path' => DB::raw('media.path')]);

        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('featured_media_id');
        });
    }

    /**
     * Da de alta en la biblioteca las imágenes destacadas que ya existían.
     */
    private function importExistingImages(): void
    {
        $disk = (string) config('media.disk', 'public');

        $articles = DB::table('articles')
            ->whereNotNull('featured_image_path')
            ->get(['id', 'title', 'featured_image_path', 'featured_image_alt', 'author_id']);

        foreach ($articles as $article) {
            $path = (string) $article->featured_image_path;

            if (! Storage::disk($disk)->exists($path)) {
                continue;
            }

            $size = Storage::disk($disk)->size($path);
            $mimeType = (string) Storage::disk($disk)->mimeType($path);
            $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';

            $mediaId = DB::table('media')->insertGetId([
                'name' => 'Portada de '.$article->title,
                'alt_text' => $article->featured_image_alt,
                'disk' => $disk,
                'path' => $path,
                'mime_type' => $mimeType,
                'extension' => $extension,
                'size' => $size,
                'processing_status' => MediaProcessingStatus::Pending->value,
                'uploaded_by' => $article->author_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('articles')->where('id', $article->id)->update(['featured_media_id' => $mediaId]);

            DB::table('media_usages')->insert([
                'media_id' => $mediaId,
                'usable_type' => 'App\Models\Article',
                'usable_id' => $article->id,
                'role' => 'featured',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Las variantes se generan después: la migración no debe depender
            // de que el procesado de imágenes funcione.
            GenerateMediaVariants::dispatch($mediaId);
        }
    }
};
