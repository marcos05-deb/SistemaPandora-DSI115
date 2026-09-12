<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ExpedienteCloseRequest;
use App\Http\Requests\ExpedienteUpdateRequest;
use App\Models\Expediente;
use Illuminate\Support\Facades\Gate;

class ExpedienteController extends Controller
{
    /**
     * Actualizar campos clínicos permitidos del expediente (HU-10).
     */
    public function update(ExpedienteUpdateRequest $request, Expediente $expediente)
    {
        Gate::authorize('update', $expediente);

        $validated = $request->validated();
        $motivoCambio = $validated['motivo_cambio'];
        unset($validated['motivo_cambio']);

        $payload = [];
        foreach (['motivo_consulta', 'notas_clinicas', 'diagnostico'] as $campo) {
            if (array_key_exists($campo, $validated)) {
                $payload[$campo] = $validated[$campo];
            }
        }

        // Incluye motivo_cambio en el INSERT original de auditoría (RP-04).
        $expediente->auditMotivoCambio = $motivoCambio;
        $expediente->update($payload);

        $expediente->paciente->update(['ultima_accion' => 'Actualización de expediente clínico']);

        return redirect()->back()
            ->with('message', 'Expediente actualizado correctamente.')
            ->with('variant', 'success');
    }

    /**
     * Close the specified Expediente.
     */
    public function close(ExpedienteCloseRequest $request, Expediente $expediente)
    {
        Gate::authorize('close', $expediente);

        $expediente->update([
            'estado' => Expediente::ESTADO_CERRADO,
            'resultado_final' => $request->validated('resultado_final'),
            'motivo_cierre' => $request->validated('motivo_cierre'),
            'fecha_cierre' => now(),
            'cerrado_por_profesional_id' => $request->user()->profesional->id,
        ]);

        $expediente->paciente->update(['ultima_accion' => 'Cierre de expediente clínico']);

        return redirect()->back()
            ->with('message', 'Expediente cerrado exitosamente.')
            ->with('variant', 'success');
    }
}
