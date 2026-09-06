<?php

declare(strict_types=1);

use App\Models\Area;
use App\Models\Consulta;
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
    $this->otraArea = Area::factory()->create();
    
    // Create roles
    $this->roleSpecialist = Role::create([
        'nombre' => 'Specialist',
        'slug' => 'specialist',
        'description' => 'Especialista',
        'is_active' => true,
    ]);

    // Create user specialist in area
    $this->userSpecialist = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $this->userSpecialist->roles()->attach($this->roleSpecialist->id);
    
    // Create profesional for user specialist in area
    $this->profesionalSpecialist = Profesional::factory()->create([
        'user_id' => $this->userSpecialist->id,
        'area_id' => $this->area->id,
    ]);
    
    // Create another specialist in another area
    $this->otraAreaSpecialist = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $this->otraAreaSpecialist->roles()->attach($this->roleSpecialist->id);
    
    Profesional::factory()->create([
        'user_id' => $this->otraAreaSpecialist->id,
        'area_id' => $this->otraArea->id,
    ]);

    // Create patient
    $this->paciente = Paciente::factory()->create();
    
    // Create expediente in main area
    $this->expediente = Expediente::create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => 'abierto',
        'fecha_derivacion' => now(),
    ]);
});

it('can registrar una consulta exitosamente y cifrar datos', function () {
    // Arrange
    $this->actingAs($this->userSpecialist);
    
    $payload = [
        'motivo_consulta' => 'Dolor de cabeza crónico',
        'notas_clinicas' => 'El paciente reporta molestias continuas por 3 meses.',
        'diagnostico' => 'Cefalea tensional',
    ];

    // Act
    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('consultas.store', $this->expediente->id), $payload);

    // Assert
    $response->assertRedirect(route('pacientes.show', $this->paciente->carnet));
    $response->assertSessionHas('message', 'Consulta registrada exitosamente.');
    
    expect(Consulta::count())->toBe(1);
    
    $consulta = Consulta::first();
    expect($consulta->expediente_id)->toBe($this->expediente->id)
        ->and($consulta->profesional_id)->toBe($this->profesionalSpecialist->id)
        ->and($consulta->motivo_consulta)->toBe('Dolor de cabeza crónico')
        ->and($consulta->notas_clinicas)->toBe('El paciente reporta molestias continuas por 3 meses.')
        ->and($consulta->diagnostico)->toBe('Cefalea tensional');
        
    // Verify it updated the expediente status
    $this->expediente->refresh();
    expect($this->expediente->estado)->toBe('en_atencion');
    
    // Verify encryption in DB by getting raw attribute
    $rawMotivo = DB::table('consultas')->where('id', $consulta->id)->value('motivo_consulta');
    expect($rawMotivo)->not->toBe('Dolor de cabeza crónico')
        ->and(strlen($rawMotivo))->toBeGreaterThan(30); // It's base64 encoded payload
});

it('rejects cross-area consultation registration', function () {
    // Arrange
    $this->actingAs($this->otraAreaSpecialist);
    
    $payload = [
        'motivo_consulta' => 'Intento ilegal',
        'notas_clinicas' => 'Cruzando áreas',
        'diagnostico' => 'Invalido',
    ];

    // Act
    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('consultas.store', $this->expediente->id), $payload);

    // Assert
    $response->assertNotFound();
});

it('rejects registering consultation if expediente is closed', function () {
    // Arrange
    $this->actingAs($this->userSpecialist);
    $this->expediente->update(['estado' => 'cerrado']);
    
    $payload = [
        'motivo_consulta' => 'Motivo',
        'notas_clinicas' => 'Notas',
        'diagnostico' => 'Diag',
    ];

    // Act
    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('consultas.store', $this->expediente->id), $payload);

    // Assert
    $response->assertForbidden();
    expect(Consulta::count())->toBe(0);
});
