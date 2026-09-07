<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use App\Models\Cita;
use App\Http\Requests\CitaStoreRequest;
use App\Http\Requests\CitaAsistenciaRequest;
use App\Http\Requests\CitaReprogramarRequest;
use App\Http\Requests\CitaCancelarRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CitaController extends Controller
{
    public function store(CitaStoreRequest $request, Expediente $expediente): RedirectResponse
    {
        $this->authorize('create', [Cita::class, $expediente]);

        try {
            DB::transaction(function () use ($request, $expediente) {
                $cita = new Cita();
                $cita->expediente_id = $expediente->id;
                $cita->profesional_id = $request->user()->profesional->id;
                $cita->area_id = $expediente->area_id;
                $cita->fecha_hora = $request->validated('fecha_hora');
                $cita->motivo = $request->validated('motivo');
                $cita->estado = 'programada';
                $cita->save();
                
                // Actividad registrada automáticamente vía Auditable
            });

            return redirect()->back()->with('success', 'Cita agendada exitosamente.');
        } catch (UniqueConstraintViolationException $e) {
            return redirect()->back()->withErrors([
                'fecha_hora' => 'El horario seleccionado ya no está disponible o existe un conflicto en la agenda del especialista.'
            ])->withInput();
        }
    }

    public function actualizarAsistencia(CitaAsistenciaRequest $request, Expediente $expediente, Cita $cita): RedirectResponse
    {
        $this->authorize('update', $cita);

        $cita->registrarAsistencia(
            $request->validated('estado'),
            $request->user()->profesional->id
        );

        return redirect()->back()
            ->with('message', 'Asistencia registrada exitosamente.')
            ->with('variant', 'success');
    }

    public function reprogramar(CitaReprogramarRequest $request, Expediente $expediente, Cita $cita): RedirectResponse
    {
        $this->authorize('reprogramar', $cita);

        try {
            DB::transaction(function () use ($request, $expediente, $cita) {
                // Crear la nueva cita clonando el motivo
                $nuevaCita = new Cita();
                $nuevaCita->expediente_id = $expediente->id;
                $nuevaCita->profesional_id = $cita->profesional_id;
                $nuevaCita->area_id = $cita->area_id;
                $nuevaCita->fecha_hora = $request->validated('fecha_hora');
                $nuevaCita->motivo = $cita->motivo; // Se copia automáticamente
                $nuevaCita->estado = 'programada';
                $nuevaCita->cita_origen_id = $cita->id;
                $nuevaCita->save();

                // Actualizar la cita original
                $cita->estado = 'reprogramada';
                $cita->save();
            });

            return redirect()->back()->with('success', 'Cita reprogramada exitosamente.');
        } catch (UniqueConstraintViolationException $e) {
            return redirect()->back()->withErrors([
                'fecha_hora' => 'El horario seleccionado ya no está disponible o existe un conflicto en la agenda del especialista.'
            ])->withInput();
        }
    }

    public function cancelar(CitaCancelarRequest $request, Expediente $expediente, Cita $cita): RedirectResponse
    {
        $this->authorize('cancelar', $cita);

        $cita->estado = 'cancelada';
        $cita->motivo_cancelacion = $request->validated('motivo_cancelacion');
        $cita->save();

        return redirect()->back()->with('success', 'Cita cancelada exitosamente.');
    }
}
