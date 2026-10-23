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
            Log::error('Preventive consultation risk listener failed.', [
                'exception' => $exception::class,
            ]);
        }
    }
}
