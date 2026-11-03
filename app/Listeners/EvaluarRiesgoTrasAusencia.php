<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CitaAusenciaRegistrada;
use App\Services\EvaluadorRiesgoPreventivo;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EvaluarRiesgoTrasAusencia
{
    public function __construct(private readonly EvaluadorRiesgoPreventivo $evaluador) {}

    public function handle(CitaAusenciaRegistrada $event): void
    {
        try {
            $this->evaluador->evaluarAusencia($event->cita);
        } catch (Throwable $exception) {
            // Never fail the clinical action. The hourly `alertas:reevaluar` schedule recovers the alert.
            Log::error('Preventive absence risk listener failed. Pending re-evaluation by scheduled alertas:reevaluar.', [
                'cita_id' => $event->cita->id,
                'paciente_id' => $event->cita->expediente?->paciente_id,
                'exception' => $exception::class,
            ]);
        }
    }
}
