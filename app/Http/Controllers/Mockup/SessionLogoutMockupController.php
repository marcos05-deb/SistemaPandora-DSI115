<?php

namespace App\Http\Controllers\Mockup;

use App\Http\Controllers\Controller;
use App\Support\MockupNavigation;
use Inertia\Inertia;
use Inertia\Response;

class SessionLogoutMockupController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Security/Session/Show', [
            'userLabel' => 'Director | Dr. Martínez',
            'navigation' => MockupNavigation::items(
                [
                    ['label' => 'Inicio', 'href' => '/cierre-sesion'],
                    ['label' => 'Seguridad y Acceso', 'href' => '/cierre-sesion'],
                    ['label' => 'Identidad y Privacidad', 'href' => '/codigos-privacidad'],
                    ['label' => 'Expedientes', 'href' => '#'],
                    ['label' => 'Reportes', 'href' => '#'],
                    ['label' => 'Auditoría', 'href' => '#'],
                ],
                '/cierre-sesion'
            ),
            'session' => [
                'userName' => 'Dr. Carlos Martínez',
                'role' => 'Director',
                'tokenPreview' => 'eyJhbGciOiJIUzI1...',
                'tokenValid' => true,
                'expiresIn' => '6h 42min (8h total)',
                'startedAt' => '13 may 2026, 08:15 AM',
            ],
        ]);
    }
}
