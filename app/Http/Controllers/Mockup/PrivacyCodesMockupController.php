<?php

namespace App\Http\Controllers\Mockup;

use App\Http\Controllers\Controller;
use App\Support\MockupNavigation;
use Inertia\Inertia;
use Inertia\Response;

class PrivacyCodesMockupController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Privacy/Codes/Index', [
            'userLabel' => 'Director | Dr. Martínez',
            'navigation' => MockupNavigation::items(
                MockupNavigation::directorPrivacy(),
                '/codigos-privacidad'
            ),
            'totalGenerated' => 128,
            'codes' => [
                [
                    'code' => 'PND-00124',
                    'patient' => 'Paciente #124',
                    'faculty' => 'Ingeniería',
                    'createdAt' => '2026-05-17',
                    'status' => 'Activo',
                    'statusVariant' => 'active',
                ],
                [
                    'code' => 'PND-00123',
                    'patient' => 'Paciente #123',
                    'faculty' => 'Medicina',
                    'createdAt' => '2026-05-16',
                    'status' => 'Activo',
                    'statusVariant' => 'active',
                ],
                [
                    'code' => 'PND-00122',
                    'patient' => 'Paciente #122',
                    'faculty' => 'Ciencias Sociales',
                    'createdAt' => '2026-05-15',
                    'status' => 'Inactivo',
                    'statusVariant' => 'inactive',
                ],
                [
                    'code' => 'PND-00121',
                    'patient' => 'Paciente #121',
                    'faculty' => 'Derecho',
                    'createdAt' => '2026-05-14',
                    'status' => 'Activo',
                    'statusVariant' => 'active',
                ],
            ],
        ]);
    }
}
