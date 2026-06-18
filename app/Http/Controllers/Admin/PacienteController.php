<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Paciente;
use Inertia\Inertia;
use Inertia\Response;

class PacienteController extends Controller
{
    /**
     * Muestra la lista de todos los pacientes para el sysadmin (solo auditoría, sin datos personales).
     */
    public function index(\Illuminate\Http\Request $request): Response
    {
        $pacientes = Paciente::select('codigo', 'created_at', 'carnet')
            ->when($request->search, function ($query, $search) {
                // Since codigo is a UUID in Postgres, we cast to text for a safe partial search
                $query->whereRaw('codigo::text ILIKE ?', ["%{$search}%"]);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(function ($paciente) {
                return [
                    'codigo' => $paciente->codigo,
                    'created_at' => $paciente->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return Inertia::render('Admin/Pacientes/Index', [
            'pacientes' => $pacientes,
            'filters' => $request->only(['search'])
        ]);
    }
}
