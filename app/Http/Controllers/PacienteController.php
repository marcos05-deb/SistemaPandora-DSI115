<?php

namespace App\Http\Controllers;

use App\Models\Carrera;
use App\Models\Facultad;
use App\Models\Paciente;
use App\Models\ContactoPaciente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PacienteController extends Controller
{
    /**
     * Show the form for creating a new patient.
     */
    public function create(): Response
    {
        // Require role explicitly here as an extra layer
        if (!auth()->user()->hasRole('psychosocial_referent')) {
            abort(403, 'Solo el Referente Psicosocial puede registrar pacientes.');
        }

        $facultades = Facultad::with('carreras')->get();

        return Inertia::render('Pacientes/Create', [
            'facultades' => $facultades
        ]);
    }

    /**
     * Store a newly created patient.
     */
    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('psychosocial_referent')) {
            abort(403, 'Solo el Referente Psicosocial puede registrar pacientes.');
        }

        $validated = $request->validate([
            // Paciente
            'carnet' => ['required', 'string', 'regex:/^[A-Za-z]{2}\d{5}$/', 'unique:pacientes,carnet'],
            'nombre_completo' => 'required|string|max:255',
            'direccion' => 'required|string',
            'carrera_id' => 'required|exists:carreras,id',
            'sexo' => 'required|in:M,F,Otro',
            'estado_civil' => 'required|in:Soltero,Casado,Divorciado,Viudo,Unión Libre',
            'fecha_nacimiento' => 'required|date|before_or_equal:today',
            'profesion_ocupacion' => 'nullable|string|max:255',
            'fecha_primera_consulta' => 'nullable|date|before_or_equal:today',
            'referido_por' => 'nullable|string|max:255',
            'llevado_por' => 'nullable|string|max:255',

            // Padre / Madre
            'padre_nombre' => 'required_if:responsable_parentesco,Padre|nullable|string|max:255',
            'padre_telefono' => ['nullable', 'string', 'regex:/^\d{8}$/'],
            'madre_nombre' => 'required_if:responsable_parentesco,Madre|nullable|string|max:255',
            'madre_telefono' => ['nullable', 'string', 'regex:/^\d{8}$/'],

            // Responsable Principal
            'responsable_parentesco' => 'required|in:Padre,Madre,Otro',
            'responsable_nombre' => 'required_if:responsable_parentesco,Otro|nullable|string|max:255',
            'responsable_telefono' => ['required', 'string', 'regex:/^\d{8}$/'],
            'responsable_direccion' => 'required|string',
        ], [
            'carnet.regex' => 'El carnet debe contener exactamente 2 letras seguidas de 5 números.',
            'carnet.unique' => 'Este carnet ya ha sido registrado.',
            'fecha_nacimiento.before_or_equal' => 'La fecha no puede estar en el futuro.',
            'padre_telefono.regex' => 'Debe tener exactamente 8 dígitos.',
            'madre_telefono.regex' => 'Debe tener exactamente 8 dígitos.',
            'responsable_telefono.regex' => 'Debe tener exactamente 8 dígitos numéricos.',
        ]);

        DB::transaction(function () use ($validated) {
            $paciente = Paciente::create([
                'carnet' => $validated['carnet'],
                'nombre_completo' => $validated['nombre_completo'],
                'direccion' => $validated['direccion'],
                'carrera_id' => $validated['carrera_id'],
                'creado_por_profesional_id' => auth()->user()->profesional->id,
                'sexo' => $validated['sexo'],
                'estado_civil' => $validated['estado_civil'],
                'fecha_nacimiento' => $validated['fecha_nacimiento'],
                'profesion_ocupacion' => $validated['profesion_ocupacion'],
                'fecha_primera_consulta' => $validated['fecha_primera_consulta'],
                'referido_por' => $validated['referido_por'],
                'llevado_por' => $validated['llevado_por'],
            ]);

            // Crear Padre
            if (!empty($validated['padre_nombre'])) {
                $esResponsable = ($validated['responsable_parentesco'] === 'Padre');
                ContactoPaciente::create([
                    'paciente_id' => $paciente->codigo,
                    'nombre_completo' => $validated['padre_nombre'],
                    'parentesco' => 'Padre',
                    'telefono_personal' => $esResponsable ? $validated['responsable_telefono'] : ($validated['padre_telefono'] ?? '00000000'),
                    'direccion' => $esResponsable ? $validated['responsable_direccion'] : null,
                    'es_responsable' => $esResponsable,
                ]);
            }

            // Crear Madre
            if (!empty($validated['madre_nombre'])) {
                $esResponsable = ($validated['responsable_parentesco'] === 'Madre');
                ContactoPaciente::create([
                    'paciente_id' => $paciente->codigo,
                    'nombre_completo' => $validated['madre_nombre'],
                    'parentesco' => 'Madre',
                    'telefono_personal' => $esResponsable ? $validated['responsable_telefono'] : ($validated['madre_telefono'] ?? '00000000'),
                    'direccion' => $esResponsable ? $validated['responsable_direccion'] : null,
                    'es_responsable' => $esResponsable,
                ]);
            }

            // Crear Otro Responsable
            if ($validated['responsable_parentesco'] === 'Otro' && !empty($validated['responsable_nombre'])) {
                ContactoPaciente::create([
                    'paciente_id' => $paciente->codigo,
                    'nombre_completo' => $validated['responsable_nombre'],
                    'parentesco' => 'Otro',
                    'telefono_personal' => $validated['responsable_telefono'],
                    'direccion' => $validated['responsable_direccion'],
                    'es_responsable' => true,
                ]);
            }
        });

        return redirect()->route('dashboard')
            ->with('message', 'Paciente registrado exitosamente. Toda la información ha sido cifrada.')
            ->with('variant', 'success');
    }

    /**
     * Display the specified patient.
     */
    public function show($carnet): Response
    {
        if (!auth()->user()->hasRole('psychosocial_referent')) {
            abort(403, 'Solo el Referente Psicosocial puede ver el expediente de un paciente.');
        }

        $paciente = Paciente::with(['contactos', 'carrera.facultad'])
            ->where('carnet', $carnet)
            ->firstOrFail();

        // RBAC: Verify the psychosocial referent created this patient
        if ($paciente->creado_por_profesional_id !== auth()->user()->profesional->id) {
            abort(403, 'No tienes permiso para ver los datos de este paciente.');
        }

        return Inertia::render('Pacientes/Show', [
            'paciente' => $paciente
        ]);
    }
}
