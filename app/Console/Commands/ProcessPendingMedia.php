<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MediaProcessingStatus;
use App\Jobs\GenerateMediaVariants;
use App\Models\Media;
use Illuminate\Console\Command;
use Throwable;

/**
 * Genera las variantes de las imágenes que se quedaron sin procesar.
 *
 * Útil tras un fallo del worker, al importar imágenes antiguas o al cambiar los
 * tamaños configurados.
 */
class ProcessPendingMedia extends Command
{
    protected $signature = 'medios:procesar-pendientes
                            {--fallidas : Reintenta también las que fallaron}
                            {--todas : Regenera las variantes de toda la biblioteca}';

    protected $description = 'Genera las variantes de las imágenes pendientes de procesar';

    public function handle(): int
    {
        $query = Media::query();

        if ($this->option('todas')) {
            $this->info('Regenerando las variantes de toda la biblioteca.');
        } elseif ($this->option('fallidas')) {
            $query->whereIn('processing_status', [
                MediaProcessingStatus::Pending->value,
                MediaProcessingStatus::Failed->value,
            ]);
        } else {
            $query->where('processing_status', MediaProcessingStatus::Pending->value);
        }

        $media = $query->get();

        if ($media->isEmpty()) {
            $this->info('No hay imágenes pendientes de procesar.');

            return self::SUCCESS;
        }

        $processed = 0;
        $failed = 0;

        foreach ($media as $item) {
            try {
                // Se ejecuta aquí mismo: el sentido del comando es no depender
                // de que haya un worker corriendo.
                (new GenerateMediaVariants($item->id))->handle();

                $this->line("Procesada: {$item->name}");
                $processed++;
            } catch (Throwable $exception) {
                $this->error("Error en «{$item->name}»: {$exception->getMessage()}");
                $failed++;
            }
        }

        $this->info("Imágenes procesadas: {$processed}.".($failed > 0 ? " Con error: {$failed}." : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
