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
        $pacientes = Paciente::select('codigo', 'created_at', 'updated_at', 'carnet', 'ultima_accion')
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
                    'updated_at' => $paciente->updated_at->format('Y-m-d H:i:s'),
                    'ultima_accion' => $paciente->ultima_accion ?? 'Registro de paciente',
                ];
            });

        return Inertia::render('Admin/Pacientes/Index', [
            'pacientes' => $pacientes,
            'filters' => $request->only(['search'])
        ]);
    }
}
