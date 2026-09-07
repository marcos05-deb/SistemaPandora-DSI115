<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use App\Models\Cita;
use App\Http\Requests\CitaStoreRequest;
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
}
