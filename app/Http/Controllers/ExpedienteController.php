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

        $expediente->update($payload);

        $ultimoAudit = $expediente->audits()->latest('id')->first();
        if ($ultimoAudit) {
            $newValues = $ultimoAudit->new_values ?? [];
            $newValues['motivo_cambio'] = $motivoCambio;

            // Bypass Eloquent: los audits son inmutables a nivel de modelo (NH-04).
            \Illuminate\Support\Facades\DB::table(config('audit.drivers.database.table', 'audits'))
                ->where('id', $ultimoAudit->id)
                ->update([
                    'new_values' => json_encode($newValues),
                    'updated_at' => now(),
                ]);
        }

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
