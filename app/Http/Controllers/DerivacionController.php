<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DerivacionStoreRequest;
use App\Models\Area;
use App\Models\Paciente;
use App\Services\DerivacionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DerivacionController extends Controller
{
    private DerivacionService $derivacionService;

    public function __construct(DerivacionService $derivacionService)
    {
        $this->derivacionService = $derivacionService;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Paciente $paciente): Response
    {
        // Enforce policy via middleware or here
        $this->authorize('derivar', \App\Models\Expediente::class);

        $areas = Area::select('id', 'nombre')->orderBy('nombre')->get();

        // Pass patient info to display on the form
        return Inertia::render('Expediente/Derivacion/Create', [
            'paciente' => [
                'codigo' => $paciente->codigo,
                'carnet' => $paciente->carnet,
                'nombre_completo' => $paciente->nombre_completo,
            ],
            'areas' => $areas,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(DerivacionStoreRequest $request, Paciente $paciente): RedirectResponse
    {
        $profesionalId = $request->user()->profesional->id;

        $this->derivacionService->derivarPaciente(
            $paciente,
            (int) $request->validated('area_id'),
            $profesionalId
        );

        return redirect()->route('pacientes.index')
            ->with('success', 'Paciente derivado exitosamente.');
    }
}
