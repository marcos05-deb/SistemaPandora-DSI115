<?php

use App\Models\Especialista;
use App\Models\Area;
use App\Models\Profesional;
use App\Models\Paciente;
use App\Models\Expediente;
use App\Models\Cita;
use function Pest\Laravel\{actingAs, get};

beforeEach(function () {
    // Inyectar clave simétrica de sesión para que funcione el cifrado de DB (EncryptedFieldCast)
    session(['_sym_key' => str_repeat('a', 32)]);

    // Área Psicología
    $this->areaPsicologia = Area::factory()->create(['nombre' => 'Psicología']);
    
    // Área Médica
    $this->areaMedica = Area::factory()->create(['nombre' => 'Medicina']);

    $roleCoordinator = \App\Models\Role::firstOrCreate(['slug' => 'area_coordinator'], ['nombre' => 'Coordinador', 'nivel_acceso' => 2]);
    $roleSpecialist = \App\Models\Role::firstOrCreate(['slug' => 'specialist'], ['nombre' => 'Especialista', 'nivel_acceso' => 1]);
    $roleSysadmin = \App\Models\Role::firstOrCreate(['slug' => 'sysadmin'], ['nombre' => 'Sysadmin', 'nivel_acceso' => 99]);

    // Coordinador de Psicología
    $this->coordinadorPsicologia = Especialista::factory()->create();
    $this->coordinadorPsicologia->roles()->attach($roleCoordinator);
    $this->profesionalCoordPsico = Profesional::factory()->create(['user_id' => $this->coordinadorPsicologia->id, 'area_id' => $this->areaPsicologia->id]);

    // Especialista Psicología 1
    $this->especialistaPsicologia1 = Especialista::factory()->create();
    $this->especialistaPsicologia1->roles()->attach($roleSpecialist);
    $this->profesionalPsico1 = Profesional::factory()->create(['user_id' => $this->especialistaPsicologia1->id, 'area_id' => $this->areaPsicologia->id]);

    // Especialista Psicología 2
    $this->especialistaPsicologia2 = Especialista::factory()->create();
    $this->especialistaPsicologia2->roles()->attach($roleSpecialist);
    $this->profesionalPsico2 = Profesional::factory()->create(['user_id' => $this->especialistaPsicologia2->id, 'area_id' => $this->areaPsicologia->id]);

    // Especialista Médica
    $this->especialistaMedica = Especialista::factory()->create();
    $this->especialistaMedica->roles()->attach($roleSpecialist);
    $this->profesionalMedico = Profesional::factory()->create(['user_id' => $this->especialistaMedica->id, 'area_id' => $this->areaMedica->id]);

    // Sysadmin
    $this->sysadmin = Especialista::factory()->create();
    $this->sysadmin->roles()->attach($roleSysadmin);

    // Datos base (Pacientes y Expedientes)
    $paciente = Paciente::factory()->create();
    $this->expedientePsico = Expediente::factory()->create([
        'paciente_id' => $paciente->codigo,
        'area_id' => $this->areaPsicologia->id,
    ]);
    
    $paciente2 = Paciente::factory()->create();
    $this->expedienteMedico = Expediente::factory()->create([
        'paciente_id' => $paciente2->codigo,
        'area_id' => $this->areaMedica->id,
    ]);

    // Citas para Psico 1 (Hoy)
    $this->citaPsico1 = Cita::factory()->create([
        'expediente_id' => $this->expedientePsico->id,
        'profesional_id' => $this->profesionalPsico1->id,
        'area_id' => $this->areaPsicologia->id,
        'fecha_hora' => now()->addHours(2),
        'estado' => 'programada'
    ]);

    // Citas para Psico 2 (Ayer)
    $this->citaPsico2 = Cita::factory()->create([
        'expediente_id' => $this->expedientePsico->id,
        'profesional_id' => $this->profesionalPsico2->id,
        'area_id' => $this->areaPsicologia->id,
        'fecha_hora' => now()->subDay(),
        'estado' => 'asistida'
    ]);

    // Cita Médica
    $this->citaMedica = Cita::factory()->create([
            'expediente_id' => $this->expedienteMedico->id,
            'profesional_id' => $this->profesionalMedico->id,
            'area_id' => $this->areaMedica->id,
            'fecha_hora' => now()->addHours(3),
            'estado' => 'programada'
        ]);
});

it('permite al especialista ver solo sus propias citas', function () {
    actingAs($this->especialistaPsicologia1);

    $response = get(route('citas.index'));

    $response->assertOk();
    
    // Verificamos que solo devuelva 1 cita (la suya)
    $citas = $response->viewData('page')['props']['citas']['data'];
    expect($citas)->toHaveCount(1)
        ->and($citas[0]['id'])->toBe($this->citaPsico1->id)
        ->and($citas[0]['profesional_id'])->toBe($this->profesionalPsico1->id);
});

it('permite al coordinador ver citas de toda el area y aplicar filtros de estado y fecha', function () {
    actingAs($this->coordinadorPsicologia);

    // Sin filtros: debe ver las de Psico 1 y Psico 2 (default = todos)
    $response = get(route('citas.index'));
    $response->assertOk();
    $citas = $response->viewData('page')['props']['citas']['data'];
    expect($citas)->toHaveCount(2);

    // Filtro por Especialista 1
    $response = get(route('citas.index', ['especialista_id' => $this->profesionalPsico1->id]));
    $response->assertOk();
    $citas = $response->viewData('page')['props']['citas']['data'];
    expect($citas)->toHaveCount(1)
        ->and($citas[0]['id'])->toBe($this->citaPsico1->id);

    // Filtro por Estado Asistida / resultado asistencia
    $response = get(route('citas.index', ['estado' => 'asistida']));
    $response->assertOk();
    $citas = $response->viewData('page')['props']['citas']['data'];
    expect($citas)->toHaveCount(1)
        ->and($citas[0]['id'])->toBe($this->citaPsico2->id);

    $response = get(route('citas.index', ['resultado_asistencia' => 'asistida']));
    $response->assertOk();
    expect($response->viewData('page')['props']['citas']['data'])->toHaveCount(1);
});

it('soporta vista diaria y semanal', function () {
    actingAs($this->coordinadorPsicologia);

    $response = get(route('citas.index', [
        'vista' => 'diaria',
        'referencia' => now()->toDateString(),
    ]));
    $response->assertOk();
    $citas = $response->viewData('page')['props']['citas']['data'];
    expect($citas)->toHaveCount(1)
        ->and($citas[0]['id'])->toBe($this->citaPsico1->id);

    $response = get(route('citas.index', [
        'vista' => 'semanal',
        'referencia' => now()->toDateString(),
    ]));
    $response->assertOk();
    expect($response->viewData('page')['props']['filtros']['vista'])->toBe('semanal');
});

it('bloquea al sysadmin de acceder a las citas', function () {
    actingAs($this->sysadmin);

    $response = get(route('citas.index'));
    
    // Asumimos comportamiento estandar: redirección al admin dashboard o 403.
    $response->assertStatus(403);
});

it('impide que filtros bypaseen el AreaScope', function () {
    actingAs($this->coordinadorPsicologia);

    // El coordinador de Psicologia intenta filtrar citas buscando al profesionalMedico (de otra área)
    $response = get(route('citas.index', ['especialista_id' => $this->profesionalMedico->id]));
    
    $response->assertOk();
    $citas = $response->viewData('page')['props']['citas']['data'];
    expect($citas)->toHaveCount(0);
});

it('falla con validacion si el filtro de especialista no es un UUID valido', function () {
    actingAs($this->coordinadorPsicologia);

    $response = get(route('citas.index', ['especialista_id' => 'no-es-un-uuid']));
    
    $response->assertSessionHasErrors('especialista_id');
});

it('no expone datos de privacidad prohibidos en el resource (PHi)', function () {
    actingAs($this->coordinadorPsicologia);

    $response = get(route('citas.index'));
    $response->assertOk();
    $citas = $response->viewData('page')['props']['citas']['data'];
    
    // Verificamos que contenga paciente y código, PERO NO el nombre_completo encriptado.
    expect($citas[0])->toHaveKey('paciente');
    expect($citas[0]['paciente'])->toHaveKey('codigo');
    expect($citas[0]['paciente'])->not->toHaveKey('nombre_completo');
});
