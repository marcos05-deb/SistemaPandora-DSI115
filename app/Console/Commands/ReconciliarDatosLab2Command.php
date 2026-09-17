<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EstadoCita;
use App\Models\Cita;
use App\Models\Consulta;
use App\Models\EstadisticaPreventivaAusencia;
use App\Models\Expediente;
use App\Models\Paciente;
use Illuminate\Console\Command;

/**
 * Reconciliación de datos históricos del Lab II (PAN-15/17/19).
 */
class ReconciliarDatosLab2Command extends Command
{
    protected $signature = 'pandora:reconciliar-datos-lab2 {--dry-run : Solo reportar sin escribir}';

    protected $description = 'Cancela citas futuras de expedientes cerrados, rellena fecha_primera_consulta y sincroniza estadísticas de ausencias';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $citas = $this->cancelarCitasFuturasDeCerrados($dry);
        $fechas = $this->rellenarFechasPrimeraConsulta($dry);
        $ausencias = $this->sincronizarEstadisticasAusencias($dry);

        $this->info("Citas futuras canceladas: {$citas}");
        $this->info("Fechas de primera consulta rellenadas: {$fechas}");
        $this->info("Estadísticas de ausencia sincronizadas: {$ausencias}");

        return self::SUCCESS;
    }

    private function cancelarCitasFuturasDeCerrados(bool $dry): int
    {
        $query = Cita::query()
            ->where('estado', EstadoCita::Programada->value)
            ->where('fecha_hora', '>', now())
            ->whereIn(
                'expediente_id',
                Expediente::withoutGlobalScopes()
                    ->where('estado', Expediente::ESTADO_CERRADO)
                    ->select('id')
            );

        $count = 0;
        $query->orderBy('id')->chunkById(50, function ($chunk) use ($dry, &$count) {
            foreach ($chunk as $cita) {
                $count++;
                if ($dry) {
                    continue;
                }

                $cita->estado = EstadoCita::Cancelada->value;
                $cita->motivo_cancelacion = $cita->motivo_cancelacion
                    ?: 'Cancelada automáticamente por cierre del expediente clínico (reconciliación).';
                $cita->fecha_cancelacion = $cita->fecha_cancelacion ?: now();
                $cita->save();
            }
        });

        return $count;
    }

    private function rellenarFechasPrimeraConsulta(bool $dry): int
    {
        $count = 0;

        Paciente::query()
            ->orderBy('codigo')
            ->chunkById(50, function ($pacientes) use ($dry, &$count) {
                foreach ($pacientes as $paciente) {
                    if (! blank($paciente->fecha_primera_consulta)) {
                        continue;
                    }

                    $primera = Consulta::query()
                        ->whereIn(
                            'expediente_id',
                            Expediente::withoutGlobalScopes()
                                ->where('paciente_id', $paciente->codigo)
                                ->select('id')
                        )
                        ->orderBy('fecha_consulta')
                        ->orderBy('created_at')
                        ->first();

                    if (! $primera?->fecha_consulta) {
                        continue;
                    }

                    $count++;
                    if ($dry) {
                        continue;
                    }

                    $paciente->fecha_primera_consulta = $primera->fecha_consulta
                        ->timezone(config('app.timezone'))
                        ->toDateString();
                    $paciente->save();
                }
            }, 'codigo');

        return $count;
    }

    private function sincronizarEstadisticasAusencias(bool $dry): int
    {
        $count = 0;

        Cita::query()
            ->where('estado', EstadoCita::Ausente->value)
            ->orderBy('id')
            ->chunkById(50, function ($chunk) use ($dry, &$count) {
                foreach ($chunk as $cita) {
                    $expediente = Expediente::withoutGlobalScopes()->find($cita->expediente_id);
                    if (! $expediente) {
                        continue;
                    }

                    $exists = EstadisticaPreventivaAusencia::query()
                        ->where('cita_id', $cita->id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $count++;
                    if ($dry) {
                        continue;
                    }

                    EstadisticaPreventivaAusencia::query()->create([
                        'cita_id' => $cita->id,
                        'paciente_id' => $expediente->paciente_id,
                        'profesional_id' => $cita->profesional_id,
                        'area_id' => $cita->area_id,
                        'fecha_registro' => $cita->fecha_registro_asistencia ?? $cita->fecha_hora ?? now(),
                    ]);
                }
            });

        return $count;
    }
}
