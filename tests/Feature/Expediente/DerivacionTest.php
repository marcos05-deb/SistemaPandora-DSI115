<?php

declare(strict_types=1);

use App\Models\Area;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Role;
use App\Models\Especialista;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    session(['_sym_key' => str_repeat('a', 32)]);
    $this->area = Area::factory()->create();
    
    // Create roles
    $this->roleReferente = Role::create([
        'nombre' => 'Psychosocial Referent',
        'slug' => 'psychosocial_referent',
        'description' => 'Referente psicosocial',
        'is_active' => true,
    ]);
    
    $this->roleSpecialist = Role::create([
        'nombre' => 'Specialist',
        'slug' => 'specialist',
        'description' => 'Especialista',
        'is_active' => true,
    ]);

    // Create user referent
    $this->userReferent = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $this->userReferent->roles()->attach($this->roleReferente->id);
    
    // Create profesional for user referent
    $this->profesionalReferent = Profesional::factory()->create([
        'user_id' => $this->userReferent->id,
    ]);

    // Create user specialist
    $this->userSpecialist = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $this->userSpecialist->roles()->attach($this->roleSpecialist->id);

    // Create patient
    $this->paciente = Paciente::factory()->create([
        'creado_por_profesional_id' => $this->profesionalReferent->id,
    ]);
});

it('can derivar a patient to an area', function () {
    // Arrange
    $this->actingAs($this->userReferent);

    // Act
    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('pacientes.derivar.store', $this->paciente->codigo), [
        'area_id' => $this->area->id,
    ]);

    // Assert
    $response->assertRedirect(route('pacientes.index'));
    $response->assertSessionHas('success', 'Paciente derivado exitosamente.');
    
    expect(Expediente::count())->toBe(1);
    
    $expediente = Expediente::first();
    expect($expediente->paciente_id)->toBe($this->paciente->codigo)
        ->and($expediente->area_id)->toBe($this->area->id)
        ->and($expediente->estado)->toBe('abierto')
        ->and($expediente->derivado_por_profesional_id)->toBe($this->profesionalReferent->id)
        ->and($expediente->fecha_derivacion)->not->toBeNull();
});

it('cannot derivar if already open in the same area', function () {
    // Arrange
    $this->actingAs($this->userReferent);
    
    Expediente::create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => 'abierto',
        'derivado_por_profesional_id' => $this->profesionalReferent->id,
        'fecha_derivacion' => now(),
    ]);

    // Act
    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('pacientes.derivar.store', $this->paciente->codigo), [
        'area_id' => $this->area->id,
    ]);

    // Assert
    $response->assertSessionHasErrors(['area_id' => 'El paciente ya tiene un expediente abierto en esta área.']);
    
    expect(Expediente::count())->toBe(1); // No new one created
});

it('forbids non psychosocial_referent to derivar', function () {
    // Arrange
    $this->actingAs($this->userSpecialist);

    // Act
    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('pacientes.derivar.store', $this->paciente->codigo), [
        'area_id' => $this->area->id,
    ]);

    // Assert
    $response->assertForbidden();
});
