<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EvaluadorRiesgoPreventivo;
use Illuminate\Console\Command;

class ReevaluarAlertasCommand extends Command
{
    protected $signature = 'alertas:reevaluar {--paciente= : UUID del paciente}';

    protected $description = 'Reevalúa las señales preventivas con la rúbrica vigente.';

    public function handle(EvaluadorRiesgoPreventivo $evaluador): int
    {
        $count = $evaluador->reevaluar($this->option('paciente') ?: null);
        $this->info("Casos reevaluados: {$count}");

        return self::SUCCESS;
    }
}
