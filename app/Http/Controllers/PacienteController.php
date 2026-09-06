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
     * Display the search interface and handle search requests.
     */
    public function index(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        $user = auth()->user();

        if ($user->hasRole('area_coordinator')) {
            return redirect()->route('busqueda-segura');
        }

        if ($request->has('carnet')) {
            $validated = $request->validate([
                'carnet' => 'required|string|size:7'
            ]);

            // Blind index search: Exact match on non-encrypted field
            $query = Paciente::where('carnet', strtoupper($validated['carnet']));

            if ($user->hasRole('psychosocial_referent')) {
                $query->where('creado_por_profesional_id', $user->profesional->id);
            } elseif (!($user->hasRole('specialist') || $user->hasRole('area_coordinator'))) {
                $query->whereRaw('1 = 0');
            }

            $paciente = $query->first();

            if ($paciente) {
                return Inertia::render('Pacientes/Index', [
                    'results' => [
                        'found' => true,
                        'carnet' => $paciente->carnet,
                        'codigo' => $paciente->codigo,
                    ],
                ]);
            }

            return redirect()->route('pacientes.index')->withErrors([
                'carnet' => 'Expediente no encontrado o no tienes permisos para acceder a él.'
            ]);
        }

        return Inertia::render('Pacientes/Index');
    }

    /**
     * Show the form for creating a new patient.
     */
    public function create(): Response
    {
        $facultades = Facultad::with('carreras')->get();

        return Inertia::render('Pacientes/Create', [
            'facultades' => $facultades
        ]);
    }

    /**
     * Store a newly created patient.
     */
    public function store(\App\Http\Requests\StorePacienteRequest $request)
    {
        $validated = $request->validated();

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
                'motivo_consulta' => $validated['motivo_consulta'],
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
        $user = auth()->user();

        $pacienteQuery = Paciente::with(['contactos', 'carrera.facultad']);

        if ($user->hasRole('psychosocial_referent')) {
            $pacienteQuery->with(['expedientes' => function ($q) {
                $q->withoutGlobalScope(\App\Models\Scopes\AreaScope::class)
                  ->select('id', 'paciente_id', 'area_id', 'estado', 'created_at', 'updated_at');
            }]);
        } else {
            $pacienteQuery->with('expedientes');
        }

        $paciente = $pacienteQuery->where('carnet', $carnet)->firstOrFail();

        // Aplicamos la política IDOR
        $this->authorize('view', $paciente);
        
        $areasDisponibles = [];
        if ($user->hasRole('psychosocial_referent')) {
            $areasDisponibles = \App\Models\Area::select('id', 'nombre')->orderBy('nombre')->get();
        }

        return Inertia::render('Pacientes/Show', [
            'paciente' => (new \App\Http\Resources\PacienteResource($paciente))->resolve(),
            'areasDisponibles' => $areasDisponibles,
        ]);
    }
}
