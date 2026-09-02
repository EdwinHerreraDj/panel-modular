<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\PruneLogs;
use Illuminate\Console\Command;

class PruneLogsCommand extends Command
{
    protected $signature = 'panel:limpiar-registros {--dias= : Conservar solo los últimos N días}';

    protected $description = 'Borra los registros de accesos y auditoría más antiguos que el plazo configurado';

    public function handle(PruneLogs $prune): int
    {
        $days = $this->option('dias') === null ? null : (int) $this->option('dias');

        ['auth' => $auth, 'activity' => $activity] = $prune->handle($days);

        $kept = $days ?? (int) config('security.logs.retention_days');

        if ($auth === 0 && $activity === 0) {
            $this->components->info("No había nada que borrar (se conservan {$kept} días).");

            return self::SUCCESS;
        }

        $this->components->info(
            "Borrados {$auth} accesos y {$activity} cambios anteriores a {$kept} días."
        );

        return self::SUCCESS;
    }
}
