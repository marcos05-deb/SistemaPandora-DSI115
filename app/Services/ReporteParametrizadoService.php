<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\FiltrosIndicador;
use App\DTO\ParametrosReporte;
use App\DTO\ReporteGenerado;
use App\DTO\ResultadoIndicadores;
use App\Enums\AgrupacionReporte;
use App\Enums\TipoIndicador;
use App\Models\Especialista;
use App\Policies\IndicadorPolicy;
use App\Policies\ReportePolicy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ReporteParametrizadoService
{
    public function __construct(
        private readonly IndicadorService $indicadores,
        private readonly IndicadorPolicy $indicadorPolicy,
        private readonly ReportePolicy $policy,
    ) {}

    /** @return array<string, mixed> */
    public function datosFormulario(Especialista $user): array
    {
        if (! $this->policy->verFormulario($user)) {
            throw new AuthorizationException;
        }

        $areaIds = $this->indicadorPolicy->areasPermitidas($user);
        $areas = DB::table('areas')
            ->whereIn('id', $areaIds)
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(static fn (object $area): array => [
                'id' => (int) $area->id,
                'nombre' => (string) $area->nombre,
            ])
            ->all();
        $today = CarbonImmutable::today(config('app.timezone'));

        return [
            'layout' => $this->indicadorPolicy->layoutFor($user),
            'areas' => $areas,
            'predeterminados' => [
                'desde' => $today->subDays(29)->toDateString(),
                'hasta' => $today->toDateString(),
                'area' => $this->indicadorPolicy->verTodasAreas($user)
                    ? 'todas'
                    : (string) ($areas[0]['id'] ?? ''),
                'indicadores' => TipoIndicador::valores(),
                'agrupar_por' => [AgrupacionReporte::Indicador->value],
            ],
            'puede_todas_areas' => $this->indicadorPolicy->verTodasAreas($user),
            'catalogo' => $this->columnasPermitidas($user),
            'agrupaciones' => array_map(static fn (AgrupacionReporte $group): array => [
                'id' => $group->value,
                'etiqueta' => $group->etiqueta(),
            ], AgrupacionReporte::cases()),
        ];
    }

    /** @return list<array{id: string, etiqueta: string, definicion: string, formula: string, fuente: string, categoria: string}> */
    public function columnasPermitidas(Especialista $user): array
    {
        if (! $this->policy->verFormulario($user)) {
            throw new AuthorizationException;
        }

        return array_map(static fn (TipoIndicador $indicator): array => [
            'id' => $indicator->value,
            'etiqueta' => $indicator->etiqueta(),
            'definicion' => $indicator->definicion(),
            'formula' => $indicator->formula(),
            'fuente' => $indicator->fuente(),
            'categoria' => $indicator->categoria(),
        ], TipoIndicador::cases());
    }

    public function generar(ParametrosReporte $parameters, Especialista $user): ReporteGenerado
    {
        if (! $this->policy->generar($user, $parameters->areaId, $parameters->todasAreas)) {
            throw new AuthorizationException;
        }

        $overall = $this->indicadores->calcular($this->filtros($parameters), $user);
        $groups = [];
        foreach ($this->periodBuckets($parameters) as $bucket) {
            $result = $bucket['desde'] === $parameters->desde && $bucket['hasta'] === $parameters->hasta
                ? $overall
                : $this->indicadores->calcular($this->filtros($parameters, $bucket['desde'], $bucket['hasta']), $user);

            $this->appendGroups($groups, $result, $parameters, $bucket['dimensiones']);
        }

        return new ReporteGenerado(
            parametrosAplicados: $parameters->toArray(),
            generadoEn: now()->toIso8601String(),
            generadoPor: $user->name,
            columnas: array_values(array_filter(
                $this->columnasPermitidas($user),
                static fn (array $column): bool => in_array($column['id'], $parameters->indicadores, true),
            )),
            grupos: array_values($groups),
            totales: $overall->totales,
            sinDatos: $overall->sinDatos,
            minimoCelda: $overall->minimoCelda,
        );
    }

    private function filtros(ParametrosReporte $parameters, ?string $desde = null, ?string $hasta = null): FiltrosIndicador
    {
        return new FiltrosIndicador(
            desde: $desde ?? $parameters->desde,
            hasta: $hasta ?? $parameters->hasta,
            areaId: $parameters->areaId,
            indicadores: $parameters->indicadores,
        );
    }

    /**
     * @return list<array{desde: string, hasta: string, dimensiones: array<string, string>}>
     */
    private function periodBuckets(ParametrosReporte $parameters): array
    {
        $timeGroups = array_intersect($parameters->agruparPor, [AgrupacionReporte::Mes->value, AgrupacionReporte::Semana->value]);
        if ($timeGroups === []) {
            return [['desde' => $parameters->desde, 'hasta' => $parameters->hasta, 'dimensiones' => []]];
        }

        $start = CarbonImmutable::parse($parameters->desde, config('app.timezone'))->startOfDay();
        $end = CarbonImmutable::parse($parameters->hasta, config('app.timezone'))->startOfDay();
        $buckets = [];
        $current = null;

        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            $dimensions = [];
            if (in_array(AgrupacionReporte::Mes->value, $timeGroups, true)) {
                $dimensions[AgrupacionReporte::Mes->value] = $day->format('Y-m');
            }
            if (in_array(AgrupacionReporte::Semana->value, $timeGroups, true)) {
                $dimensions[AgrupacionReporte::Semana->value] = $day->startOfWeek(CarbonInterface::MONDAY)->toDateString();
            }

            $key = json_encode($dimensions, JSON_THROW_ON_ERROR);
            if ($current === null || $current['key'] !== $key) {
                if ($current !== null) {
                    $buckets[] = $current['bucket'];
                }
                $current = [
                    'key' => $key,
                    'bucket' => [
                        'desde' => $day->toDateString(),
                        'hasta' => $day->toDateString(),
                        'dimensiones' => $dimensions,
                    ],
                ];
            } else {
                $current['bucket']['hasta'] = $day->toDateString();
            }
        }

        if ($current !== null) {
            $buckets[] = $current['bucket'];
        }

        return $buckets;
    }

    /**
     * @param  array<string, array{dimensiones: array<string, mixed>, valores: array<string, array<string, mixed>>}>  $groups
     * @param  array<string, string>  $periodDimensions
     */
    private function appendGroups(
        array &$groups,
        ResultadoIndicadores $result,
        ParametrosReporte $parameters,
        array $periodDimensions,
    ): void {
        $areaGrouped = in_array(AgrupacionReporte::Area->value, $parameters->agruparPor, true);
        $indicatorGrouped = in_array(AgrupacionReporte::Indicador->value, $parameters->agruparPor, true);
        $areas = $areaGrouped ? $result->areas : [null];

        foreach ($areas as $area) {
            $indicatorIds = $indicatorGrouped ? $parameters->indicadores : [null];
            foreach ($indicatorIds as $indicatorId) {
                $dimensions = $periodDimensions;
                if ($areaGrouped && $area !== null) {
                    $dimensions['area_id'] = $area['id'];
                    $dimensions['area'] = $area['nombre'];
                }
                if ($indicatorGrouped && $indicatorId !== null) {
                    $dimensions['indicador'] = $indicatorId;
                    $dimensions['indicador_etiqueta'] = TipoIndicador::from($indicatorId)->etiqueta();
                }

                $key = json_encode($dimensions, JSON_THROW_ON_ERROR);
                $groups[$key] ??= ['dimensiones' => $dimensions, 'valores' => []];
                foreach ($indicatorGrouped ? [$indicatorId] : $parameters->indicadores as $id) {
                    $groups[$key]['valores'][$id] = $this->valorIndicador(
                        $result,
                        $id,
                        $area === null ? null : $area['id'],
                    );
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function valorIndicador(ResultadoIndicadores $result, string $indicatorId, ?int $areaId): array
    {
        $total = $result->totales[$indicatorId];
        if ($areaId === null) {
            return $total;
        }

        $rows = array_values(array_filter($result->filas, static fn (array $row): bool => $row['indicador'] === $indicatorId && $row['area_id'] === $areaId
        ));
        if ($indicatorId === TipoIndicador::AlertasGeneradas->value) {
            $suppressed = array_values(array_filter($rows, static fn (array $row): bool => $row['suprimido']));
            if ($suppressed !== []) {
                return [
                    ...$total,
                    'valor' => null,
                    'suprimido' => true,
                    'supresion' => in_array('primaria', array_column($suppressed, 'supresion'), true)
                        ? 'primaria'
                        : 'complementaria',
                ];
            }

            return [
                ...$total,
                'valor' => array_sum(array_column($rows, 'valor')),
                'suprimido' => false,
                'supresion' => null,
            ];
        }

        $row = $rows[0] ?? null;
        if ($row === null) {
            return [...$total, 'valor' => 0, 'suprimido' => false, 'supresion' => null];
        }

        return [
            ...$total,
            'valor' => $row['valor'],
            'suprimido' => $row['suprimido'],
            'supresion' => $row['supresion'],
        ];
    }
}
