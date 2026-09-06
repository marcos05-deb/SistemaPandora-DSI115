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
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Session is needed for decryption
    session(['_sym_key' => str_repeat('a', 32)]);
    
    $this->area = Area::factory()->create(['nombre' => 'Psicología']);
    $this->otraArea = Area::factory()->create(['nombre' => 'Odontología']);
    
    // Create roles
    $this->roleSpecialist = Role::create([
        'nombre' => 'Specialist',
        'slug' => 'specialist',
        'is_active' => true,
    ]);
    $this->roleCoordinator = Role::create([
        'nombre' => 'Area Coordinator',
        'slug' => 'area_coordinator',
        'is_active' => true,
    ]);
    $this->roleSysadmin = Role::create([
        'nombre' => 'System Admin',
        'slug' => 'sysadmin',
        'is_active' => true,
    ]);

    // Specialist in Psicología
    $this->userSpecialist = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $this->userSpecialist->roles()->attach($this->roleSpecialist->id);
    
    $this->profesionalSpecialist = Profesional::factory()->create([
        'user_id' => $this->userSpecialist->id,
        'area_id' => $this->area->id,
        'especialidad' => 'Psicólogo Clínico',
    ]);

    // Specialist in Odontología
    $this->otraAreaSpecialist = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $this->otraAreaSpecialist->roles()->attach($this->roleSpecialist->id);
    
    $this->profesionalOtraArea = Profesional::factory()->create([
        'user_id' => $this->otraAreaSpecialist->id,
        'area_id' => $this->otraArea->id,
        'especialidad' => 'Odontólogo General',
    ]);

    // Coordinator with access to BOTH areas
    $this->userCoordinator = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $this->userCoordinator->roles()->attach($this->roleCoordinator->id);
    
    // Coordinador necesita su perfil profesional en ambas áreas para que el AreaScope lo reconozca
    Profesional::factory()->create([
        'user_id' => $this->userCoordinator->id,
        'area_id' => $this->area->id,
        'especialidad' => 'Coordinador',
    ]);
    Profesional::factory()->create([
        'user_id' => $this->userCoordinator->id,
        'area_id' => $this->otraArea->id,
        'especialidad' => 'Coordinador',
    ]);

    // Sysadmin
    $this->userSysadmin = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $this->userSysadmin->roles()->attach($this->roleSysadmin->id);

    // Create patient
    $this->paciente = Paciente::factory()->create();
    
    // Create expediente in Psicología
    $this->expedientePsi = Expediente::create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => 'cerrado',
        'fecha_derivacion' => now()->subMonths(2),
    ]);
    
    // Create consulta in Psicología
    $this->consultaPsi = Consulta::create([
        'expediente_id' => $this->expedientePsi->id,
        'profesional_id' => $this->profesionalSpecialist->id,
        'fecha_consulta' => now()->subMonths(1),
        'motivo_consulta' => 'Ansiedad generalizada',
        'notas_clinicas' => 'Se requiere seguimiento.',
        'diagnostico' => 'F41.1',
    ]);

    // Create expediente in Odontología
    $this->expedienteOdo = Expediente::create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->otraArea->id,
        'estado' => 'abierto',
        'fecha_derivacion' => now()->subDays(10),
    ]);
    
    // Create consulta in Odontología
    $this->consultaOdo = Consulta::create([
        'expediente_id' => $this->expedienteOdo->id,
        'profesional_id' => $this->profesionalOtraArea->id,
        'fecha_consulta' => now()->subDays(5),
        'motivo_consulta' => 'Caries dental',
        'notas_clinicas' => 'Obturación realizada.',
        'diagnostico' => 'K02',
    ]);
});

it('prevents sysadmin from accessing medical history', function () {
    $this->actingAs($this->userSysadmin);
    
    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->get(route('pacientes.historial', $this->paciente->codigo));

    $response->assertForbidden();
});

it('allows specialist to see only history from their authorized areas', function () {
    $this->actingAs($this->userSpecialist);
    
    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->get(route('pacientes.historial', $this->paciente->codigo));

    $response->assertOk();
    
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Historial/Show')
        ->has('historial.data', 1)
        ->where('historial.data.0.motivo_consulta', 'Ansiedad generalizada') // Only sees Psychology
        ->where('historial.data.0.area.nombre', 'Psicología')
    );
});

it('allows coordinator to see history from all their authorized areas', function () {
    $this->actingAs($this->userCoordinator);
    
    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->get(route('pacientes.historial', $this->paciente->codigo));

    $response->assertOk();
    
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Historial/Show')
        ->has('historial.data', 2)
        // Check both consultations are present
        ->where('historial.data.0.motivo_consulta', 'Caries dental') // Most recent first (descending)
        ->where('historial.data.1.motivo_consulta', 'Ansiedad generalizada')
    );
});
