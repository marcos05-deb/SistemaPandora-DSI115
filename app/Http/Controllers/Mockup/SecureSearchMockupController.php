<?php

namespace App\Http\Controllers\Mockup;

use App\Http\Controllers\Controller;
use App\Support\MockupNavigation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecureSearchMockupController extends Controller
{
    public function index(Request $request): Response
    {
        $pndCode = $request->query('code', '');

        return Inertia::render('SecureSearch/Index', [
            'userLabel' => 'Especialista | Dr. Pérez',
            'navigation' => MockupNavigation::items(
                MockupNavigation::specialist(),
                '/busqueda-segura'
            ),
            'searchCode' => $pndCode,
            'results' => $pndCode === 'PND-00124' ? $this->mockResults() : null,
        ]);
    }

    public function search(Request $request): Response
    {
        $code = $request->input('code', '');

        return Inertia::render('SecureSearch/Index', [
            'userLabel' => 'Especialista | Dr. Pérez',
            'navigation' => MockupNavigation::items(
                MockupNavigation::specialist(),
                '/busqueda-segura'
            ),
            'searchCode' => $code,
            'results' => strtoupper(trim($code)) === 'PND-00124' ? $this->mockResults() : [
                'code' => $code,
                'count' => 0,
                'notFound' => true,
            ],
        ]);
    }

    private function mockResults(): array
    {
        return [
            'code' => 'PND-00124',
            'count' => 3,
            'notFound' => false,
            'fields' => [
                ['label' => 'Edad aproximada', 'value' => '22 años'],
                ['label' => 'Facultad', 'value' => 'Ingeniería y Arquitectura'],
                ['label' => 'Género', 'value' => 'Masculino'],
                ['label' => 'Área de atención', 'value' => 'Psicología'],
                ['label' => 'Estado', 'value' => 'En seguimiento'],
                ['label' => 'Última consulta', 'value' => '2026-05-20'],
            ],
        ];
    }
}
