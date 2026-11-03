<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreSolicitudCorreccionRequest;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Services\SolicitudCorreccionExpedienteService;
use Illuminate\Http\RedirectResponse;

class SolicitudCorreccionController extends Controller
{
    public function storeDatos(
        StoreSolicitudCorreccionRequest $request,
        Paciente $paciente,
        SolicitudCorreccionExpedienteService $service
    ): RedirectResponse {
        $service->solicitarDatos(
            $paciente,
            $request->user(),
            $request->validated('campos'),
            $request->validated('motivo')
        );

        return redirect()->back()
            ->with('message', 'Solicitud enviada. Administración debe aprobarla antes de que puedas volver a corregir estos datos.')
            ->with('variant', 'success');
    }

    public function storeClinico(
        StoreSolicitudCorreccionRequest $request,
        Expediente $expediente,
        SolicitudCorreccionExpedienteService $service
    ): RedirectResponse {
        $service->solicitarClinico(
            $expediente,
            $request->user(),
            $request->validated('campos'),
            $request->validated('motivo')
        );

        return redirect()->back()
            ->with('message', 'Solicitud enviada. Administración debe aprobarla antes de que puedas volver a actualizar este expediente.')
            ->with('variant', 'success');
    }
}
