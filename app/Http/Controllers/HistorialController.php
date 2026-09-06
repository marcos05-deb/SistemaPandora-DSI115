<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\HistorialResource;
use App\Models\Paciente;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HistorialController extends Controller
{
    /**
     * Display the multidisciplinary history of the specified patient.
     */
    public function show(Paciente $paciente): Response
    {
        // Authorize viewing the patient. 
        // This generally ensures the user has some relationship/access to the patient,
        // although the AreaScope on Expediente will filter the actual history items.
        Gate::authorize('view', $paciente);

        // Fetch history utilizing the relationship. 
        // The AreaScope on Expediente will automatically filter out Consultas 
        // belonging to areas the user doesn't have access to.
        $consultas = $paciente->historialMultidisciplinario()
            ->with(['expediente.area', 'profesional.especialista'])
            ->get();

        return Inertia::render('Historial/Show', [
            'paciente' => [
                'codigo' => $paciente->codigo,
                'carnet' => $paciente->carnet,
                'nombre_completo' => $paciente->nombre_completo,
            ],
            'historial' => HistorialResource::collection($consultas),
        ]);
    }
}
