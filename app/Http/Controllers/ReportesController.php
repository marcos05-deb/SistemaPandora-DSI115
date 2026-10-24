<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FormatoExportacion;
use App\Http\Requests\ReporteGenerarRequest;
use App\Http\Requests\ReporteIndexRequest;
use App\Http\Resources\ReporteResource;
use App\Models\Especialista;
use App\Services\ClinicalAccessAuditor;
use App\Services\ReporteExportService;
use App\Services\ReporteParametrizadoService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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

    public function exportPdf(
        ReporteGenerarRequest $request,
        ReporteExportService $exports,
        ClinicalAccessAuditor $auditor,
    ): SymfonyResponse {
        return $this->download($request, $exports, $auditor, FormatoExportacion::Pdf);
    }

    public function exportExcel(
        ReporteGenerarRequest $request,
        ReporteExportService $exports,
        ClinicalAccessAuditor $auditor,
    ): SymfonyResponse {
        return $this->download($request, $exports, $auditor, FormatoExportacion::Excel);
    }

    private function download(
        ReporteGenerarRequest $request,
        ReporteExportService $exports,
        ClinicalAccessAuditor $auditor,
        FormatoExportacion $formato,
    ): SymfonyResponse {
        /** @var Especialista $user */
        $user = $request->user();
        $parameters = $request->parametros();
        $archivo = $exports->exportar($parameters, $formato, $user);
        $auditor->recordAggregate($user, 'export_parameterized_report_'.$formato->value, $parameters->toArray());

        return response($archivo->contenido, 200, [
            'Content-Type' => $formato->mime(),
            'Content-Disposition' => 'attachment; filename="'.$archivo->nombreArchivo.'"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Length' => (string) strlen($archivo->contenido),
        ]);
    }
}
