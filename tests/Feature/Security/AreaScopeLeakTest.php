<?php

use App\Models\Area;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    session(['_sym_key' => str_repeat('a', 32)]);
    Role::firstOrCreate(['slug' => 'specialist'], ['nombre' => 'Specialist', 'nivel' => 10]);
    Role::firstOrCreate(['slug' => 'psychosocial_referent'], ['nombre' => 'Referente Psicosocial', 'nivel' => 10]);
});

test('paciente show no filtra expedientes cross area y evita data leak', function () {
    // Arrange
    $areaA = Area::factory()->create(['nombre' => 'Area A']);
    $areaB = Area::factory()->create(['nombre' => 'Area B']);

    $specialistA = Especialista::factory()->create(['password' => 'password123', 'is_active' => true]);
    $profesionalA = Profesional::factory()->create(['user_id' => $specialistA->id, 'area_id' => $areaA->id]);
    $specialistA->roles()->attach(Role::where('slug', 'specialist')->first()->id);

    $specialistB = Especialista::factory()->create(['password' => 'password123', 'is_active' => true]);
    $profesionalB = Profesional::factory()->create(['user_id' => $specialistB->id, 'area_id' => $areaB->id]);
    $specialistB->roles()->attach(Role::where('slug', 'specialist')->first()->id);

    $paciente = Paciente::factory()->create();

    $expedienteA = Expediente::factory()->create([
        'paciente_id' => $paciente->codigo,
        'area_id' => $areaA->id,
        'estado' => 'abierto',
        'motivo_consulta' => 'Secreto Area A'
    ]);

    $expedienteB = Expediente::factory()->create([
        'paciente_id' => $paciente->codigo,
        'area_id' => $areaB->id,
        'estado' => 'abierto',
        'motivo_consulta' => 'Secreto Area B'
    ]);

    // Act
    $response = $this->actingAs($specialistA)
        ->get(route('pacientes.show', $paciente->carnet));

    // Assert
    expect($response->status())->toBe(200);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Pacientes/Show')
        ->has('paciente.expedientes', 1)
        ->where('paciente.expedientes.0.area_id', $areaA->id)
    );
});
