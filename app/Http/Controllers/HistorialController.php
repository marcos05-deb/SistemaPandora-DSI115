<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\HistorialFilterRequest;
use App\Http\Resources\HistorialResource;
use App\Models\Paciente;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HistorialController extends Controller
{
    /**
     * Display the multidisciplinary history of the specified patient.
     *
     * Orden cronológico: más reciente primero (descendente por fecha_consulta).
     */
    public function show(HistorialFilterRequest $request, Paciente $paciente): Response
    {
        Gate::authorize('view', $paciente);

        $filtros = $request->validated();
        $user = $request->user();

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

        // Hoy el historial solo contiene consultas clínicas.
        if (! empty($filtros['tipo_atencion']) && $filtros['tipo_atencion'] !== 'consulta') {
            $query->whereRaw('1 = 0');
        }

        $consultas = $query->get();
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
            'historial' => HistorialResource::collection($consultas),
            'filtros' => [
                'fecha_desde' => $filtros['fecha_desde'] ?? null,
                'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
                'area_id' => $filtros['area_id'] ?? null,
                'tipo_atencion' => $filtros['tipo_atencion'] ?? null,
            ],
            'areasAutorizadas' => $areasAutorizadas,
            'tiposAtencion' => [
                ['value' => 'consulta', 'label' => 'Consulta clínica'],
            ],
            'historialVacio' => $consultas->isEmpty() && $sinFiltros,
            'filtrosSinResultados' => $consultas->isEmpty() && ! $sinFiltros,
            'orden' => 'desc',
        ]);
    }
}
