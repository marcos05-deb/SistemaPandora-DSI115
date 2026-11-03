<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ConsultaSenalesRiesgoRegistradas;
use App\Services\EvaluadorRiesgoPreventivo;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EvaluarRiesgoTrasConsulta
{
    public function __construct(private readonly EvaluadorRiesgoPreventivo $evaluador) {}

    public function handle(ConsultaSenalesRiesgoRegistradas $event): void
    {
        try {
            $this->evaluador->evaluarConsulta($event->consulta);
        } catch (Throwable $exception) {
            // Never fail the clinical action. The hourly `alertas:reevaluar` schedule recovers the alert.
            Log::error('Preventive consultation risk listener failed. Pending re-evaluation by scheduled alertas:reevaluar.', [
                'consulta_id' => $event->consulta->id,
                'paciente_id' => $event->consulta->expediente?->paciente_id,
                'exception' => $exception::class,
            ]);
        }
    }
}
