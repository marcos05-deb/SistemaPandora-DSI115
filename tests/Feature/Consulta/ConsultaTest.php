<?php

declare(strict_types=1);

use App\Models\Area;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function evaluacionInicialValida(): array
{
    return [
        'apariencia_externa' => 'Normal',
        'voz' => 'Normal',
        'patrones_habla' => 'Normal',
        'expresiones_faciales' => 'Normal',
        'ademanes' => 'Normal',
        'actitudes_tratamiento' => 'Normal',
        'impresion' => 'Normal',
        'plan_tratamiento' => 'Normal',
        'pronostico' => 'Normal',
    ];
}

function payloadConsulta(array $overrides = []): array
{
    return array_merge([
        'motivo_consulta' => 'Dolor de cabeza crónico',
        'notas_clinicas' => 'El paciente reporta molestias continuas por 3 meses.',
        'diagnostico' => 'Cefalea tensional',
        'plan_atencion' => 'Seguimiento quincenal con higiene del sueño.',
        'tecnica_utilizada' => 'Entrevista clínica',
        'fecha_consulta' => now()->toDateString(),
        'evaluacion_inicial' => evaluacionInicialValida(),
    ], $overrides);
}

beforeEach(function () {
    session(['_sym_key' => str_repeat('a', 32)]);
    $this->area = Area::factory()->create();
    $this->otraArea = Area::factory()->create();

    $this->roleSpecialist = Role::create([
        'nombre' => 'Specialist',
        'slug' => 'specialist',
        'description' => 'Especialista',
        'is_active' => true,
    ]);

    $this->userSpecialist = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $this->userSpecialist->roles()->attach($this->roleSpecialist->id);

    $this->profesionalSpecialist = Profesional::factory()->create([
        'user_id' => $this->userSpecialist->id,
        'area_id' => $this->area->id,
    ]);

    $this->otraAreaSpecialist = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $this->otraAreaSpecialist->roles()->attach($this->roleSpecialist->id);

    Profesional::factory()->create([
        'user_id' => $this->otraAreaSpecialist->id,
        'area_id' => $this->otraArea->id,
    ]);

    $this->paciente = Paciente::factory()->create();

    $this->expediente = Expediente::create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => 'abierto',
        'fecha_derivacion' => now(),
    ]);
});

it('can registrar una consulta exitosamente y cifrar datos', function () {
    $this->actingAs($this->userSpecialist);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('consultas.store', $this->expediente->id), payloadConsulta());

    $response->assertRedirect(route('pacientes.show', $this->paciente->carnet));
    $response->assertSessionHas('message', 'Consulta registrada exitosamente.');

    expect(Consulta::count())->toBe(1);

    $consulta = Consulta::first();
    expect($consulta->expediente_id)->toBe($this->expediente->id)
        ->and($consulta->profesional_id)->toBe($this->profesionalSpecialist->id)
        ->and($consulta->motivo_consulta)->toBe('Dolor de cabeza crónico')
        ->and($consulta->notas_clinicas)->toBe('El paciente reporta molestias continuas por 3 meses.')
        ->and($consulta->diagnostico)->toBe('Cefalea tensional')
        ->and($consulta->plan_atencion)->toBe('Seguimiento quincenal con higiene del sueño.');

    $this->expediente->refresh();
    expect($this->expediente->estado)->toBe('en_atencion');

    $rawMotivo = DB::table('consultas')->where('id', $consulta->id)->value('motivo_consulta');
    $rawPlan = DB::table('consultas')->where('id', $consulta->id)->value('plan_atencion');

    expect($rawMotivo)->not->toBe('Dolor de cabeza crónico')
        ->and(strlen($rawMotivo))->toBeGreaterThan(30)
        ->and($rawPlan)->not->toContain('higiene del sueño')
        ->and(base64_decode($rawPlan, true))->not->toBeFalse();
});

it('rejects cross-area consultation registration', function () {
    $this->actingAs($this->otraAreaSpecialist);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('consultas.store', $this->expediente->id), payloadConsulta());

    $response->assertNotFound();
});

it('rejects registering consultation if expediente is closed', function () {
    $this->actingAs($this->userSpecialist);
    $this->expediente->update(['estado' => 'cerrado']);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('consultas.store', $this->expediente->id), payloadConsulta());

    $response->assertForbidden();
    expect(Consulta::count())->toBe(0);
});

it('policy denies create for specialist of another area even if expediente is known', function () {
    expect($this->otraAreaSpecialist->can('create', [Consulta::class, $this->expediente]))->toBeFalse()
        ->and($this->userSpecialist->can('create', [Consulta::class, $this->expediente]))->toBeTrue();
});

it('forbids psychosocial referent from registering a consultation', function () {
    $roleReferente = Role::create([
        'nombre' => 'Psychosocial Referent',
        'slug' => 'psychosocial_referent',
        'description' => 'Referente',
        'is_active' => true,
    ]);

    $referente = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $referente->roles()->attach($roleReferente->id);
    Profesional::factory()->create([
        'user_id' => $referente->id,
        'area_id' => $this->area->id,
    ]);

    $this->actingAs($referente);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('consultas.store', $this->expediente->id), payloadConsulta([
            'motivo_consulta' => 'Intento no autorizado',
            'notas_clinicas' => 'No debería persistir',
            'diagnostico' => 'N/A',
            'tecnica_utilizada' => 'N/A',
        ]));

    $response->assertForbidden();
    expect(Consulta::count())->toBe(0);
});

it('allows consultation with only diagnostico (sin observación)', function () {
    $this->actingAs($this->userSpecialist);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('consultas.store', $this->expediente->id), payloadConsulta([
            'motivo_consulta' => 'Seguimiento breve',
            'diagnostico' => 'Ansiedad situacional',
            'notas_clinicas' => '',
            'tecnica_utilizada' => 'Entrevista',
        ]));

    $response->assertRedirect();
    expect(Consulta::count())->toBe(1)
        ->and(Consulta::first()->diagnostico)->toBe('Ansiedad situacional');
});

it('allows consultation with only observacion (sin diagnóstico)', function () {
    $this->actingAs($this->userSpecialist);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('consultas.store', $this->expediente->id), payloadConsulta([
            'motivo_consulta' => 'Seguimiento breve',
            'diagnostico' => '',
            'notas_clinicas' => 'Paciente colaborador, sin cambios relevantes.',
            'tecnica_utilizada' => 'Entrevista',
        ]));

    $response->assertRedirect();
    expect(Consulta::count())->toBe(1)
        ->and(Consulta::first()->notas_clinicas)->toBe('Paciente colaborador, sin cambios relevantes.');
});

it('rejects consultation without diagnostico nor observacion', function () {
    $this->actingAs($this->userSpecialist);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('consultas.create', $this->expediente->id))
        ->post(route('consultas.store', $this->expediente->id), payloadConsulta([
            'motivo_consulta' => 'Seguimiento breve',
            'diagnostico' => '',
            'notas_clinicas' => '',
            'tecnica_utilizada' => 'Entrevista',
        ]));

    $response->assertSessionHasErrors(['diagnostico', 'notas_clinicas']);
    expect(Consulta::count())->toBe(0);
});

it('requires plan_atencion on every consultation', function () {
    $this->actingAs($this->userSpecialist);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('consultas.create', $this->expediente->id))
        ->post(route('consultas.store', $this->expediente->id), payloadConsulta([
            'plan_atencion' => '',
        ]));

    $response->assertSessionHasErrors('plan_atencion');
    expect(Consulta::count())->toBe(0);
});

it('rejects plan_atencion shorter than minimum', function () {
    $this->actingAs($this->userSpecialist);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('consultas.create', $this->expediente->id))
        ->post(route('consultas.store', $this->expediente->id), payloadConsulta([
            'plan_atencion' => 'corto',
        ]));

    $response->assertSessionHasErrors('plan_atencion');
    expect(Consulta::count())->toBe(0);
});

it('stores the selected fecha_consulta separately from created_at', function () {
    $this->actingAs($this->userSpecialist);
    $fechaClinica = now()->subDays(3)->toDateString();

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('consultas.store', $this->expediente->id), payloadConsulta([
            'fecha_consulta' => $fechaClinica,
        ]));

    $response->assertRedirect();
    $consulta = Consulta::first();

    expect($consulta->fecha_consulta->toDateString())->toBe($fechaClinica)
        ->and($consulta->created_at->toDateString())->toBe(now()->toDateString());
});

it('rejects future fecha_consulta', function () {
    $this->actingAs($this->userSpecialist);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('consultas.create', $this->expediente->id))
        ->post(route('consultas.store', $this->expediente->id), payloadConsulta([
            'fecha_consulta' => now()->addDay()->toDateString(),
        ]));

    $response->assertSessionHasErrors('fecha_consulta');
    expect(Consulta::count())->toBe(0);
});
