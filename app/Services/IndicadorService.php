<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\FiltrosIndicador;
use App\DTO\ResultadoIndicadores;
use App\Enums\EstadoCita;
use App\Enums\TipoIndicador;
use App\Models\AlertaPreventiva;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Policies\IndicadorPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class IndicadorService
{
    public function __construct(private readonly IndicadorPolicy $policy) {}

    public function calcular(FiltrosIndicador $filtros, Especialista $user): ResultadoIndicadores
    {
        if (! $this->policy->ver($user, $filtros->areaId)) {
            throw new AuthorizationException;
        }

        $areaIds = $this->policy->areasPermitidas($user);
        $areasDisponibles = DB::table('areas')
            ->whereIn('id', $areaIds)
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(static fn (object $area): array => [
                'id' => (int) $area->id,
                'nombre' => (string) $area->nombre,
            ])
            ->all();
        $areas = $filtros->areaId === null
            ? $areasDisponibles
            : array_values(array_filter($areasDisponibles, static fn (array $area): bool => $area['id'] === $filtros->areaId));
        $areaIds = array_column($areas, 'id');
        $inicio = CarbonImmutable::parse($filtros->desde, config('app.timezone'))
            ->startOfDay()
            ->utc()
            ->format('Y-m-d H:i:s.uP');
        $fin = CarbonImmutable::parse($filtros->hasta, config('app.timezone'))
            ->endOfDay()
            ->utc()
            ->format('Y-m-d H:i:s.uP');

        $porArea = [];
        foreach ($areaIds as $areaId) {
            $porArea[$areaId] = [
                TipoIndicador::ConsultasRegistradas->value => 0,
                TipoIndicador::PacientesAtendidos->value => 0,
                TipoIndicador::ExpedientesAbiertos->value => 0,
                TipoIndicador::ExpedientesCerrados->value => 0,
                TipoIndicador::CitasProgramadas->value => 0,
                TipoIndicador::CitasAsistidas->value => 0,
                TipoIndicador::CitasNoAsistidas->value => 0,
                TipoIndicador::CitasCanceladas->value => 0,
                TipoIndicador::CitasReprogramadas->value => 0,
                TipoIndicador::AlertasGeneradas->value => array_fill_keys(AlertaPreventiva::SENALES, 0),
                TipoIndicador::SeguimientosRegistrados->value => 0,
            ];
        }

        // Aggregate projections and policy-scoped area IDs follow ESTANDARES.md §4.44; source models are never hydrated.
        $pacientesTotal = 0;
        if (array_intersect($filtros->indicadores, [
            TipoIndicador::ConsultasRegistradas->value,
            TipoIndicador::PacientesAtendidos->value,
        ]) !== []) {
            $pacientesTotal = $this->consultasPorArea($porArea, $areaIds, $inicio, $fin);
        }
        if (array_intersect($filtros->indicadores, [
            TipoIndicador::ExpedientesAbiertos->value,
            TipoIndicador::ExpedientesCerrados->value,
        ]) !== []) {
            $this->expedientesPorArea($porArea, $areaIds, $inicio, $fin);
        }
        if (array_intersect($filtros->indicadores, [
            TipoIndicador::CitasProgramadas->value,
            TipoIndicador::CitasAsistidas->value,
            TipoIndicador::CitasNoAsistidas->value,
            TipoIndicador::CitasCanceladas->value,
            TipoIndicador::CitasReprogramadas->value,
            TipoIndicador::TasaAsistencia->value,
        ]) !== []) {
            $this->citasPorArea($porArea, $areaIds, $inicio, $fin);
        }
        if (in_array(TipoIndicador::AlertasGeneradas->value, $filtros->indicadores, true)) {
            $this->alertasPorArea($porArea, $areaIds, $inicio, $fin);
        }
        if (in_array(TipoIndicador::SeguimientosRegistrados->value, $filtros->indicadores, true)) {
            $this->seguimientosPorArea($porArea, $areaIds, $inicio, $fin);
        }

        $minimo = max(1, (int) config('indicadores.min_celda', 5));
        $indicadores = array_map(
            static fn (string $value): TipoIndicador => TipoIndicador::from($value),
            $filtros->indicadores,
        );
        $filas = [];
        $totales = [];
        $hayDatos = false;

        foreach ($indicadores as $indicador) {
            if ($indicador === TipoIndicador::AlertasGeneradas) {
                $alertasTotal = 0;
                $alertasSuprimidas = false;

                foreach (AlertaPreventiva::SENALES as $senal) {
                    $valores = [];
                    foreach ($areaIds as $areaId) {
                        $valores[$areaId] = $porArea[$areaId][$indicador->value][$senal];
                    }
                    $total = array_sum($valores);
                    $hayDatos = $hayDatos || $total > 0;
                    [$celdas, $supresionTotal] = $this->suprimirCeldas(
                        $valores,
                        $minimo,
                        totalPrimario: $total < $minimo,
                    );
                    $alertasTotal += $total;
                    $alertasSuprimidas = $alertasSuprimidas || $supresionTotal !== null;

                    foreach ($areaIds as $areaId) {
                        $filas[] = [
                            'indicador' => $indicador->value,
                            'etiqueta' => $indicador->etiqueta(),
                            'categoria' => $indicador->categoria(),
                            'area_id' => $areaId,
                            'area' => $this->areaNombre($areas, $areaId),
                            'segmento' => $senal,
                            'segmento_etiqueta' => AlertaPreventiva::labelForSignal($senal),
                            ...$celdas[$areaId],
                        ];
                    }
                }

                $totales[$indicador->value] = [
                    'etiqueta' => $indicador->etiqueta(),
                    'categoria' => $indicador->categoria(),
                    'valor' => $alertasTotal < $minimo || $alertasSuprimidas ? null : $alertasTotal,
                    'suprimido' => $alertasTotal < $minimo || $alertasSuprimidas,
                    'supresion' => $alertasTotal < $minimo ? 'primaria' : ($alertasSuprimidas ? 'complementaria' : null),
                    'unidad' => 'conteo',
                ];

                continue;
            }

            $valores = [];
            $areasPrimarias = [];
            $totalAsistidas = 0;
            $totalNoAsistidas = 0;
            foreach ($areaIds as $areaId) {
                $values = $porArea[$areaId];
                $totalAsistidas += $values[TipoIndicador::CitasAsistidas->value];
                $totalNoAsistidas += $values[TipoIndicador::CitasNoAsistidas->value];

                if ($indicador === TipoIndicador::TasaAsistencia) {
                    $denominadorArea = $values[TipoIndicador::CitasAsistidas->value] + $values[TipoIndicador::CitasNoAsistidas->value];
                    $valores[$areaId] = $denominadorArea === 0
                        ? 0.0
                        : round($values[TipoIndicador::CitasAsistidas->value] * 100 / $denominadorArea, 1);

                    if ($denominadorArea < $minimo
                        || $values[TipoIndicador::CitasAsistidas->value] < $minimo
                        || $values[TipoIndicador::CitasNoAsistidas->value] < $minimo) {
                        $areasPrimarias[] = $areaId;
                    }

                    $hayDatos = $hayDatos || $denominadorArea > 0;

                    continue;
                }

                $value = $values[$indicador->value];
                $valores[$areaId] = $value;
                $hayDatos = $hayDatos || $value > 0;
            }

            if ($indicador === TipoIndicador::TasaAsistencia) {
                $denominador = $totalAsistidas + $totalNoAsistidas;
                $total = $denominador === 0 ? 0.0 : round($totalAsistidas * 100 / $denominador, 1);
                $totalPrimario = $denominador < $minimo
                    || $totalAsistidas < $minimo
                    || $totalNoAsistidas < $minimo;
                [$celdas, $supresionTotal] = $this->suprimirCeldas($valores, $minimo, $areasPrimarias, $totalPrimario);
                $unidad = 'porcentaje';
            } else {
                $total = $indicador === TipoIndicador::PacientesAtendidos ? $pacientesTotal : array_sum($valores);
                [$celdas, $supresionTotal] = $this->suprimirCeldas(
                    $valores,
                    $minimo,
                    totalPrimario: $total < $minimo,
                );
                $unidad = 'conteo';
            }

            foreach ($areaIds as $areaId) {
                $filas[] = [
                    'indicador' => $indicador->value,
                    'etiqueta' => $indicador->etiqueta(),
                    'categoria' => $indicador->categoria(),
                    'area_id' => $areaId,
                    'area' => $this->areaNombre($areas, $areaId),
                    'segmento' => null,
                    ...$celdas[$areaId],
                ];
            }

            $totales[$indicador->value] = [
                'etiqueta' => $indicador->etiqueta(),
                'categoria' => $indicador->categoria(),
                'valor' => $supresionTotal === null ? $total : null,
                'suprimido' => $supresionTotal !== null,
                'supresion' => $supresionTotal,
                'unidad' => $unidad,
            ];
        }

        if (! $hayDatos) {
            foreach ($totales as &$total) {
                $total['valor'] = 0;
                $total['suprimido'] = false;
                $total['supresion'] = null;
            }
            unset($total);

            foreach ($filas as &$fila) {
                $fila['valor'] = 0;
                $fila['suprimido'] = false;
                $fila['supresion'] = null;
            }
            unset($fila);
        }

        return new ResultadoIndicadores(
            filtros: [
                'desde' => $filtros->desde,
                'hasta' => $filtros->hasta,
                'area_id' => $filtros->areaId,
                'indicadores' => $filtros->indicadores,
            ],
            areas: $areas,
            areasDisponibles: $areasDisponibles,
            totales: $totales,
            filas: $filas,
            sinDatos: ! $hayDatos,
            minimoCelda: $minimo,
        );
    }

    /** @param array<int, array<string, mixed>> $porArea
     * @param  list<int>  $areaIds
     */
    private function consultasPorArea(array &$porArea, array $areaIds, string $inicio, string $fin): int
    {
        $rows = DB::table('consultas as consulta')
            ->join('expedientes as expediente', 'expediente.id', '=', 'consulta.expediente_id')
            ->whereIn('expediente.area_id', $areaIds)
            ->whereNull('consulta.deleted_at')
            ->whereNull('expediente.deleted_at')
            ->whereBetween('consulta.fecha_consulta', [$inicio, $fin])
            ->selectRaw('expediente.area_id, GROUPING(expediente.area_id) AS global_row, COUNT(*) AS total, COUNT(DISTINCT expediente.paciente_id) AS pacientes')
            ->groupByRaw('GROUPING SETS ((expediente.area_id), ())')
            ->get();
        $pacientesTotal = 0;

        foreach ($rows as $row) {
            if ((int) $row->global_row === 1) {
                $pacientesTotal = (int) $row->pacientes;

                continue;
            }

            $areaId = (int) $row->area_id;
            $porArea[$areaId][TipoIndicador::ConsultasRegistradas->value] = (int) $row->total;
            $porArea[$areaId][TipoIndicador::PacientesAtendidos->value] = (int) $row->pacientes;
        }

        return $pacientesTotal;
    }

    /** @param array<int, array<string, mixed>> $porArea
     * @param  list<int>  $areaIds
     */
    private function expedientesPorArea(array &$porArea, array $areaIds, string $inicio, string $fin): void
    {
        $rows = DB::table('expedientes')
            ->whereIn('area_id', $areaIds)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($inicio, $fin): void {
                $query->where(function ($query) use ($inicio, $fin): void {
                    $query->whereIn('estado', [Expediente::ESTADO_ABIERTO, Expediente::ESTADO_EN_ATENCION])
                        ->where(function ($query) use ($inicio, $fin): void {
                            $query->whereBetween('fecha_derivacion', [$inicio, $fin])
                                ->orWhere(function ($query) use ($inicio, $fin): void {
                                    $query->whereNull('fecha_derivacion')
                                        ->whereBetween('created_at', [$inicio, $fin]);
                                });
                        });
                })->orWhere(function ($query) use ($inicio, $fin): void {
                    $query->where('estado', Expediente::ESTADO_CERRADO)
                        ->whereNotNull('fecha_cierre')
                        ->whereBetween('fecha_cierre', [$inicio, $fin]);
                });
            })
            ->selectRaw('area_id, SUM(CASE WHEN estado IN (?, ?) THEN 1 ELSE 0 END) AS abiertos, SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) AS cerrados', [
                Expediente::ESTADO_ABIERTO,
                Expediente::ESTADO_EN_ATENCION,
                Expediente::ESTADO_CERRADO,
            ])
            ->groupBy('area_id')
            ->get();

        foreach ($rows as $row) {
            $areaId = (int) $row->area_id;
            $porArea[$areaId][TipoIndicador::ExpedientesAbiertos->value] = (int) $row->abiertos;
            $porArea[$areaId][TipoIndicador::ExpedientesCerrados->value] = (int) $row->cerrados;
        }
    }

    /** @param array<int, array<string, mixed>> $porArea
     * @param  list<int>  $areaIds
     */
    private function citasPorArea(array &$porArea, array $areaIds, string $inicio, string $fin): void
    {
        $rows = DB::table('citas')
            ->whereIn('area_id', $areaIds)
            ->whereNull('deleted_at')
            ->whereBetween('fecha_hora', [$inicio, $fin])
            ->select('area_id', 'estado')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('area_id', 'estado')
            ->get();
        $indicadoresPorEstado = [
            EstadoCita::Programada->value => TipoIndicador::CitasProgramadas->value,
            EstadoCita::Asistida->value => TipoIndicador::CitasAsistidas->value,
            EstadoCita::Ausente->value => TipoIndicador::CitasNoAsistidas->value,
            EstadoCita::Cancelada->value => TipoIndicador::CitasCanceladas->value,
            EstadoCita::Reprogramada->value => TipoIndicador::CitasReprogramadas->value,
        ];

        foreach ($rows as $row) {
            $indicador = $indicadoresPorEstado[$row->estado] ?? null;
            if ($indicador !== null) {
                $porArea[(int) $row->area_id][$indicador] = (int) $row->total;
            }
        }
    }

    /** @param array<int, array<string, mixed>> $porArea
     * @param  list<int>  $areaIds
     */
    private function alertasPorArea(array &$porArea, array $areaIds, string $inicio, string $fin): void
    {
        $rows = DB::table('alertas_preventivas')
            ->whereIn('area_id', $areaIds)
            ->whereBetween('generada_en', [$inicio, $fin])
            ->select('area_id', 'senal')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('area_id', 'senal')
            ->get();

        foreach ($rows as $row) {
            if (in_array($row->senal, AlertaPreventiva::SENALES, true)) {
                $porArea[(int) $row->area_id][TipoIndicador::AlertasGeneradas->value][$row->senal] = (int) $row->total;
            }
        }
    }

    /** @param array<int, array<string, mixed>> $porArea
     * @param  list<int>  $areaIds
     */
    private function seguimientosPorArea(array &$porArea, array $areaIds, string $inicio, string $fin): void
    {
        $rows = DB::table('seguimientos_preventivos')
            ->whereIn('area_id', $areaIds)
            ->whereBetween('created_at', [$inicio, $fin])
            ->select('area_id')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('area_id')
            ->get();

        foreach ($rows as $row) {
            $porArea[(int) $row->area_id][TipoIndicador::SeguimientosRegistrados->value] = (int) $row->total;
        }
    }

    /** @param array<int, int|float> $valores
     * @param  list<int>|null  $areasPrimarias
     * @return array{0: array<int, array{valor: int|float|null, suprimido: bool, supresion: string|null}>, 1: string|null}
     */
    private function suprimirCeldas(
        array $valores,
        int $minimo,
        ?array $areasPrimarias = null,
        bool $totalPrimario = false,
    ): array {
        $areasPrimarias ??= array_keys(array_filter($valores, static fn (int|float $valor): bool => $valor < $minimo));
        $areasComplementarias = [];

        if (count($areasPrimarias) === 1 && count($valores) > 1) {
            $candidatas = array_diff_key($valores, array_fill_keys($areasPrimarias, true));
            if ($candidatas !== []) {
                asort($candidatas);
                $areasComplementarias[] = (int) array_key_first($candidatas);
            }
        }

        $celdas = [];
        foreach ($valores as $areaId => $valor) {
            $supresion = in_array($areaId, $areasPrimarias, true)
                ? 'primaria'
                : (in_array($areaId, $areasComplementarias, true) ? 'complementaria' : null);
            $celdas[$areaId] = [
                'valor' => $supresion === null ? $valor : null,
                'suprimido' => $supresion !== null,
                'supresion' => $supresion,
            ];
        }

        $totalSupresion = $totalPrimario
            ? 'primaria'
            : (($areasPrimarias !== [] || $areasComplementarias !== []) ? 'complementaria' : null);

        return [$celdas, $totalSupresion];
    }

    /** @param list<array{id: int, nombre: string}> $areas */
    private function areaNombre(array $areas, int $areaId): string
    {
        foreach ($areas as $area) {
            if ($area['id'] === $areaId) {
                return $area['nombre'];
            }
        }

        return 'Área';
    }
}
