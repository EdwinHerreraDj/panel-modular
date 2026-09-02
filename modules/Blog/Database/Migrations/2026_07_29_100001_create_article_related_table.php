<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Artículos relacionados. La relación es dirigida: que A recomiende B no
 * implica que B recomiende A.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_related', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('related_article_id')->constrained('articles')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);

            $table->unique(['article_id', 'related_article_id']);
            $table->index('related_article_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_related');
    }
};
