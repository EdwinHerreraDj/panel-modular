<?php

declare(strict_types=1);

namespace App\Modules\Values;

use Closure;

/**
 * Trabajo periódico que un módulo necesita hacer.
 *
 * Existe porque el alojamiento compartido no siempre tiene cron: el núcleo la
 * ejecuta desde el planificador cuando lo hay y, si no, como red de seguridad
 * después de responder a una visita. El módulo declara qué hacer; cuándo y
 * cómo es problema del núcleo.
 *
 * `hasWork` debe ser barato —un `exists` sobre un índice—: se consulta en
 * visitas normales y no puede costar nada.
 */
final readonly class DeferredTask
{
    /**
     * @param  string  $key  Clave del cerrojo; única en toda la plataforma
     * @param  Closure(): bool  $hasWork
     * @param  Closure(): void  $run
     * @param  int  $everyMinutes  Cada cuánto se comprueba, como mucho
     */
    public function __construct(
        public string $key,
        public Closure $hasWork,
        public Closure $run,
        public int $everyMinutes = 1,
    ) {}
}
