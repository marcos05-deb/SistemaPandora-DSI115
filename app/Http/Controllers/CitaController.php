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

    /** Límite operativo por defecto de citas en vistas diaria/semanal (RP-09 / R593-06). */
    private const LIMITE_VISTA_AGENDA = 500;

    private function limiteVistaAgenda(): int
    {
        return max(1, (int) config('citas.limite_vista_agenda', self::LIMITE_VISTA_AGENDA));
    }

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
            'page' => 'nullable|integer|min:1',
        ]);

        $vista = $validated['vista'] ?? 'lista';
        $referencia = isset($validated['referencia'])
            ? \Illuminate\Support\Carbon::parse($validated['referencia'])->startOfDay()
            : now()->startOfDay();
        $page = max(1, (int) ($validated['page'] ?? 1));

        $estado = $validated['estado'] ?? null;
        if ($estado === 'todos') {
            $estado = null;
        }

        $query = Cita::with(['expediente.paciente', 'profesional.especialista', 'registradoPor.especialista']);

        if (! $user->hasRole('area_coordinator')) {
            $profesionalIds = $user->perfilesProfesionales()->pluck('id');
            $query->whereIn('profesional_id', $profesionalIds);
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

        // NH-06 / RP-09 / R593-06: tope operativo con paginación y total real del rango.
        $semanaTruncada = false;
        $totalRango = null;
        $rangoLista = null;
        if ($vista === 'diaria' || $vista === 'semanal') {
            $limite = $this->limiteVistaAgenda();
            $totalRango = (clone $query)->count();
            $items = $query->orderBy('fecha_hora', 'asc')
                ->forPage($page, $limite)
                ->get();
            $semanaTruncada = $totalRango > $limite;
            $citas = new LengthAwarePaginator(
                $items,
                $totalRango,
                $limite,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            if ($vista === 'semanal') {
                $inicio = $referencia->copy()->startOfWeek();
                $fin = $referencia->copy()->endOfWeek();
                $rangoLista = [
                    'fecha_desde' => $inicio->toDateString(),
                    'fecha_hasta' => $fin->toDateString(),
                ];
            } else {
                $rangoLista = [
                    'fecha_desde' => $referencia->toDateString(),
                    'fecha_hasta' => $referencia->toDateString(),
                ];
            }
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
                'limite' => $vista === 'diaria' || $vista === 'semanal' ? $this->limiteVistaAgenda() : null,
                'truncada' => $semanaTruncada,
                'total' => $totalRango,
                'rangoLista' => $rangoLista,
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

        $profesional = $request->user()->profesionalParaArea($expediente->area_id);
        abort_unless($profesional, 403);

        try {
            DB::transaction(function () use ($request, $expediente, $profesional) {
                $fechaHora = \Illuminate\Support\Carbon::parse($request->validated('fecha_hora'));
                $profesionalId = $profesional->id;
                $profesionalUserId = (int) $profesional->user_id;

                $this->bloquearAgendaUsuario($profesionalUserId);

                if (Cita::hayConflictoHorario($profesionalUserId, $fechaHora)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'fecha_hora' => 'El horario seleccionado se solapa con otra cita programada del especialista.',
                    ]);
                }

                $cita = new Cita();
                $cita->expediente_id = $expediente->id;
                $cita->consulta_id = $request->validated('consulta_id');
                $cita->profesional_id = $profesionalId;
                $cita->profesional_user_id = $profesionalUserId;
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

        $profesional = $request->user()->profesionalParaArea($cita->area_id);
        abort_unless($profesional, 403);

        $cita->registrarAsistencia(
            $request->validated('estado'),
            $profesional->id
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

        $reprogramador = $request->user()->profesionalParaArea($cita->area_id);
        abort_unless($reprogramador, 403);

        try {
            DB::transaction(function () use ($request, $cita, $reprogramador) {
                /** @var Cita $citaBloqueada */
                $citaBloqueada = Cita::query()
                    ->whereKey($cita->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $profesionalUserId = (int) $citaBloqueada->profesional_user_id;
                $this->bloquearAgendaUsuario($profesionalUserId);

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
                    $profesionalUserId,
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
                $nuevaCita->profesional_user_id = $profesionalUserId;
                $nuevaCita->area_id = $citaBloqueada->area_id;
                $nuevaCita->fecha_hora = $nuevaFecha;
                $nuevaCita->motivo = $citaBloqueada->motivo;
                $nuevaCita->estado = \App\Enums\EstadoCita::Programada->value;
                $nuevaCita->cita_origen_id = $citaBloqueada->id;
                $nuevaCita->save();

                $citaBloqueada->estado = \App\Enums\EstadoCita::Reprogramada->value;
                $citaBloqueada->motivo_reprogramacion = $request->validated('motivo_reprogramacion');
                $citaBloqueada->reprogramado_por_profesional_id = $reprogramador->id;
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

        $cancelador = $request->user()->profesionalParaArea($cita->area_id);
        abort_unless($cancelador, 403);

        DB::transaction(function () use ($request, $cita, $cancelador) {
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
            $citaBloqueada->cancelado_por_profesional_id = $cancelador->id;
            $citaBloqueada->fecha_cancelacion = now();
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
     * Serializa mutaciones de agenda por persona (RF-01 / NH-05).
     */
    private function bloquearAgendaUsuario(int $profesionalUserId): void
    {
        $lockKey = crc32('cita-agenda-usuario:'.$profesionalUserId);
        DB::select('SELECT pg_advisory_xact_lock(?)', [$lockKey]);
    }

    private function esViolacionExclusionAgenda(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $message = $e->getMessage();

        return $sqlState === '23P01'
            || str_contains($message, 'citas_no_solapamiento_usuario_programada')
            || str_contains($message, 'citas_no_solapamiento_programada')
            || str_contains($message, 'exclusion constraint');
    }
}
