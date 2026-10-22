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
            Log::error('Preventive absence risk listener failed.', [
                'exception' => $exception::class,
            ]);
        }
    }
}
