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
     * Store a newly created resource in storage.
     */
    public function store(DerivacionStoreRequest $request, Paciente $paciente): RedirectResponse
    {
        $profesionalId = $request->user()->profesional->id;

        $this->derivacionService->derivarPaciente(
            $paciente,
            (int) $request->validated('area_id'),
            $profesionalId,
            $request->validated('motivo_derivacion'),
        );

        $paciente->update(['ultima_accion' => 'Apertura de expediente']);

        return redirect()->back()
            ->with('success', 'Paciente derivado exitosamente.');
    }
}
