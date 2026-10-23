<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CitaAusenciaRegistrada;
use App\Models\EstadisticaPreventivaAusencia;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegistrarAusenciaEnEstadisticasPreventivas
{
    public function handle(CitaAusenciaRegistrada $event): void
    {
        try {
            $cita = $event->cita->loadMissing('expediente');

            if (! $cita->expediente) {
                return;
            }

            EstadisticaPreventivaAusencia::query()->firstOrCreate(
                ['cita_id' => $cita->id],
                [
                    'paciente_id' => $cita->expediente->paciente_id,
                    'profesional_id' => $cita->profesional_id,
                    'area_id' => $cita->area_id,
                    'fecha_registro' => $cita->fecha_registro_asistencia ?? now(),
                ]
            );
        } catch (Throwable $exception) {
            Log::error('Preventive absence statistics listener failed.', [
                'exception' => $exception::class,
            ]);
        }
    }
}
