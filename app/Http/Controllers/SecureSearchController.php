<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecureSearchController extends Controller
{
    /**
     * Display the secure search interface.
     */
    public function index(): Response|\Illuminate\Http\RedirectResponse
    {
        $user = auth()->user();

        if ($user->hasRole('psychosocial_referent') || $user->hasRole('specialist')) {
            return redirect()->route('pacientes.index');
        }

        return Inertia::render('SecureSearch/Index', [
            'searchCode' => '',
            'results' => null,
        ]);
    }

    /**
     * Handle the secure search request.
     */
    public function search(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i'],
        ], [
            'code.regex' => 'El formato del codigo UUID no es valido.',
        ]);

        $user = auth()->user();
        $code = strtolower($validated['code']);

        $query = Paciente::where('codigo', $code);

        if ($user->hasRole('psychosocial_referent') || $user->hasRole('specialist')) {
            return redirect()->route('busqueda-segura')->withErrors([
                'code' => 'No tienes permisos para realizar busquedas.',
            ]);
        }

        $paciente = $query->first();

        if (!$paciente) {
            return redirect()->route('busqueda-segura')->withErrors([
                'code' => 'No se encontraron registros para el codigo ingresado.',
            ]);
        }

        return Inertia::render('SecureSearch/Index', [
            'searchCode' => $validated['code'],
            'results' => [
                'found' => true,
                'code' => $paciente->codigo,
                'pacienteId' => $paciente->codigo,
                'carnet' => $paciente->carnet,
            ],
        ]);
    }
}
