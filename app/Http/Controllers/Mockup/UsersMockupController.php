<?php

namespace App\Http\Controllers\Mockup;

use App\Http\Controllers\Controller;
use App\Support\MockupNavigation;
use Inertia\Inertia;
use Inertia\Response;

class UsersMockupController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Security/Users/Index', [
            'userLabel' => 'Director | Dr. Martínez',
            'navigation' => MockupNavigation::items(
                MockupNavigation::directorSecurity(),
                '/usuarios'
            ),
            'total' => 12,
            'users' => [
                [
                    'id' => 1,
                    'name' => 'Dr. Carlos Martínez',
                    'email' => 'c.martinez@clinica.org',
                    'role' => 'Director',
                    'roleVariant' => 'director',
                    'status' => 'Activo',
                    'statusVariant' => 'active',
                ],
                [
                    'id' => 2,
                    'name' => 'Dra. Ana López',
                    'email' => 'a.lopez@clinica.org',
                    'role' => 'Especialista',
                    'roleVariant' => 'specialist',
                    'status' => 'Activo',
                    'statusVariant' => 'active',
                ],
                [
                    'id' => 3,
                    'name' => 'Sra. María López',
                    'email' => 'm.lopez@clinica.org',
                    'role' => 'Recepción',
                    'roleVariant' => 'reception',
                    'status' => 'Activo',
                    'statusVariant' => 'active',
                ],
                [
                    'id' => 4,
                    'name' => 'Dr. Roberto Sánchez',
                    'email' => 'r.sanchez@clinica.org',
                    'role' => 'Especialista',
                    'roleVariant' => 'specialist',
                    'status' => 'Bloqueado',
                    'statusVariant' => 'blocked',
                ],
            ],
        ]);
    }
}
