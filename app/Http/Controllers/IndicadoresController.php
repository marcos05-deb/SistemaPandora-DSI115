<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\IndicadoresRequest;
use App\Http\Resources\IndicadoresResource;
use App\Models\Especialista;
use App\Policies\IndicadorPolicy;
use App\Services\ClinicalAccessAuditor;
use App\Services\IndicadorService;
use Inertia\Inertia;
use Inertia\Response;

final class IndicadoresController extends Controller
{
    public function index(
        IndicadoresRequest $request,
        IndicadorService $service,
        ClinicalAccessAuditor $auditor,
        IndicadorPolicy $policy,
    ): Response {
        /** @var Especialista $user */
        $user = $request->user();
        $filters = $request->filtros();
        $result = $service->calcular($filters, $user);
        $auditor->recordAggregate($user, 'read_institutional_indicators', $filters->toArray());

        return Inertia::render('Indicadores/Index', [
            'layout' => $policy->layoutFor($user),
            'resultados' => IndicadoresResource::make($result)->resolve($request),
        ]);
    }
}
