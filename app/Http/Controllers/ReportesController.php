<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReporteGenerarRequest;
use App\Http\Requests\ReporteIndexRequest;
use App\Http\Resources\ReporteResource;
use App\Models\Especialista;
use App\Services\ClinicalAccessAuditor;
use App\Services\ReporteParametrizadoService;
use Inertia\Inertia;
use Inertia\Response;

final class ReportesController extends Controller
{
    public function index(ReporteIndexRequest $request, ReporteParametrizadoService $service): Response
    {
        return Inertia::render('Reportes/Index', $service->datosFormulario($request->user()));
    }

    public function generate(
        ReporteGenerarRequest $request,
        ReporteParametrizadoService $service,
        ClinicalAccessAuditor $auditor,
    ): Response {
        /** @var Especialista $user */
        $user = $request->user();
        $parameters = $request->parametros();
        $report = $service->generar($parameters, $user);
        $auditor->recordAggregate($user, 'generate_parameterized_report', $parameters->toArray());

        return Inertia::render('Reportes/Index', [
            ...$service->datosFormulario($user),
            'reporte' => ReporteResource::make($report)->resolve($request),
        ]);
    }
}
