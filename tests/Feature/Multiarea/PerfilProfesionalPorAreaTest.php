<?php

declare(strict_types=1);

use App\Enums\EstadoCita;
use App\Models\Area;
use App\Models\Cita;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    session(['_sym_key' => str_repeat('a', 32)]);

    $this->areaA = Area::factory()->create(['nombre' => 'Psicología']);
    $this->areaB = Area::factory()->create(['nombre' => 'Medicina']);

    $role = Role::firstOrCreate(
        ['slug' => 'specialist'],
        ['nombre' => 'Especialista', 'is_active' => true]
    );

    $this->user = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $this->user->roles()->attach($role);

    $this->profA = Profesional::factory()->create([
        'user_id' => $this->user->id,
        'area_id' => $this->areaA->id,
    ]);
    $this->profB = Profesional::factory()->create([
        'user_id' => $this->user->id,
        'area_id' => $this->areaB->id,
    ]);

    $this->paciente = Paciente::factory()->create();
    $this->expedienteA = Expediente::factory()->create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->areaA->id,
        'estado' => 'abierto',
    ]);
    $this->expedienteB = Expediente::factory()->create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->areaB->id,
        'estado' => 'abierto',
    ]);
});

function crearConsultaActiva(Expediente $expediente, Profesional $profesional): Consulta
{
    return Consulta::create([
        'expediente_id' => $expediente->id,
        'profesional_id' => $profesional->id,
        'motivo_consulta' => 'Motivo clínico de prueba',
        'notas_clinicas' => 'Notas clínicas de prueba',
        'diagnostico' => 'Diagnóstico de prueba',
        'plan_atencion' => 'Plan de atención de prueba',
        'tecnica_utilizada' => 'Entrevista',
        'fecha_consulta' => now(),
    ]);
}

it('asocia consultas al perfil profesional del área correspondiente', function () {
    $payload = [
        'motivo_consulta' => 'Consulta clínica con detalle suficiente',
        'notas_clinicas' => 'Notas clínicas suficientes para validación',
        'diagnostico' => 'Observación clínica',
        'plan_atencion' => 'Plan de seguimiento clínico',
        'tecnica_utilizada' => 'Entrevista',
        'fecha_consulta' => now()->toDateString(),
        'evaluacion_inicial' => [
            'apariencia_externa' => 'Normal',
            'voz' => 'Normal',
            'patrones_habla' => 'Normal',
            'expresiones_faciales' => 'Normal',
            'ademanes' => 'Normal',
            'actitudes_tratamiento' => 'Normal',
            'impresion' => 'Normal',
            'plan_tratamiento' => 'Normal',
            'pronostico' => 'Normal',
        ],
    ];

    $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('consultas.store', $this->expedienteA->id), $payload)
        ->assertRedirect();

    $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('consultas.store', $this->expedienteB->id), $payload)
        ->assertRedirect();

    $consultaA = Consulta::withoutGlobalScopes()->where('expediente_id', $this->expedienteA->id)->first();
    $consultaB = Consulta::withoutGlobalScopes()->where('expediente_id', $this->expedienteB->id)->first();

    expect($consultaA->profesional_id)->toBe($this->profA->id)
        ->and($consultaB->profesional_id)->toBe($this->profB->id);
});

it('crea citas con el profesional del área del expediente', function () {
    $consultaA = crearConsultaActiva($this->expedienteA, $this->profA);
    $consultaB = crearConsultaActiva($this->expedienteB, $this->profB);

    $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('citas.store', $this->expedienteA->id), [
            'consulta_id' => $consultaA->id,
            'fecha_hora' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'motivo' => 'Cita área A',
            'acordada_con_paciente' => true,
        ])
        ->assertRedirect();

    $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('citas.store', $this->expedienteB->id), [
            'consulta_id' => $consultaB->id,
            'fecha_hora' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'motivo' => 'Cita área B',
            'acordada_con_paciente' => true,
        ])
        ->assertRedirect();

    expect(Cita::withoutGlobalScopes()->where('expediente_id', $this->expedienteA->id)->value('profesional_id'))
        ->toBe($this->profA->id)
        ->and(Cita::withoutGlobalScopes()->where('expediente_id', $this->expedienteB->id)->value('profesional_id'))
        ->toBe($this->profB->id);
});

it('muestra en agenda las citas de ambos perfiles del especialista', function () {
    Cita::create([
        'expediente_id' => $this->expedienteA->id,
        'profesional_id' => $this->profA->id,
        'area_id' => $this->areaA->id,
        'fecha_hora' => now()->addDay()->setTime(9, 0),
        'motivo' => 'Agenda A',
        'estado' => EstadoCita::Programada->value,
    ]);
    Cita::create([
        'expediente_id' => $this->expedienteB->id,
        'profesional_id' => $this->profB->id,
        'area_id' => $this->areaB->id,
        'fecha_hora' => now()->addDay()->setTime(11, 0),
        'motivo' => 'Agenda B',
        'estado' => EstadoCita::Programada->value,
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->get(route('citas.index', ['vista' => 'lista']));

    $response->assertOk();
    $citas = collect($response->viewData('page')['props']['citas']['data']);
    expect($citas)->toHaveCount(2)
        ->and($citas->pluck('profesional_id')->sort()->values()->all())
        ->toBe(collect([$this->profA->id, $this->profB->id])->sort()->values()->all());
});

it('registra asistencia con el perfil del área de la cita', function () {
    $cita = Cita::create([
        'expediente_id' => $this->expedienteB->id,
        'profesional_id' => $this->profB->id,
        'area_id' => $this->areaB->id,
        'fecha_hora' => now()->subHour(),
        'motivo' => 'Asistencia área B',
        'estado' => EstadoCita::Programada->value,
    ]);

    $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->patch(route('citas.asistencia', [$this->expedienteB->id, $cita->id]), [
            'estado' => EstadoCita::Asistida->value,
        ])
        ->assertRedirect();

    expect($cita->fresh()->registrado_por_profesional_id)->toBe($this->profB->id);
});

it('cierra expediente con el perfil del área correspondiente', function () {
    $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('expedientes.cerrar', $this->expedienteB->id), [
            'resultado_final' => 'Alta clínica documentada con resultado suficiente',
            'motivo_cierre' => 'Cierre clínico documentado con motivo suficiente',
            'confirmacion_irreversible' => true,
        ])
        ->assertRedirect();

    $cerrado = $this->expedienteB->fresh();
    expect($cerrado->estado)->toBe('cerrado')
        ->and($cerrado->cerrado_por_profesional_id)->toBe($this->profB->id);
});

it('permite seleccionar cualquiera de los expedientes activos autorizados', function () {
    $response = $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->get(route('pacientes.show', [
            'carnet' => $this->paciente->carnet,
            'expediente_id' => $this->expedienteB->id,
        ]));

    $response->assertOk();
    $props = $response->viewData('page')['props'];
    expect($props['expedienteSeleccionadoId'])->toBe($this->expedienteB->id)
        ->and(collect($props['expedientesActivos'])->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->expedienteA->id, $this->expedienteB->id])->sort()->values()->all());
});

it('ignora un expediente_id de área no autorizada', function () {
    $areaAjena = Area::factory()->create();
    $expAjeno = Expediente::withoutGlobalScopes()->create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $areaAjena->id,
        'estado' => 'abierto',
        'fecha_derivacion' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->get(route('pacientes.show', [
            'carnet' => $this->paciente->carnet,
            'expediente_id' => $expAjeno->id,
        ]));

    $response->assertOk();
    $props = $response->viewData('page')['props'];
    expect(collect($props['expedientesActivos'])->pluck('id'))->not->toContain($expAjeno->id)
        ->and($props['expedienteSeleccionadoId'])->not->toBe($expAjeno->id);
});
