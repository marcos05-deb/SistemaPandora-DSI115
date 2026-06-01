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
    public function index(): Response
    {
        $pacientes = Paciente::select('codigo', 'created_at', 'carnet')
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->through(function ($paciente) {
                return [
                    'codigo' => $paciente->codigo,
                    // Dejamos el carnet o no? El admin general no debe ver datos de pacientes. 
                    // El requerimiento decía "solo el código puede ver, los datos personales no".
                    'created_at' => $paciente->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return Inertia::render('Admin/Pacientes/Index', [
            'pacientes' => $pacientes
        ]);
    }
}
