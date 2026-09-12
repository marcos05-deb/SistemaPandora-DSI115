<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Especialista;
use App\Models\EstadisticaPreventivaAusencia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Consulta verificable de ausencias para alertas preventivas (HU-12 / NH-03).
 */
final class EstadisticasPreventivasService
{
    /**
     * @return array{
     *     total: int,
     *     por_paciente: Collection<string, int>,
     *     items: Collection<int, EstadisticaPreventivaAusencia>
     * }
     */
    public function ausencias(
        Especialista $especialista,
        ?string $pacienteId = null,
        ?Carbon $desde = null,
        ?Carbon $hasta = null
    ): array {
        $areas = $especialista->areas()->pluck('areas.id');

        if ($areas->isEmpty() && $especialista->profesional?->area_id) {
            $areas = collect([$especialista->profesional->area_id]);
        }

        $query = EstadisticaPreventivaAusencia::query()
            ->with(['paciente', 'profesional.especialista', 'area'])
            ->whereIn('area_id', $areas);

        if ($pacienteId) {
            $query->where('paciente_id', $pacienteId);
        }

        if ($desde) {
            $query->where('fecha_registro', '>=', $desde);
        }

        if ($hasta) {
            $query->where('fecha_registro', '<=', $hasta);
        }

        $items = $query->orderByDesc('fecha_registro')->get();

        return [
            'total' => $items->count(),
            'por_paciente' => $items->groupBy('paciente_id')->map->count(),
            'items' => $items,
        ];
    }

    public function contarAusenciasPaciente(Especialista $especialista, string $pacienteId): int
    {
        return $this->ausencias($especialista, $pacienteId)['total'];
    }
}
