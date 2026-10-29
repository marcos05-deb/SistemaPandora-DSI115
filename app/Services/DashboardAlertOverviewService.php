<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\StorePacienteRequest;
use App\Models\AlertaPreventiva;
use App\Models\Area;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Resumen agregado del panel (alertas + tipología + tendencia).
 * Sysadmin: agregados protegidos, sin alertas individuales.
 * Coordinador/especialista: carnet solo en la lista de alertas urgentes.
 */
final class DashboardAlertOverviewService
{
    public function __construct(private readonly ClinicalAccessAuditor $auditor) {}

    /**
     * @return array{
     *     visible: bool,
     *     reveal_carnets: bool,
     *     scope: 'institucional'|'area'|null,
     *     total: int|null,
     *     abiertas: int|null,
     *     privacidad_protegida?: bool,
     *     por_estado: list<array{key: string, label: string, count: int|null}>,
     *     por_senal: list<array{key: string, label: string, count: int|null}>,
     *     urgentes: list<array{id: string, senal: string, senal_label: string, estado: string, estado_label: string, generada_en: string|null, paciente_carnet?: string|null}>,
     *     por_etiqueta: list<array{key: string, label: string, count: int|null}>,
     *     tendencia_suicida: array{
     *         total_alumnos: int|null,
     *         por_facultad: list<array{id: int, label: string, count: int|null}>,
     *         por_carrera: list<array{id: int, label: string, facultad_id: int, facultad_label: string, count: int|null}>
     *     }
     * }
     */
    public function forUser(Especialista $user): array
    {
        $emptyTendencia = [
            'total_alumnos' => 0,
            'por_facultad' => [],
            'por_carrera' => [],
        ];

        $empty = [
            'visible' => false,
            'reveal_carnets' => false,
            'scope' => null,
            'privacidad_protegida' => false,
            'total' => 0,
            'abiertas' => 0,
            'por_estado' => [],
            'por_senal' => [],
            'urgentes' => [],
            'por_etiqueta' => [],
            'tendencia_suicida' => $emptyTendencia,
        ];

        $isSysadmin = $user->hasRole('sysadmin');
        $canClinicalOverview = Gate::forUser($user)->allows('viewAny', AlertaPreventiva::class);

        if (! $isSysadmin && ! $canClinicalOverview) {
            return $empty;
        }

        if ($isSysadmin) {
            $areaIds = Area::query()->orderBy('id')->pluck('id')->all();
            $scope = 'institucional';
            $revealCarnets = false;
        } else {
            $areaIds = $user->areas()->pluck('areas.id')->all();
            if ($areaIds === []) {
                return $empty;
            }
            $scope = 'area';
            $revealCarnets = $user->hasRole('area_coordinator') || $user->hasRole('specialist');
        }

        $base = AlertaPreventiva::query()->whereIn('area_id', $areaIds);

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

        $urgentesQuery = (clone $base)
            ->whereIn('estado', AlertaPreventiva::ESTADOS_ACTIVOS)
            ->orderByRaw("CASE estado WHEN 'activa' THEN 0 WHEN 'en_seguimiento' THEN 1 ELSE 2 END")
            ->orderByDesc('generada_en')
            ->limit(5);

        if ($revealCarnets) {
            $urgentesQuery->with(['paciente:codigo,carnet']);
        }

        $urgentes = ! $revealCarnets ? [] : $urgentesQuery
            ->get()
            ->map(static function (AlertaPreventiva $alerta) use ($revealCarnets): array {
                $row = [
                    'id' => (string) $alerta->id,
                    'senal' => (string) $alerta->senal,
                    'senal_label' => AlertaPreventiva::labelForSignal((string) $alerta->senal),
                    'estado' => (string) $alerta->estado,
                    'estado_label' => match ($alerta->estado) {
                        'activa' => 'Activa',
                        'en_seguimiento' => 'En seguimiento',
                        default => (string) $alerta->estado,
                    },
                    'generada_en' => $alerta->generada_en?->toIso8601String(),
                ];

                if ($revealCarnets) {
                    $row['paciente_carnet'] = $alerta->paciente?->carnet;
                }

                return $row;
            })
            ->values()
            ->all();

        $etiquetas = $this->aggregateEtiquetas($areaIds);
        $tendencia = $this->aggregateTendenciaSuicida($areaIds);
        $protectedAlerts = ! $revealCarnets && $this->hasSmallCounts(array_merge($porEstado, $porSenal));
        if ($protectedAlerts) {
            $porEstado = $this->hideCounts($porEstado);
            $porSenal = $this->hideCounts($porSenal);
        }
        $protectedTags = $this->hasSmallCounts($etiquetas);
        if ($protectedTags) {
            $etiquetas = $this->hideCounts($etiquetas);
        }
        $protectedTrend = $this->hasSmallCounts(array_merge($tendencia['por_facultad'], $tendencia['por_carrera']));
        if ($protectedTrend) {
            // Hide both hierarchies and their total to prevent reconstruction by difference.
            $tendencia['por_facultad'] = $this->hideCounts($tendencia['por_facultad']);
            $tendencia['por_carrera'] = $this->hideCounts($tendencia['por_carrera']);
            $tendencia['total_alumnos'] = null;
        }

        $filters = ['scope' => $scope, 'areas' => $areaIds];
        $this->auditor->recordAggregate($user, 'view_dashboard_overview', $filters);
        if ($revealCarnets) {
            $this->auditor->recordListing($user, AlertaPreventiva::class, $filters);
        }

        return [
            'visible' => true,
            'reveal_carnets' => $revealCarnets,
            'scope' => $scope,
            'total' => $protectedAlerts ? null : $total,
            'abiertas' => $protectedAlerts ? null : $abiertas,
            'privacidad_protegida' => $protectedAlerts || $protectedTags || $protectedTrend,
            'por_estado' => $porEstado,
            'por_senal' => $porSenal,
            'urgentes' => $urgentes,
            'por_etiqueta' => $etiquetas,
            'tendencia_suicida' => $tendencia,
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private function hasSmallCounts(array $rows): bool
    {
        $minimum = max(2, (int) config('indicadores.min_celda', 5));

        return collect($rows)->contains(static fn (array $row): bool => $row['count'] > 0 && $row['count'] < $minimum);
    }

    /**
     * Suppress the whole related breakdown rather than expose complementary totals.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function hideCounts(array $rows): array
    {
        return array_map(static fn (array $row): array => array_merge($row, ['count' => null]), $rows);
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
     * Alumnos del área con señal de tendencia suicida (consulta, alerta o etiqueta),
     * agregados por facultad y carrera (sin PHI).
     *
     * @param  list<int|string>  $areaIds
     * @return array{
     *     total_alumnos: int,
     *     por_facultad: list<array{id: int, label: string, count: int}>,
     *     por_carrera: list<array{id: int, label: string, facultad_id: int, facultad_label: string, count: int}>
     * }
     */
    private function aggregateTendenciaSuicida(array $areaIds): array
    {
        $pacienteIds = $this->pacienteIdsConTendenciaSuicida($areaIds);

        if ($pacienteIds === []) {
            return [
                'total_alumnos' => 0,
                'por_facultad' => [],
                'por_carrera' => [],
            ];
        }

        $rows = Paciente::query()
            ->whereIn('codigo', $pacienteIds)
            ->join('carreras', 'carreras.id', '=', 'pacientes.carrera_id')
            ->join('facultades', 'facultades.id', '=', 'carreras.facultad_id')
            ->groupBy('carreras.id', 'carreras.nombre', 'facultades.id', 'facultades.nombre')
            ->orderByDesc(DB::raw('COUNT(DISTINCT pacientes.codigo)'))
            ->orderBy('facultades.nombre')
            ->orderBy('carreras.nombre')
            ->get([
                'carreras.id as carrera_id',
                'carreras.nombre as carrera_nombre',
                'facultades.id as facultad_id',
                'facultades.nombre as facultad_nombre',
                DB::raw('COUNT(DISTINCT pacientes.codigo) as aggregate'),
            ]);

        $porFacultadMap = [];
        $porCarrera = [];

        foreach ($rows as $row) {
            $facultadId = (int) $row->facultad_id;
            $count = (int) $row->aggregate;

            if (! isset($porFacultadMap[$facultadId])) {
                $porFacultadMap[$facultadId] = [
                    'id' => $facultadId,
                    'label' => (string) $row->facultad_nombre,
                    'count' => 0,
                ];
            }
            $porFacultadMap[$facultadId]['count'] += $count;

            $porCarrera[] = [
                'id' => (int) $row->carrera_id,
                'label' => (string) $row->carrera_nombre,
                'facultad_id' => $facultadId,
                'facultad_label' => (string) $row->facultad_nombre,
                'count' => $count,
            ];
        }

        $porFacultad = array_values($porFacultadMap);
        usort($porFacultad, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return [
            'total_alumnos' => count($pacienteIds),
            'por_facultad' => $porFacultad,
            'por_carrera' => $porCarrera,
        ];
    }

    /**
     * @param  list<int|string>  $areaIds
     * @return list<string>
     */
    private function pacienteIdsConTendenciaSuicida(array $areaIds): array
    {
        $ids = [];

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
                $ids[$pacienteId] = true;
            }
        }

        AlertaPreventiva::query()
            ->whereIn('area_id', $areaIds)
            ->where('senal', 'tendencia_suicida')
            ->pluck('paciente_id')
            ->each(static function (mixed $pacienteId) use (&$ids): void {
                if (is_string($pacienteId) && $pacienteId !== '') {
                    $ids[$pacienteId] = true;
                }
            });

        $areaPacienteIds = Expediente::query()
            ->withoutGlobalScopes()
            ->whereIn('area_id', $areaIds)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('paciente_id');

        Paciente::query()
            ->whereIn('codigo', $areaPacienteIds)
            ->get(['codigo', 'etiquetas_motivo'])
            ->each(static function (Paciente $paciente) use (&$ids): void {
                $tags = $paciente->etiquetas_motivo;
                if (is_array($tags) && in_array('Ideas Suicidas', $tags, true)) {
                    $ids[(string) $paciente->codigo] = true;
                }
            });

        return array_keys($ids);
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
