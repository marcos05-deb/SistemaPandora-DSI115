<?php

namespace App\Http\Controllers\Mockup;

use App\Http\Controllers\Controller;
use App\Support\MockupNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatientRegistrationMockupController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Patients/Create', [
            'userLabel' => 'Recepción | Sra. López',
            'navigation' => MockupNavigation::items(
                MockupNavigation::reception(),
                '/registro-paciente'
            ),
            'faculties' => [
                'Ingeniería y Arquitectura',
                'Medicina',
                'Ciencias Sociales',
                'Derecho',
                'Ciencias Naturales',
            ],
            'generatedCode' => session('generated_privacy_code'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'age' => ['required', 'integer', 'min:15', 'max:60'],
            'faculty' => ['required', 'string', 'max:255'],
            'guardian' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $code = 'PND-'.str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);

        return redirect()
            ->route('registro-paciente')
            ->with([
                'message' => 'Paciente registrado correctamente (demo).',
                'variant' => 'success',
                'generated_privacy_code' => $code,
            ]);
    }
}
