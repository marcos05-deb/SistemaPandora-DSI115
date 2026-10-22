<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AlertaIndexRequest;
use App\Http\Resources\AlertaPreventivaResource;
use App\Models\AlertaPreventiva;
use App\Services\ClinicalAccessAuditor;
use Illuminate\Http\RedirectResponse;
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

    public function markNotificationAsRead(DatabaseNotification $notification): RedirectResponse
    {
        $this->authorize('markNotificationAsRead', [AlertaPreventiva::class, $notification]);
        $notification->markAsRead();

        return back();
    }
}
