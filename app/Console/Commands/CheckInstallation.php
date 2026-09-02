<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Installation\Check;
use App\Support\Installation\EnvironmentReport;
use Illuminate\Console\Command;

/**
 * Revisión del entorno antes de abrir al público.
 */
class CheckInstallation extends Command
{
    protected $signature = 'panel:comprobar';

    protected $description = 'Comprueba que la instalación está lista para atender público';

    public function handle(EnvironmentReport $report): int
    {
        $checks = $report->run();

        $this->newLine();
        $this->line('  <options=bold>Comprobación de la instalación</>');
        $this->newLine();

        foreach ($checks as $check) {
            [$icon, $color] = match (true) {
                $check->isError() => ['✗', 'red'],
                $check->isWarning() => ['!', 'yellow'],
                default => ['✓', 'green'],
            };

            $this->line(sprintf(
                '  <fg=%s>%s</> <options=bold>%-22s</> %s',
                $color,
                $icon,
                $check->name,
                $check->message,
            ));

            if ($check->fix !== null) {
                $this->line('    <fg=gray>→ '.$check->fix.'</>');
            }
        }

        $errors = array_filter($checks, fn (Check $check): bool => $check->isError());
        $warnings = array_filter($checks, fn (Check $check): bool => $check->isWarning());

        $this->newLine();

        if ($errors !== []) {
            $this->line('  <fg=red;options=bold>'.count($errors).' problema(s) que hay que resolver antes de abrir al público.</>');

            if ($warnings !== []) {
                $this->line('  <fg=yellow>Y '.count($warnings).' aviso(s) que conviene revisar.</>');
            }

            $this->newLine();

            return self::FAILURE;
        }

        if ($warnings !== []) {
            $this->line('  <fg=yellow;options=bold>Todo lo imprescindible está bien, con '.count($warnings).' aviso(s).</>');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->line('  <fg=green;options=bold>Todo correcto. La instalación está lista.</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
