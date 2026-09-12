<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CitaAusenciaRegistrada;
use App\Models\EstadisticaPreventivaAusencia;

class RegistrarAusenciaEnEstadisticasPreventivas
{
    public function handle(CitaAusenciaRegistrada $event): void
    {
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
    }
}
