<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AlertaCerrarRequest;
use App\Http\Requests\AlertaDescartarRequest;
use App\Http\Requests\AlertaIndexRequest;
use App\Http\Requests\SeguimientoStoreRequest;
use App\Http\Resources\AlertaDetalleResource;
use App\Http\Resources\AlertaPreventivaResource;
use App\Models\AlertaPreventiva;
use App\Models\Especialista;
use App\Services\AlertaSeguimientoService;
use App\Services\ClinicalAccessAuditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class AlertaPreventivaController extends Controller
{
    public function index(AlertaIndexRequest $request): Response
    {
        $alertas = AlertaPreventiva::query()
            ->with(['paciente:codigo,carnet', 'versionRubrica:id,numero_version'])
            ->when($request->validated('estado'), fn ($query, $estado) => $query->where('estado', $estado))
            ->orderByDesc('generada_en')
            ->paginate(15)
            ->withQueryString();

        $auditor = app(ClinicalAccessAuditor::class);
        foreach ($alertas->getCollection() as $alerta) {
            $auditor->record($request->user(), $alerta, 'read_alert_inbox');
        }

        return Inertia::render('Alertas/Index', [
            'alerts' => AlertaPreventivaResource::collection($alertas),
            'filtros' => ['estado' => $request->validated('estado')],
        ]);
    }

    public function show(
        Request $request,
        AlertaPreventiva $alerta,
        ClinicalAccessAuditor $auditor,
    ): Response {
        $this->authorize('view', $alerta);
        $auditor->record($request->user(), $alerta, 'read_alert_detail');
        $alerta->load([
            'paciente:codigo,carnet',
            'versionRubrica:id,numero_version,vigente_desde',
            'responsable.especialista:id,name',
            'cerradaPor.especialista:id,name',
            'descartadaPor.especialista:id,name',
            'seguimientos.responsable.especialista:id,name',
            'seguimientos.registradoPor.especialista:id,name',
        ]);
        $responsableSeguimiento = $request->user()->profesionalParaArea($alerta->area_id);
        $responsableSeguimiento?->loadMissing('especialista:id,name');

        return Inertia::render('Alertas/Show', [
            'alerta' => AlertaDetalleResource::make($alerta)->resolve($request),
            'responsableSeguimiento' => $responsableSeguimiento === null ? null : [
                'id' => (string) $responsableSeguimiento->getKey(),
                'nombre' => $responsableSeguimiento->especialista?->name,
            ],
        ]);
    }

    public function storeSeguimiento(
        SeguimientoStoreRequest $request,
        AlertaPreventiva $alerta,
        AlertaSeguimientoService $service,
    ): RedirectResponse {
        /** @var Especialista $user */
        $user = $request->user();
        $service->registrarSeguimiento($alerta, $user, $request->validated());

        return redirect()->back()->with('message', 'Seguimiento preventivo registrado.');
    }

    public function cerrar(
        AlertaCerrarRequest $request,
        AlertaPreventiva $alerta,
        AlertaSeguimientoService $service,
    ): RedirectResponse {
        /** @var Especialista $user */
        $user = $request->user();
        $service->cerrar($alerta, $user, $request->validated());

        return redirect()->back()->with('message', 'Alerta cerrada correctamente.');
    }

    public function descartar(
        AlertaDescartarRequest $request,
        AlertaPreventiva $alerta,
        AlertaSeguimientoService $service,
    ): RedirectResponse {
        /** @var Especialista $user */
        $user = $request->user();
        $service->descartar($alerta, $user, $request->validated());

        return redirect()->back()->with('message', 'Alerta descartada correctamente.');
    }

    public function markNotificationAsRead(DatabaseNotification $notification): RedirectResponse
    {
        $this->authorize('markNotificationAsRead', [AlertaPreventiva::class, $notification]);
        $notification->markAsRead();

        return back();
    }
}
