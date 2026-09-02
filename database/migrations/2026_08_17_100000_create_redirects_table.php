<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Redirecciones para no perder los enlaces que ya están repartidos por ahí.
 *
 * Dos orígenes:
 *
 * - **Automáticas**: al cambiar la dirección de un artículo se guarda la
 *   anterior apuntando a la nueva.
 * - **Manuales**: las que se añaden a mano, sobre todo al traer un blog que ya
 *   existía y cuyas direcciones eran distintas.
 *
 * Las sirve la API para que la web pública las aplique: el panel no atiende
 * tráfico público, así que aquí solo se guardan y se ordenan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();

            // Dirección antigua. Sin barra inicial y en minúsculas.
            $table->string('from_path', 500);
            // Dirección nueva: la de un artículo, o una URL completa externa.
            $table->string('to_path', 500);

            $table->unsignedSmallInteger('status_code')->default(301);

            // Las automáticas no se pueden editar: las gobierna el sistema.
            $table->boolean('is_automatic')->default(false);

            // Cuando apunta a un artículo concreto se guarda, para poder
            // rehacer la redirección si su dirección vuelve a cambiar.
            // Nació atada al blog; una migración posterior la hace polimórfica.
            // La columna solo se crea si el blog está instalado: sin él, la
            // tabla de redirecciones —que es del núcleo— tiene que poder
            // crearse igual. En las instalaciones en marcha ya corrió con la
            // columna y el resultado final es el mismo.
            if (Schema::hasTable('articles')) {
                $table->foreignId('article_id')->nullable()->constrained()->cascadeOnDelete();
            }

            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();

            $table->timestamps();

            // Una dirección antigua solo puede apuntar a un sitio.
            $table->unique('from_path');
            if (Schema::hasTable('articles')) {
                $table->index('article_id');
            }
            $table->index('is_automatic');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
