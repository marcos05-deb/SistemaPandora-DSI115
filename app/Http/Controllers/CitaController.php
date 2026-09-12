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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Resources\CitaResource;
use Inertia\Inertia;
use Illuminate\Pagination\LengthAwarePaginator;

class CitaController extends Controller
{
    private const MENSAJE_CONFLICTO_HORARIO = 'El horario seleccionado ya no está disponible o existe un conflicto en la agenda del especialista.';

    /** Límite operativo de citas cargadas en vistas diaria/semanal (RP-09). */
    private const LIMITE_VISTA_AGENDA = 500;

    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('sysadmin')) {
            abort(403);
        }

        $validated = $request->validate([
            'especialista_id' => 'nullable|uuid|exists:profesionales,id',
            'estado' => 'nullable|string|in:programada,asistida,ausente,reprogramada,cancelada,todos',
            'resultado_asistencia' => 'nullable|string|in:asistida,ausente',
            'fecha' => 'nullable|date',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
            'paciente' => 'nullable|string|max:64',
            'vista' => 'nullable|string|in:lista,diaria,semanal',
            'referencia' => 'nullable|date',
        ]);

        $vista = $validated['vista'] ?? 'lista';
        $referencia = isset($validated['referencia'])
            ? \Illuminate\Support\Carbon::parse($validated['referencia'])->startOfDay()
            : now()->startOfDay();

        $estado = $validated['estado'] ?? null;
        if ($estado === 'todos') {
            $estado = null;
        }

        $query = Cita::with(['expediente.paciente', 'profesional.especialista', 'registradoPor.especialista']);

        if (! $user->hasRole('area_coordinator')) {
            $query->where('profesional_id', $user->profesional->id);
        } elseif (! empty($validated['especialista_id'])) {
            $profesionalFiltro = \App\Models\Profesional::query()
                ->whereKey($validated['especialista_id'])
                ->whereIn('area_id', $user->areas()->pluck('areas.id'))
                ->first();

            if ($profesionalFiltro) {
                $query->where('profesional_id', $profesionalFiltro->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (! empty($validated['resultado_asistencia'])) {
            $query->where('estado', $validated['resultado_asistencia']);
        } elseif (! empty($estado)) {
            $query->where('estado', $estado);
        }

        if (! empty($validated['paciente'])) {
            $pacienteTerm = $validated['paciente'];
            $query->whereHas('expediente.paciente', function ($q) use ($pacienteTerm) {
                $q->where('codigo', $pacienteTerm)
                    ->orWhere('carnet', $pacienteTerm);
            });
        }

        if ($vista === 'diaria') {
            $query->whereDate('fecha_hora', $referencia->toDateString());
        } elseif ($vista === 'semanal') {
            $inicio = $referencia->copy()->startOfWeek();
            $fin = $referencia->copy()->endOfWeek();
            $query->whereBetween('fecha_hora', [$inicio, $fin]);
        } else {
            if (! empty($validated['fecha'])) {
                $query->whereDate('fecha_hora', $validated['fecha']);
            }
            if (! empty($validated['fecha_desde'])) {
                $query->whereDate('fecha_hora', '>=', $validated['fecha_desde']);
            }
            if (! empty($validated['fecha_hasta'])) {
                $query->whereDate('fecha_hora', '<=', $validated['fecha_hasta']);
            }
        }

        // NH-06 / RP-09: cargan el rango completo con tope explícito y aviso si se trunca.
        $semanaTruncada = false;
        if ($vista === 'diaria' || $vista === 'semanal') {
            $totalRango = (clone $query)->count();
            $items = $query->orderBy('fecha_hora', 'asc')->limit(self::LIMITE_VISTA_AGENDA)->get();
            $semanaTruncada = $totalRango > $items->count();
            $citas = new LengthAwarePaginator(
                $items,
                $items->count(),
                max($items->count(), 1),
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $citas = $query->orderBy('fecha_hora', 'desc')->paginate(30)->withQueryString();
        }

        $especialistas = [];
        if ($user->hasRole('area_coordinator')) {
            $especialistas = \App\Models\Profesional::with('especialista:id,name')
                ->whereIn('area_id', $user->areas()->pluck('areas.id'))
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'nombre' => $p->especialista->name,
                ]);
        }

        $agrupadas = null;
        if ($vista === 'semanal') {
            $agrupadas = collect($citas->items())
                ->groupBy(fn ($cita) => $cita->fecha_hora->toDateString())
                ->map(fn ($grupo, $dia) => [
                    'fecha' => $dia,
                    'citas' => CitaResource::collection($grupo)->resolve(),
                ])
                ->values();
        }

        return Inertia::render('Citas/Index', [
            'citas' => CitaResource::collection($citas),
            'citasPorDia' => $agrupadas,
            'filtros' => [
                'especialista_id' => $validated['especialista_id'] ?? null,
                'estado' => $validated['estado'] ?? 'todos',
                'resultado_asistencia' => $validated['resultado_asistencia'] ?? null,
                'fecha' => $validated['fecha'] ?? null,
                'fecha_desde' => $validated['fecha_desde'] ?? null,
                'fecha_hasta' => $validated['fecha_hasta'] ?? null,
                'paciente' => $validated['paciente'] ?? null,
                'vista' => $vista,
                'referencia' => $referencia->toDateString(),
            ],
            'especialistas' => $especialistas,
            'agendaMeta' => [
                'limite' => self::LIMITE_VISTA_AGENDA,
                'truncada' => $semanaTruncada,
            ],
            'navegacion' => [
                'anterior' => $vista === 'semanal'
                    ? $referencia->copy()->subWeek()->toDateString()
                    : $referencia->copy()->subDay()->toDateString(),
                'siguiente' => $vista === 'semanal'
                    ? $referencia->copy()->addWeek()->toDateString()
                    : $referencia->copy()->addDay()->toDateString(),
                'hoy' => now()->toDateString(),
            ],
        ]);
    }

    public function store(CitaStoreRequest $request, Expediente $expediente): RedirectResponse
    {
        $this->authorize('create', [Cita::class, $expediente]);

        try {
            DB::transaction(function () use ($request, $expediente) {
                $fechaHora = \Illuminate\Support\Carbon::parse($request->validated('fecha_hora'));
                $profesionalId = $request->user()->profesional->id;

                $this->bloquearAgendaProfesional($profesionalId);

                if (Cita::hayConflictoHorario($profesionalId, $fechaHora)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'fecha_hora' => 'El horario seleccionado se solapa con otra cita programada del especialista.',
                    ]);
                }

                $cita = new Cita();
                $cita->expediente_id = $expediente->id;
                $cita->consulta_id = $request->validated('consulta_id');
                $cita->profesional_id = $profesionalId;
                $cita->area_id = $expediente->area_id;
                $cita->fecha_hora = $fechaHora;
                $cita->motivo = $request->validated('motivo');
                $cita->estado = 'programada';
                $cita->save();
            });

            return redirect()->back()->with('success', 'Cita agendada exitosamente.');
        } catch (UniqueConstraintViolationException $e) {
            return redirect()->back()->withErrors([
                'fecha_hora' => self::MENSAJE_CONFLICTO_HORARIO,
            ])->withInput();
        } catch (QueryException $e) {
            if ($this->esViolacionExclusionAgenda($e)) {
                return redirect()->back()->withErrors([
                    'fecha_hora' => self::MENSAJE_CONFLICTO_HORARIO,
                ])->withInput();
            }

            throw $e;
        }
    }

    public function actualizarAsistencia(CitaAsistenciaRequest $request, Expediente $expediente, Cita $cita): RedirectResponse
    {
        $this->asegurarCitaDelExpediente($expediente, $cita);
        $this->authorize('update', $cita);

        $cita->registrarAsistencia(
            $request->validated('estado'),
            $request->user()->profesional->id
        );

        $etiqueta = $request->validated('estado') === \App\Enums\EstadoCita::Asistida->value
            ? 'Asistió'
            : 'Ausente';

        return redirect()->back()
            ->with('message', "Asistencia registrada: {$etiqueta}.")
            ->with('variant', 'success');
    }

    public function reprogramar(CitaReprogramarRequest $request, Expediente $expediente, Cita $cita): RedirectResponse
    {
        $this->asegurarCitaDelExpediente($expediente, $cita);
        $this->authorize('reprogramar', $cita);

        try {
            DB::transaction(function () use ($request, $cita) {
                /** @var Cita $citaBloqueada */
                $citaBloqueada = Cita::query()
                    ->whereKey($cita->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->bloquearAgendaProfesional($citaBloqueada->profesional_id);

                if ($citaBloqueada->estado !== \App\Enums\EstadoCita::Programada->value) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'estado' => 'Solo se pueden reprogramar citas que estén en estado programada.',
                    ]);
                }

                if (! $citaBloqueada->fecha_hora->isFuture()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'fecha_hora' => 'Solo se pueden reprogramar citas futuras cuya hora aún no haya llegado.',
                    ]);
                }

                $nuevaFecha = \Illuminate\Support\Carbon::parse($request->validated('fecha_hora'));

                if (Cita::hayConflictoHorario(
                    $citaBloqueada->profesional_id,
                    $nuevaFecha,
                    $citaBloqueada->id
                )) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'fecha_hora' => 'El horario seleccionado se solapa con otra cita programada del especialista.',
                    ]);
                }

                $nuevaCita = new Cita();
                $nuevaCita->expediente_id = $citaBloqueada->expediente_id;
                $nuevaCita->consulta_id = $citaBloqueada->consulta_id;
                $nuevaCita->profesional_id = $citaBloqueada->profesional_id;
                $nuevaCita->area_id = $citaBloqueada->area_id;
                $nuevaCita->fecha_hora = $nuevaFecha;
                $nuevaCita->motivo = $citaBloqueada->motivo;
                $nuevaCita->estado = \App\Enums\EstadoCita::Programada->value;
                $nuevaCita->cita_origen_id = $citaBloqueada->id;
                $nuevaCita->save();

                $citaBloqueada->estado = \App\Enums\EstadoCita::Reprogramada->value;
                $citaBloqueada->motivo_reprogramacion = $request->validated('motivo_reprogramacion');
                $citaBloqueada->reprogramado_por_profesional_id = $request->user()->profesional->id;
                $citaBloqueada->fecha_reprogramacion = now();
                $citaBloqueada->save();
            });

            return redirect()->back()->with('success', 'Cita reprogramada exitosamente.');
        } catch (UniqueConstraintViolationException $e) {
            return redirect()->back()->withErrors([
                'fecha_hora' => self::MENSAJE_CONFLICTO_HORARIO,
            ])->withInput();
        } catch (QueryException $e) {
            if ($this->esViolacionExclusionAgenda($e)) {
                return redirect()->back()->withErrors([
                    'fecha_hora' => self::MENSAJE_CONFLICTO_HORARIO,
                ])->withInput();
            }

            throw $e;
        }
    }

    public function cancelar(CitaCancelarRequest $request, Expediente $expediente, Cita $cita): RedirectResponse
    {
        $this->asegurarCitaDelExpediente($expediente, $cita);
        $this->authorize('cancelar', $cita);

        DB::transaction(function () use ($request, $cita) {
            /** @var Cita $citaBloqueada */
            $citaBloqueada = Cita::query()
                ->whereKey($cita->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($citaBloqueada->estado !== \App\Enums\EstadoCita::Programada->value) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'estado' => 'Solo se pueden cancelar citas que estén en estado programada.',
                ]);
            }

            if (! $citaBloqueada->fecha_hora->isFuture()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'fecha_hora' => 'Solo se pueden cancelar citas futuras cuya hora aún no haya llegado.',
                ]);
            }

            $citaBloqueada->estado = \App\Enums\EstadoCita::Cancelada->value;
            $citaBloqueada->motivo_cancelacion = $request->validated('motivo_cancelacion');
            $citaBloqueada->save();
        });

        return redirect()->back()->with('success', 'Cita cancelada exitosamente.');
    }

    /**
     * Defensa en profundidad: la cita de la URL debe pertenecer al expediente (RP-02).
     */
    private function asegurarCitaDelExpediente(Expediente $expediente, Cita $cita): void
    {
        if ((string) $cita->expediente_id !== (string) $expediente->id) {
            abort(404);
        }
    }

    /**
     * Serializa mutaciones de agenda por profesional (NH-05).
     */
    private function bloquearAgendaProfesional(string $profesionalId): void
    {
        $lockKey = crc32('cita-agenda:'.$profesionalId);
        DB::select('SELECT pg_advisory_xact_lock(?)', [$lockKey]);
    }

    private function esViolacionExclusionAgenda(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;

        return $sqlState === '23P01'
            || str_contains($e->getMessage(), 'citas_no_solapamiento_programada')
            || str_contains($e->getMessage(), 'exclusion constraint');
    }
}
