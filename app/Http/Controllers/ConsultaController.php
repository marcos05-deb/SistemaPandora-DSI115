<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ConsultaStoreRequest;
use App\Models\Expediente;
use App\Services\ConsultaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConsultaController extends Controller
{
    private ConsultaService $consultaService;

    public function __construct(ConsultaService $consultaService)
    {
        $this->consultaService = $consultaService;
    }

    public function create(Expediente $expediente): Response
    {
        Gate::authorize('create', [\App\Models\Consulta::class, $expediente]);

        $isFirstConsulta = !$expediente->consultas()->exists();

        return Inertia::render('Consultas/Create', [
            'expediente' => [
                'id' => $expediente->id,
                'paciente' => [
                    'carnet' => $expediente->paciente->carnet,
                    'nombre_completo' => $expediente->paciente->nombre_completo,
                ]
            ],
            'esPrimeraConsulta' => $isFirstConsulta
        ]);
    }

    public function store(ConsultaStoreRequest $request, Expediente $expediente): RedirectResponse
    {
        Gate::authorize('create', [\App\Models\Consulta::class, $expediente]);

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
