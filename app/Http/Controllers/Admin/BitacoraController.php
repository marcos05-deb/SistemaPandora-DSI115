<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BitacoraIndexRequest;
use App\Models\Especialista;
use App\Services\BitacoraExportService;
use App\Services\BitacoraQueryService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class BitacoraController extends Controller
{
    public function index(BitacoraIndexRequest $request, BitacoraQueryService $service): Response
    {
        /** @var Especialista $user */
        $user = $request->user();
        $filtros = $request->filtros();
        $paginator = $service->buscar($filtros);
        $service->registrarConsulta($user, $filtros->toArray());

        return Inertia::render('Admin/Bitacora/Index', [
            'eventos' => [
                'data' => $service->mapear($paginator),
                'links' => $paginator->toArray()['links'] ?? [],
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
            'filtros' => $filtros->toArray(),
            'usuarios' => $service->usuariosDisponibles(),
            'acciones' => $service->catalogoAcciones(),
            'orden' => 'más reciente primero',
            'export_max' => BitacoraExportService::MAX_EVENTOS,
        ]);
    }

    public function export(BitacoraIndexRequest $request, BitacoraExportService $exports): SymfonyResponse
    {
        /** @var Especialista $user */
        $user = $request->user();
        $archivo = $exports->exportar($request->filtros(), $user);

        return response($archivo->contenido, 200, [
            'Content-Type' => $archivo->formato->mime(),
            'Content-Disposition' => 'attachment; filename="'.$archivo->nombreArchivo.'"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'X-Checksum-SHA256' => $archivo->sha256,
            'Content-Length' => (string) strlen($archivo->contenido),
        ]);
    }
}
