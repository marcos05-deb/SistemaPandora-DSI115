<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RevisarSolicitudCorreccionRequest;
use App\Models\Paciente;
use App\Models\PacienteCorreccionAuditoria;
use App\Models\SolicitudCorreccionExpediente;
use App\Services\SolicitudCorreccionExpedienteService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PacienteController extends Controller
{
    /**
     * Auditoría anonimizada: UUID, campos tocados, solicitudes de permiso. Sin PII.
     */
    public function index(Request $request): Response
    {
        $pacientes = Paciente::select('codigo', 'created_at', 'updated_at', 'ultima_accion', 'datos_corregidos_en')
            ->when($request->search, function ($query, $search) {
                $query->whereRaw('codigo::text ILIKE ?', ["%{$search}%"]);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10, ['*'], 'pacientes_page')
            ->withQueryString()
            ->through(function ($paciente) {
                return [
                    'codigo' => $paciente->codigo,
                    'created_at' => $paciente->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $paciente->updated_at->format('Y-m-d H:i:s'),
                    'ultima_accion' => $paciente->ultima_accion ?? 'Registro de paciente',
                    'datos_corregidos' => (bool) $paciente->datos_corregidos_en,
                ];
            });

        $correccionesQuery = PacienteCorreccionAuditoria::query()
            ->with(['usuario:id,name,email', 'revisadoPor:id,name'])
            ->when($request->tipo_evento, fn ($q, $tipo) => $q->where('tipo_evento', $tipo))
            ->when($request->nivel_evento, fn ($q, $nivel) => $q->where('nivel_evento', $nivel))
            ->when($request->usuario_id, fn ($q, $uid) => $q->where('usuario_id', $uid))
            ->when($request->paciente, function ($q, $paciente) {
                $q->whereRaw('paciente_id::text ILIKE ?', ["%{$paciente}%"]);
            })
            ->when($request->fecha_desde, fn ($q, $desde) => $q->whereDate('created_at', '>=', $desde))
            ->when($request->fecha_hasta, fn ($q, $hasta) => $q->whereDate('created_at', '<=', $hasta))
            ->orderByDesc('created_at');

        $correcciones = $correccionesQuery
            ->paginate(15, ['*'], 'correcciones_page')
            ->withQueryString()
            ->through(fn (PacienteCorreccionAuditoria $c) => $this->mapCorreccionResumen($c));

        $avisosPendientes = PacienteCorreccionAuditoria::query()
            ->with(['usuario:id,name'])
            ->where('aviso_sensible', true)
            ->where('aviso_revisado', false)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (PacienteCorreccionAuditoria $c) => $this->mapCorreccionResumen($c));

        $solicitudes = SolicitudCorreccionExpediente::query()
            ->with(['solicitante:id,name', 'revisadoPor:id,name'])
            ->when($request->solicitud_estado, fn ($q, $e) => $q->where('estado', $e))
            ->orderByRaw("CASE WHEN estado = 'pendiente' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'solicitudes_page')
            ->withQueryString()
            ->through(fn (SolicitudCorreccionExpediente $s) => app(SolicitudCorreccionExpedienteService::class)->mapSolicitudResumen($s));

        $solicitudesPendientes = SolicitudCorreccionExpediente::query()
            ->with(['solicitante:id,name'])
            ->pendientes()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (SolicitudCorreccionExpediente $s) => app(SolicitudCorreccionExpedienteService::class)->mapSolicitudResumen($s));

        return Inertia::render('Admin/Pacientes/Index', [
            'pacientes' => $pacientes,
            'correcciones' => $correcciones,
            'avisosPendientes' => $avisosPendientes,
            'solicitudes' => $solicitudes,
            'solicitudesPendientes' => $solicitudesPendientes,
            'filters' => $request->only([
                'search',
                'tipo_evento',
                'nivel_evento',
                'usuario_id',
                'paciente',
                'fecha_desde',
                'fecha_hasta',
                'solicitud_estado',
            ]),
        ]);
    }

    public function showCorreccion(PacienteCorreccionAuditoria $correccion): Response
    {
        $correccion->load(['usuario:id,name,email', 'revisadoPor:id,name', 'profesional:id']);

        return Inertia::render('Admin/Pacientes/CorreccionShow', [
            'correccion' => [
                'id' => $correccion->id,
                'paciente_id' => $correccion->paciente_id,
                'tipo_evento' => $correccion->tipo_evento,
                'nivel_evento' => $correccion->nivel_evento,
                'aviso_sensible' => $correccion->aviso_sensible,
                'aviso_revisado' => $correccion->aviso_revisado,
                'revisado_en' => $correccion->revisado_en?->format('Y-m-d H:i:s'),
                'revisado_por' => $correccion->revisadoPor?->name,
                'motivo' => $correccion->motivo,
                'campos_modificados' => $correccion->campos_modificados,
                'rol_usuario' => $correccion->rol_usuario,
                'responsable' => $correccion->usuario?->name,
                'ip_address' => $correccion->ip_address,
                'created_at' => $correccion->created_at?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function marcarRevisado(Request $request, PacienteCorreccionAuditoria $correccion)
    {
        if (! $correccion->aviso_sensible) {
            return redirect()->back()
                ->with('message', 'Este registro no es un aviso sensible.')
                ->with('variant', 'error');
        }

        if ($correccion->aviso_revisado) {
            return redirect()->back()
                ->with('message', 'El aviso ya estaba marcado como revisado.')
                ->with('variant', 'success');
        }

        $correccion->marcarRevisado($request->user());

        return redirect()->back()
            ->with('message', 'Aviso marcado como revisado. El registro de auditoría se conserva intacto.')
            ->with('variant', 'success');
    }

    public function aprobarSolicitud(
        RevisarSolicitudCorreccionRequest $request,
        SolicitudCorreccionExpediente $solicitud,
        SolicitudCorreccionExpedienteService $service
    ) {
        $service->aprobar($solicitud, $request->user(), $request->validated('nota_admin'));

        return redirect()->back()
            ->with('message', 'Permiso aprobado. El solicitante puede realizar una corrección en los campos autorizados.')
            ->with('variant', 'success');
    }

    public function rechazarSolicitud(
        RevisarSolicitudCorreccionRequest $request,
        SolicitudCorreccionExpediente $solicitud,
        SolicitudCorreccionExpedienteService $service
    ) {
        $service->rechazar($solicitud, $request->user(), $request->validated('nota_admin'));

        return redirect()->back()
            ->with('message', 'Solicitud rechazada. El expediente permanece bloqueado para nuevas correcciones.')
            ->with('variant', 'success');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCorreccionResumen(PacienteCorreccionAuditoria $c): array
    {
        return [
            'id' => $c->id,
            'paciente_id' => $c->paciente_id,
            'tipo_evento' => $c->tipo_evento,
            'nivel_evento' => $c->nivel_evento,
            'campos_modificados' => $c->campos_modificados,
            'responsable' => $c->usuario?->name ?? 'Desconocido',
            'motivo' => $c->motivo,
            'aviso_sensible' => $c->aviso_sensible,
            'aviso_revisado' => $c->aviso_revisado,
            'created_at' => $c->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
