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

        return Inertia::render('Consultas/Create', [
            'expediente' => $expediente->load('paciente'),
        ]);
    }

    public function store(ConsultaStoreRequest $request, Expediente $expediente): RedirectResponse
    {
        Gate::authorize('create', [\App\Models\Consulta::class, $expediente]);

        $profesionalId = $request->user()->profesional->id;

        $this->consultaService->registrarConsulta(
            $expediente,
            $request->validated(),
            $profesionalId
        );

        return redirect()->route('pacientes.show', $expediente->paciente->carnet)
            ->with('message', 'Consulta registrada exitosamente.')
            ->with('variant', 'success');
    }
}
