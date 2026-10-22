<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ConsultaStoreRequest;
use App\Models\Consulta;
use App\Models\Expediente;
use App\Services\ConsultaService;
use App\Services\RubricaResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConsultaController extends Controller
{
    private ConsultaService $consultaService;

    private RubricaResolver $rubricaResolver;

    public function __construct(ConsultaService $consultaService, RubricaResolver $rubricaResolver)
    {
        $this->consultaService = $consultaService;
        $this->rubricaResolver = $rubricaResolver;
    }

    public function create(Expediente $expediente): Response
    {
        Gate::authorize('create', [Consulta::class, $expediente]);

        $isFirstConsulta = ! $expediente->consultas()->exists();
        $effectiveVersion = $this->rubricaResolver->vigente();
        $trendIndicators = $effectiveVersion?->variables
            ->where('tipo_senal', 'tendencia_suicida')
            ->pluck('clave_indicador')
            ->filter(static fn (mixed $key): bool => is_string($key) && $key !== '')
            ->unique()
            ->sort()
            ->values()
            ->all() ?? [];

        return Inertia::render('Consultas/Create', [
            'expediente' => [
                'id' => $expediente->id,
                'paciente' => [
                    'carnet' => $expediente->paciente->carnet,
                    'nombre_completo' => $expediente->paciente->nombre_completo,
                ],
            ],
            'esPrimeraConsulta' => $isFirstConsulta,
            'indicadoresTendenciaSuicida' => $trendIndicators,
        ]);
    }

    public function store(ConsultaStoreRequest $request, Expediente $expediente): RedirectResponse
    {
        Gate::authorize('create', [Consulta::class, $expediente]);

        $profesional = $request->user()->profesionalParaArea($expediente->area_id);
        abort_unless($profesional, 403);

        $consulta = $this->consultaService->registrarConsulta(
            $expediente,
            $request->validated(),
            $profesional->id
        );

        $expediente->paciente->update(['ultima_accion' => 'Registro de consulta clínica']);

        return redirect()->route('pacientes.show', $expediente->paciente->carnet)
            ->with('message', 'Consulta registrada exitosamente.')
            ->with('variant', 'success')
            ->with('prompt_cita_expediente_id', $expediente->id)
            ->with('prompt_cita_consulta_id', $consulta->id);
    }
}
