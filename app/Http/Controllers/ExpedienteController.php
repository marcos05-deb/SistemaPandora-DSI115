<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoCita;
use App\Http\Requests\ExpedienteCloseRequest;
use App\Http\Requests\ExpedienteUpdateRequest;
use App\Models\Cita;
use App\Models\Expediente;
use App\Services\ExpedienteActualizacionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ExpedienteController extends Controller
{
    /**
     * Actualizar campos clínicos permitidos del expediente (HU-10).
     * Primera actualización libre; las siguientes requieren permiso admin consumible.
     */
    public function update(
        ExpedienteUpdateRequest $request,
        Expediente $expediente,
        ExpedienteActualizacionService $service
    ) {
        Gate::authorize('update', $expediente);

        $service->actualizar($expediente, $request->user(), $request->validated());

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

        $profesional = $request->user()->profesionalParaArea($expediente->area_id);
        abort_unless($profesional, 403);

        $citasCanceladas = 0;

        DB::transaction(function () use ($request, $expediente, $profesional, &$citasCanceladas) {
            $expediente->update([
                'estado' => Expediente::ESTADO_CERRADO,
                'resultado_final' => $request->validated('resultado_final'),
                'motivo_cierre' => $request->validated('motivo_cierre'),
                'fecha_cierre' => now(),
                'cerrado_por_profesional_id' => $profesional->id,
            ]);

            // PAN-17: no dejar citas futuras programadas/editables tras el cierre.
            $citasFuturas = Cita::query()
                ->where('expediente_id', $expediente->id)
                ->where('estado', EstadoCita::Programada->value)
                ->where('fecha_hora', '>', now())
                ->lockForUpdate()
                ->get();

            foreach ($citasFuturas as $cita) {
                $cita->estado = EstadoCita::Cancelada->value;
                $cita->motivo_cancelacion = 'Cancelada automáticamente por cierre del expediente clínico.';
                $cita->cancelado_por_profesional_id = $profesional->id;
                $cita->fecha_cancelacion = now();
                $cita->save();
                $citasCanceladas++;
            }

            $expediente->paciente->update(['ultima_accion' => 'Cierre de expediente clínico']);
        });

        $mensaje = $citasCanceladas > 0
            ? "Expediente cerrado exitosamente. Se cancelaron {$citasCanceladas} cita(s) futura(s)."
            : 'Expediente cerrado exitosamente.';

        return redirect()->back()
            ->with('message', $mensaje)
            ->with('variant', 'success');
    }
}
