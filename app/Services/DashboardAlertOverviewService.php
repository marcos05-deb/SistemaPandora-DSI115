<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\StorePacienteRequest;
use App\Models\AlertaPreventiva;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Resumen agregado del panel clínico (alertas + tipología de casos).
 * Sin notas clínicas ni nombres; solo conteos y carnets operativos ya usados en bandeja.
 */
final class DashboardAlertOverviewService
{
    /** @var list<string> */
    public const INDICADORES_TENDENCIA_DEMO = [
        'ideas_suicidas',
        'planificacion',
        'intentos_previos',
        'autolesion',
    ];

    /**
     * @return array{
     *     visible: bool,
     *     total: int,
     *     abiertas: int,
     *     por_estado: list<array{key: string, label: string, count: int}>,
     *     por_senal: list<array{key: string, label: string, count: int}>,
     *     urgentes: list<array{id: string, senal: string, senal_label: string, estado: string, estado_label: string, paciente_carnet: string|null, generada_en: string|null}>,
     *     por_etiqueta: list<array{key: string, label: string, count: int}>,
     *     tendencia_suicida: array{
     *         alumnos_con_indicador: int,
     *         alumnos_con_alerta: int,
     *         alumnos_etiqueta_ideas: int,
     *         por_indicador: list<array{key: string, label: string, count: int}>
     *     }
     * }
     */
    public function forUser(Especialista $user): array
    {
        $emptyTendencia = [
            'alumnos_con_indicador' => 0,
            'alumnos_con_alerta' => 0,
            'alumnos_etiqueta_ideas' => 0,
            'por_indicador' => [],
        ];

        $empty = [
            'visible' => false,
            'total' => 0,
            'abiertas' => 0,
            'por_estado' => [],
            'por_senal' => [],
            'urgentes' => [],
            'por_etiqueta' => [],
            'tendencia_suicida' => $emptyTendencia,
        ];

        if (! Gate::forUser($user)->allows('viewAny', AlertaPreventiva::class)) {
            return $empty;
        }

        $areaIds = $user->areas()->pluck('areas.id')->all();
        if ($areaIds === []) {
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
            'por_etiqueta' => $this->aggregateEtiquetas($areaIds),
            'tendencia_suicida' => $this->aggregateTendenciaSuicida($areaIds),
        ];
    }

    /**
     * @param  list<int|string>  $areaIds
     * @return list<array{key: string, label: string, count: int}>
     */
    private function aggregateEtiquetas(array $areaIds): array
    {
        $counts = array_fill_keys(StorePacienteRequest::ETIQUETAS_VALIDAS, 0);

        $pacienteIds = Expediente::query()
            ->withoutGlobalScopes()
            ->whereIn('area_id', $areaIds)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('paciente_id');

        Paciente::query()
            ->whereIn('codigo', $pacienteIds)
            ->get(['codigo', 'etiquetas_motivo'])
            ->each(function (Paciente $paciente) use (&$counts): void {
                $tags = $paciente->etiquetas_motivo;
                if (! is_array($tags)) {
                    return;
                }
                foreach ($tags as $tag) {
                    if (is_string($tag) && array_key_exists($tag, $counts)) {
                        $counts[$tag]++;
                    }
                }
            });

        return array_map(
            static fn (string $label, int $count): array => [
                'key' => $label,
                'label' => $label,
                'count' => $count,
            ],
            array_keys($counts),
            array_values($counts)
        );
    }

    /**
     * @param  list<int|string>  $areaIds
     * @return array{
     *     alumnos_con_indicador: int,
     *     alumnos_con_alerta: int,
     *     alumnos_etiqueta_ideas: int,
     *     por_indicador: list<array{key: string, label: string, count: int}>
     * }
     */
    private function aggregateTendenciaSuicida(array $areaIds): array
    {
        $indicatorCounts = array_fill_keys(self::INDICADORES_TENDENCIA_DEMO, 0);
        $patientsWithIndicator = [];

        $consultas = Consulta::query()
            ->whereHas('expediente', static function ($query) use ($areaIds): void {
                $query->withoutGlobalScopes()
                    ->whereIn('area_id', $areaIds)
                    ->whereNull('deleted_at');
            })
            ->with(['expediente:id,paciente_id,area_id'])
            ->get(['id', 'expediente_id', 'indicadores_tendencia_suicida']);

        foreach ($consultas as $consulta) {
            $indicators = $consulta->indicadores_tendencia_suicida;
            if (! is_array($indicators) || $indicators === []) {
                continue;
            }

            $pacienteId = $consulta->expediente?->paciente_id;
            if (is_string($pacienteId) && $pacienteId !== '') {
                $patientsWithIndicator[$pacienteId] = true;
            }

            foreach ($indicators as $clave) {
                if (is_string($clave) && array_key_exists($clave, $indicatorCounts)) {
                    $indicatorCounts[$clave]++;
                }
            }
        }

        $alumnosConAlerta = (int) AlertaPreventiva::query()
            ->where('senal', 'tendencia_suicida')
            ->distinct('paciente_id')
            ->count('paciente_id');

        $alumnosEtiquetaIdeas = 0;
        $pacienteIds = Expediente::query()
            ->withoutGlobalScopes()
            ->whereIn('area_id', $areaIds)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('paciente_id');

        Paciente::query()
            ->whereIn('codigo', $pacienteIds)
            ->get(['codigo', 'etiquetas_motivo'])
            ->each(function (Paciente $paciente) use (&$alumnosEtiquetaIdeas): void {
                $tags = $paciente->etiquetas_motivo;
                if (is_array($tags) && in_array('Ideas Suicidas', $tags, true)) {
                    $alumnosEtiquetaIdeas++;
                }
            });

        $porIndicador = [];
        foreach ($indicatorCounts as $key => $count) {
            $porIndicador[] = [
                'key' => $key,
                'label' => $this->labelForTendenciaIndicador($key),
                'count' => $count,
            ];
        }

        return [
            'alumnos_con_indicador' => count($patientsWithIndicator),
            'alumnos_con_alerta' => $alumnosConAlerta,
            'alumnos_etiqueta_ideas' => $alumnosEtiquetaIdeas,
            'por_indicador' => $porIndicador,
        ];
    }

    private function labelForTendenciaIndicador(string $key): string
    {
        return match ($key) {
            'ideas_suicidas' => 'Ideas suicidas',
            'planificacion' => 'Planificación',
            'intentos_previos' => 'Intentos previos',
            'autolesion' => 'Autolesión',
            default => $key,
        };
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
