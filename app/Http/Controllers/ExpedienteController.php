<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpedienteCloseRequest;
use App\Models\Expediente;
use Illuminate\Support\Facades\Gate;

class ExpedienteController extends Controller
{
    /**
     * Close the specified Expediente.
     */
    public function close(ExpedienteCloseRequest $request, Expediente $expediente)
    {
        Gate::authorize('close', $expediente);

        $expediente->update([
            'estado' => 'cerrado',
            'motivo_cierre' => $request->motivo_cierre,
            'fecha_cierre' => now(),
            'cerrado_por_profesional_id' => $request->user()->profesional->id,
        ]);

        $expediente->paciente->update(['ultima_accion' => 'Cierre de expediente clínico']);

        return redirect()->back()
            ->with('message', 'Expediente cerrado exitosamente.')
            ->with('variant', 'success');
    }
}
