<?php

declare(strict_types=1);

use App\Enums\EstadoCita;
use App\Models\Area;
use App\Models\Cita;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Role;

beforeEach(function () {
    session(['_sym_key' => str_repeat('a', 32)]);

    $this->area1 = Area::factory()->create(['nombre' => 'Psicología']);
    $this->area2 = Area::factory()->create(['nombre' => 'Trabajo Social']);
    $this->area3 = Area::factory()->create(['nombre' => 'Odontología']);

    $this->roleCoordinator = Role::firstOrCreate(
        ['slug' => 'area_coordinator'],
        ['nombre' => 'Coordinador', 'is_active' => true]
    );
    $this->roleSpecialist = Role::firstOrCreate(
        ['slug' => 'specialist'],
        ['nombre' => 'Especialista', 'is_active' => true]
    );

    $this->coordinador = Especialista::factory()->create();
    $this->coordinador->roles()->attach($this->roleCoordinator);
    $this->profCoord1 = Profesional::factory()->create([
        'user_id' => $this->coordinador->id,
        'area_id' => $this->area1->id,
    ]);
    Profesional::factory()->create([
        'user_id' => $this->coordinador->id,
        'area_id' => $this->area2->id,
    ]);

    $this->especialistaArea1 = Especialista::factory()->create();
    $this->especialistaArea1->roles()->attach($this->roleSpecialist);
    $this->profEsp1 = Profesional::factory()->create([
        'user_id' => $this->especialistaArea1->id,
        'area_id' => $this->area1->id,
    ]);

    $this->especialistaArea2 = Especialista::factory()->create();
    $this->especialistaArea2->roles()->attach($this->roleSpecialist);
    $this->profEsp2 = Profesional::factory()->create([
        'user_id' => $this->especialistaArea2->id,
        'area_id' => $this->area2->id,
    ]);

    $this->especialistaArea3 = Especialista::factory()->create();
    $this->especialistaArea3->roles()->attach($this->roleSpecialist);
    $this->profEsp3 = Profesional::factory()->create([
        'user_id' => $this->especialistaArea3->id,
        'area_id' => $this->area3->id,
    ]);

    $paciente = Paciente::factory()->create();

    $this->expediente1 = Expediente::factory()->create([
        'paciente_id' => $paciente->codigo,
        'area_id' => $this->area1->id,
        'estado' => 'abierto',
    ]);
    $this->expediente2 = Expediente::factory()->create([
        'paciente_id' => $paciente->codigo,
        'area_id' => $this->area2->id,
        'estado' => 'abierto',
    ]);
    $this->expediente3 = Expediente::factory()->create([
        'paciente_id' => $paciente->codigo,
        'area_id' => $this->area3->id,
        'estado' => 'abierto',
    ]);
});

function crearCitaProgramada(Expediente $expediente, Profesional $profesional, Area $area): Cita
{
    return Cita::create([
        'expediente_id' => $expediente->id,
        'profesional_id' => $profesional->id,
        'area_id' => $area->id,
        'fecha_hora' => now()->addDays(2)->setTime(10, 0),
        'motivo' => 'Cita de prueba multiárea',
        'estado' => EstadoCita::Programada->value,
    ]);
}

it('permite al coordinador multiárea reprogramar en su primera área', function () {
    $cita = crearCitaProgramada($this->expediente1, $this->profEsp1, $this->area1);

    $this->actingAs($this->coordinador)
        ->patch("/expedientes/{$this->expediente1->id}/citas/{$cita->id}/reprogramar", [
            'fecha_hora' => now()->addDays(4)->format('Y-m-d H:i'),
            'motivo_reprogramacion' => 'Reprogramación autorizada en área principal.',
            'acordada_con_paciente' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');
});

it('permite al coordinador multiárea reprogramar en su segunda área', function () {
    $cita = crearCitaProgramada($this->expediente2, $this->profEsp2, $this->area2);

    $this->actingAs($this->coordinador)
        ->patch("/expedientes/{$this->expediente2->id}/citas/{$cita->id}/reprogramar", [
            'fecha_hora' => now()->addDays(5)->format('Y-m-d H:i'),
            'motivo_reprogramacion' => 'Reprogramación autorizada en segunda área.',
            'acordada_con_paciente' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');
});

it('permite al coordinador multiárea cancelar en ambas áreas', function () {
    $cita1 = crearCitaProgramada($this->expediente1, $this->profEsp1, $this->area1);
    $cita2 = crearCitaProgramada($this->expediente2, $this->profEsp2, $this->area2);

    $this->actingAs($this->coordinador)
        ->patch("/expedientes/{$this->expediente1->id}/citas/{$cita1->id}/cancelar", [
            'motivo_cancelacion' => 'Cancelación autorizada área 1.',
        ])
        ->assertRedirect();

    $this->actingAs($this->coordinador)
        ->patch("/expedientes/{$this->expediente2->id}/citas/{$cita2->id}/cancelar", [
            'motivo_cancelacion' => 'Cancelación autorizada área 2.',
        ])
        ->assertRedirect();

    expect($cita1->fresh()->estado)->toBe(EstadoCita::Cancelada->value)
        ->and($cita2->fresh()->estado)->toBe(EstadoCita::Cancelada->value);
});

it('niega al coordinador operar sobre un área no autorizada', function () {
    $cita = crearCitaProgramada($this->expediente3, $this->profEsp3, $this->area3);

    $this->actingAs($this->coordinador)
        ->patch("/expedientes/{$this->expediente3->id}/citas/{$cita->id}/reprogramar", [
            'fecha_hora' => now()->addDays(6)->format('Y-m-d H:i'),
            'motivo_reprogramacion' => 'Intento fuera de área.',
            'acordada_con_paciente' => true,
        ])
        ->assertNotFound();
});

it('limita al especialista a sus propias citas', function () {
    $citaAjena = crearCitaProgramada($this->expediente1, $this->profEsp1, $this->area1);

    $otro = Especialista::factory()->create();
    $otro->roles()->attach($this->roleSpecialist);
    Profesional::factory()->create([
        'user_id' => $otro->id,
        'area_id' => $this->area1->id,
    ]);

    $this->actingAs($otro)
        ->patch("/expedientes/{$this->expediente1->id}/citas/{$citaAjena->id}/reprogramar", [
            'fecha_hora' => now()->addDays(7)->format('Y-m-d H:i'),
            'motivo_reprogramacion' => 'Intento de especialista no asignado.',
            'acordada_con_paciente' => true,
        ])
        ->assertForbidden();
});
