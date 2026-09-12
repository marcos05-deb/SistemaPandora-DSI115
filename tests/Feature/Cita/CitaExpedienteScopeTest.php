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

    $this->area = Area::factory()->create();
    $this->roleSpecialist = Role::firstOrCreate(
        ['slug' => 'specialist'],
        ['nombre' => 'Especialista', 'is_active' => true]
    );

    $this->especialista = Especialista::factory()->create();
    $this->especialista->roles()->attach($this->roleSpecialist);
    $this->profesional = Profesional::factory()->create([
        'user_id' => $this->especialista->id,
        'area_id' => $this->area->id,
    ]);

    $this->pacienteA = Paciente::factory()->create();
    $this->pacienteB = Paciente::factory()->create();

    $this->expedienteA = Expediente::factory()->create([
        'paciente_id' => $this->pacienteA->codigo,
        'area_id' => $this->area->id,
        'estado' => 'abierto',
    ]);
    $this->expedienteB = Expediente::factory()->create([
        'paciente_id' => $this->pacienteB->codigo,
        'area_id' => $this->area->id,
        'estado' => 'abierto',
    ]);

    $this->citaA = Cita::create([
        'expediente_id' => $this->expedienteA->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->subHour(),
        'motivo' => 'Cita del expediente A',
        'estado' => EstadoCita::Programada->value,
    ]);
});

it('responde 404 al registrar asistencia con expediente distinto al de la cita', function () {
    $this->actingAs($this->especialista)
        ->patch("/expedientes/{$this->expedienteB->id}/citas/{$this->citaA->id}/asistencia", [
            'estado' => EstadoCita::Asistida->value,
        ])
        ->assertNotFound();

    expect($this->citaA->fresh()->estado)->toBe(EstadoCita::Programada->value);
});

it('responde 404 al reprogramar con expediente distinto y no crea registros', function () {
    $antes = Cita::count();

    $this->actingAs($this->especialista)
        ->patch("/expedientes/{$this->expedienteB->id}/citas/{$this->citaA->id}/reprogramar", [
            'fecha_hora' => now()->addDays(3)->format('Y-m-d H:i'),
            'motivo_reprogramacion' => 'Intento de mezcla entre expedientes autorizados.',
            'acordada_con_paciente' => true,
        ])
        ->assertNotFound();

    expect(Cita::count())->toBe($antes)
        ->and($this->citaA->fresh()->estado)->toBe(EstadoCita::Programada->value);
});

it('responde 404 al cancelar con expediente distinto y no modifica la cita', function () {
    $this->actingAs($this->especialista)
        ->patch("/expedientes/{$this->expedienteB->id}/citas/{$this->citaA->id}/cancelar", [
            'motivo_cancelacion' => 'Intento de cancelación cruzada entre expedientes.',
        ])
        ->assertNotFound();

    expect($this->citaA->fresh()->estado)->toBe(EstadoCita::Programada->value);
});
