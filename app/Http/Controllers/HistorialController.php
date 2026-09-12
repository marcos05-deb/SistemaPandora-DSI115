<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\HistorialFilterRequest;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Scopes\AreaScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HistorialController extends Controller
{
    /**
     * Historial multidisciplinario: consultas, derivaciones y cierres.
     * Orden: más reciente primero.
     */
    public function show(HistorialFilterRequest $request, Paciente $paciente): Response
    {
        Gate::authorize('view', $paciente);

        $filtros = $request->validated();
        $user = $request->user();
        $tipo = $filtros['tipo_atencion'] ?? null;

        $eventos = collect();

        if ($tipo === null || $tipo === 'consulta') {
            $query = $paciente->historialMultidisciplinario()
                ->with(['expediente.area', 'profesional.especialista']);

            if (! empty($filtros['fecha_desde'])) {
                $query->whereDate('fecha_consulta', '>=', $filtros['fecha_desde']);
            }

            if (! empty($filtros['fecha_hasta'])) {
                $query->whereDate('fecha_consulta', '<=', $filtros['fecha_hasta']);
            }

            if (! empty($filtros['area_id'])) {
                $query->whereHas('expediente', fn ($q) => $q->where('area_id', $filtros['area_id']));
            }

            foreach ($query->get() as $consulta) {
                $eventos->push([
                    'id' => $consulta->id,
                    'fecha_consulta' => $consulta->fecha_consulta->format('Y-m-d H:i:s'),
                    'motivo_consulta' => $consulta->motivo_consulta,
                    'notas_clinicas' => $consulta->notas_clinicas,
                    'diagnostico' => $consulta->diagnostico,
                    'plan_atencion' => $consulta->plan_atencion,
                    'evaluacion_inicial' => $consulta->evaluacion_inicial,
                    'tecnica_utilizada' => $consulta->tecnica_utilizada,
                    'tipo_atencion' => 'consulta',
                    'profesional' => [
                        'id' => $consulta->profesional->id,
                        'nombre' => $consulta->profesional->especialista->name ?? 'Profesional Desconocido',
                    ],
                    'area' => [
                        'id' => $consulta->expediente->area->id,
                        'nombre' => $consulta->expediente->area->nombre,
                        'color' => $consulta->expediente->area->color ?? 'bg-nord-4',
                    ],
                ]);
            }
        }

        if ($tipo === null || $tipo === 'derivacion' || $tipo === 'cierre') {
            $expedientesQuery = Expediente::query()
                ->where('paciente_id', $paciente->codigo)
                ->with(['area', 'derivadoPor.especialista', 'cerradoPor.especialista']);

            // El referente ve todos los expedientes del paciente; clínico queda bajo AreaScope.
            if ($user->hasRole('psychosocial_referent') && ! $user->hasRole('specialist') && ! $user->hasRole('area_coordinator')) {
                $expedientesQuery->withoutGlobalScope(AreaScope::class);
            }

            if (! empty($filtros['area_id'])) {
                $expedientesQuery->where('area_id', $filtros['area_id']);
            }

            foreach ($expedientesQuery->get() as $expediente) {
                if (($tipo === null || $tipo === 'derivacion')
                    && $expediente->fecha_derivacion
                    && filled($expediente->motivo_derivacion)
                ) {
                    $fecha = $expediente->fecha_derivacion;
                    if ($this->fechaEnRango($fecha, $filtros)) {
                        $eventos->push([
                            'id' => 'derivacion-'.$expediente->id,
                            'fecha_consulta' => $fecha->format('Y-m-d H:i:s'),
                            'motivo_consulta' => $expediente->motivo_derivacion,
                            'notas_clinicas' => null,
                            'diagnostico' => null,
                            'plan_atencion' => null,
                            'evaluacion_inicial' => null,
                            'tecnica_utilizada' => null,
                            'tipo_atencion' => 'derivacion',
                            'profesional' => [
                                'id' => $expediente->derivado_por_profesional_id,
                                'nombre' => $expediente->derivadoPor?->especialista?->name ?? 'Referente',
                            ],
                            'area' => [
                                'id' => $expediente->area->id,
                                'nombre' => $expediente->area->nombre,
                                'color' => $expediente->area->color ?? 'bg-nord-4',
                            ],
                        ]);
                    }
                }

                if (($tipo === null || $tipo === 'cierre')
                    && $expediente->estado === Expediente::ESTADO_CERRADO
                    && $expediente->fecha_cierre
                    && (filled($expediente->resultado_final) || filled($expediente->motivo_cierre))
                ) {
                    $fecha = $expediente->fecha_cierre;
                    if ($this->fechaEnRango($fecha, $filtros)) {
                        $eventos->push([
                            'id' => 'cierre-'.$expediente->id,
                            'fecha_consulta' => $fecha->format('Y-m-d H:i:s'),
                            'motivo_consulta' => $expediente->resultado_final ?: $expediente->motivo_cierre,
                            'notas_clinicas' => $expediente->motivo_cierre,
                            'diagnostico' => null,
                            'plan_atencion' => null,
                            'evaluacion_inicial' => null,
                            'tecnica_utilizada' => null,
                            'tipo_atencion' => 'cierre',
                            'resultado_final' => $expediente->resultado_final,
                            'profesional' => [
                                'id' => $expediente->cerrado_por_profesional_id,
                                'nombre' => $expediente->cerradoPor?->especialista?->name ?? 'Especialista',
                            ],
                            'area' => [
                                'id' => $expediente->area->id,
                                'nombre' => $expediente->area->nombre,
                                'color' => $expediente->area->color ?? 'bg-nord-4',
                            ],
                        ]);
                    }
                }
            }
        }

        $ordenados = $eventos->sortByDesc('fecha_consulta')->values();
        $sinFiltros = empty(array_filter($filtros));

        $areasAutorizadas = $user->areas()
            ->get(['areas.id', 'areas.nombre'])
            ->unique('id')
            ->values()
            ->map(fn ($area) => [
                'id' => $area->id,
                'nombre' => $area->nombre,
            ]);

        if ($areasAutorizadas->isEmpty() && $user->profesional?->area) {
            $areasAutorizadas = collect([[
                'id' => $user->profesional->area->id,
                'nombre' => $user->profesional->area->nombre,
            ]]);
        }

        return Inertia::render('Historial/Show', [
            'paciente' => [
                'codigo' => $paciente->codigo,
                'carnet' => $paciente->carnet,
                'nombre_completo' => $paciente->nombre_completo,
            ],
            'historial' => [
                'data' => $ordenados->all(),
            ],
            'filtros' => [
                'fecha_desde' => $filtros['fecha_desde'] ?? null,
                'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
                'area_id' => $filtros['area_id'] ?? null,
                'tipo_atencion' => $filtros['tipo_atencion'] ?? null,
            ],
            'areasAutorizadas' => $areasAutorizadas,
            'tiposAtencion' => [
                ['value' => 'consulta', 'label' => 'Consulta clínica'],
                ['value' => 'derivacion', 'label' => 'Derivación'],
                ['value' => 'cierre', 'label' => 'Cierre de expediente'],
            ],
            'historialVacio' => $ordenados->isEmpty() && $sinFiltros,
            'filtrosSinResultados' => $ordenados->isEmpty() && ! $sinFiltros,
            'orden' => 'desc',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function fechaEnRango(Carbon $fecha, array $filtros): bool
    {
        if (! empty($filtros['fecha_desde']) && $fecha->toDateString() < $filtros['fecha_desde']) {
            return false;
        }

        if (! empty($filtros['fecha_hasta']) && $fecha->toDateString() > $filtros['fecha_hasta']) {
            return false;
        }

        return true;
    }
}
