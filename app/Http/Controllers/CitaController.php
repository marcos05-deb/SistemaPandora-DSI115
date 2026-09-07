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
use Illuminate\Http\Request;
use App\Http\Resources\CitaResource;
use Inertia\Inertia;

class CitaController extends Controller
{
    public function index(Request $request)
    {
        Log::info('Start of CitaController@index');
        $user = $request->user();

        if ($user->hasRole('sysadmin')) {
            return redirect()->route('admin.dashboard'); // sysadmin is bounced out
        }

        $validated = $request->validate([
            'especialista_id' => 'nullable|uuid|exists:profesionales,id',
            'estado' => 'nullable|string|in:programada,asistida,ausente,reprogramada,cancelada',
            'fecha' => 'nullable|date',
        ]);

        $query = Cita::with(['expediente.paciente', 'profesional.especialista', 'registradoPor.especialista']);

        // Control de acceso al listado
        if (! $user->hasRole('area_coordinator')) {
            // El especialista ve estrictamente lo suyo
            $query->where('profesional_id', $user->profesional->id);
        } elseif (!empty($validated['especialista_id'])) {
            // El coordinador puede filtrar por especialista (dentro de su área, protegido por AreaScope)
            $query->where('profesional_id', $validated['especialista_id']);
        }

        if (!empty($validated['estado'])) {
            $query->where('estado', $validated['estado']);
        }

        if (!empty($validated['fecha'])) {
            $query->whereDate('fecha_hora', $validated['fecha']);
        }

        $citas = $query->orderBy('fecha_hora', 'desc')->paginate(15);

        Log::info('Citas queried, passing to Inertia Render');

        $especialistas = [];
        if ($user->hasRole('area_coordinator')) {
            $especialistas = \App\Models\Profesional::with('especialista:id,name')
                ->where('area_id', $user->profesional->area_id)
                ->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'nombre' => $p->especialista->name
                ]);
        }

        return Inertia::render('Citas/Index', [
            'citas' => CitaResource::collection($citas),
            'filtros' => $validated,
            'especialistas' => $especialistas,
        ]);
    }
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
