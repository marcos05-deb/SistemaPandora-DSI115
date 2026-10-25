<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AlertaPreventiva;
use App\Models\Especialista;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Resumen agregado de alertas para el panel clínico (sin PHI clínico).
 * Solo carnet operativo + señal/estado ya visibles en la bandeja de alertas.
 */
final class DashboardAlertOverviewService
{
    /**
     * @return array{
     *     visible: bool,
     *     total: int,
     *     abiertas: int,
     *     por_estado: list<array{key: string, label: string, count: int}>,
     *     por_senal: list<array{key: string, label: string, count: int}>,
     *     urgentes: list<array{id: string, senal: string, senal_label: string, estado: string, estado_label: string, paciente_carnet: string|null, generada_en: string|null}>
     * }
     */
    public function forUser(Especialista $user): array
    {
        $empty = [
            'visible' => false,
            'total' => 0,
            'abiertas' => 0,
            'por_estado' => [],
            'por_senal' => [],
            'urgentes' => [],
        ];

        if (! Gate::forUser($user)->allows('viewAny', AlertaPreventiva::class)) {
            return $empty;
        }

        $base = AlertaPreventiva::query();

        $porEstadoRaw = (clone $base)
            ->selectRaw('estado, COUNT(*) as aggregate')
            ->groupBy('estado')
            ->pluck('aggregate', 'estado');

        $porSenalRaw = (clone $base)
            ->selectRaw('senal, COUNT(*) as aggregate')
            ->groupBy('senal')
            ->pluck('aggregate', 'senal');

        $porEstado = $this->mapCounts(
            AlertaPreventiva::ESTADOS,
            $porEstadoRaw,
            static fn (string $key): string => match ($key) {
                'activa' => 'Activa',
                'en_seguimiento' => 'En seguimiento',
                'cerrada' => 'Cerrada',
                'descartada' => 'Descartada',
                default => $key,
            }
        );

        $porSenal = $this->mapCounts(
            AlertaPreventiva::SENALES,
            $porSenalRaw,
            static fn (string $key): string => AlertaPreventiva::labelForSignal($key)
        );

        $total = (int) $porEstadoRaw->sum();
        $abiertas = (int) collect(AlertaPreventiva::ESTADOS_ACTIVOS)
            ->sum(static fn (string $estado): int => (int) ($porEstadoRaw[$estado] ?? 0));

        $urgentes = (clone $base)
            ->with(['paciente:codigo,carnet'])
            ->whereIn('estado', AlertaPreventiva::ESTADOS_ACTIVOS)
            ->orderByRaw("CASE estado WHEN 'activa' THEN 0 WHEN 'en_seguimiento' THEN 1 ELSE 2 END")
            ->orderByDesc('generada_en')
            ->limit(5)
            ->get()
            ->map(static function (AlertaPreventiva $alerta): array {
                return [
                    'id' => (string) $alerta->id,
                    'senal' => (string) $alerta->senal,
                    'senal_label' => AlertaPreventiva::labelForSignal((string) $alerta->senal),
                    'estado' => (string) $alerta->estado,
                    'estado_label' => match ($alerta->estado) {
                        'activa' => 'Activa',
                        'en_seguimiento' => 'En seguimiento',
                        default => (string) $alerta->estado,
                    },
                    'paciente_carnet' => $alerta->paciente?->carnet,
                    'generada_en' => $alerta->generada_en?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return [
            'visible' => true,
            'total' => $total,
            'abiertas' => $abiertas,
            'por_estado' => $porEstado,
            'por_senal' => $porSenal,
            'urgentes' => $urgentes,
        ];
    }

    /**
     * @param  list<string>  $keys
     * @param  Collection<string, int|string>  $counts
     * @param  callable(string): string  $label
     * @return list<array{key: string, label: string, count: int}>
     */
    private function mapCounts(array $keys, Collection $counts, callable $label): array
    {
        return array_map(
            static fn (string $key): array => [
                'key' => $key,
                'label' => $label($key),
                'count' => (int) ($counts[$key] ?? 0),
            ],
            $keys
        );
    }
}
