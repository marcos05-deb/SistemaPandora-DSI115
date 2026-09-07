<?php

namespace Tests\Feature\Cita;

use App\Models\Area;
use App\Models\Cita;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    session(['_sym_key' => str_repeat('a', 32)]);

    $this->area = Area::factory()->create();
    
    $this->roleSpecialist = Role::create(['nombre' => 'Especialista', 'slug' => 'specialist']);
    $this->roleCoordinator = Role::create(['nombre' => 'Coordinador de Área', 'slug' => 'area_coordinator']);

    $this->especialista = Especialista::factory()->create();
    $this->especialista->roles()->attach($this->roleSpecialist);
    $this->profesional = Profesional::factory()->create([
        'user_id' => $this->especialista->id,
        'area_id' => $this->area->id,
    ]);

    $this->paciente = Paciente::factory()->create();
    $this->expediente = Expediente::factory()->create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => 'abierto',
    ]);
});

it('permite al especialista reprogramar su propia cita clonando el motivo', function () {
    $citaOriginal = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->addDays(1)->setTime(10, 0),
        'motivo' => 'Motivo clínico original confidencial',
        'estado' => 'programada',
    ]);

    $nuevaFecha = now()->addDays(3)->setTime(14, 0)->format('Y-m-d H:i');

    $response = $this->actingAs($this->especialista)
        ->patch("/expedientes/{$this->expediente->id}/citas/{$citaOriginal->id}/reprogramar", [
            'fecha_hora' => $nuevaFecha,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Cita reprogramada exitosamente.');

    // Verificar cita original
    $citaOriginal->refresh();
    expect($citaOriginal->estado)->toBe('reprogramada');

    // Verificar cita nueva
    $citaNueva = Cita::where('cita_origen_id', $citaOriginal->id)->first();
    expect($citaNueva)->not->toBeNull()
        ->and($citaNueva->estado)->toBe('programada')
        ->and($citaNueva->motivo)->toBe('Motivo clínico original confidencial') // Descifrado via cast
        ->and($citaNueva->fecha_hora->format('Y-m-d H:i'))->toBe($nuevaFecha);
});

it('permite al especialista cancelar su propia cita', function () {
    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->addDays(2)->setTime(9, 0),
        'motivo' => 'Revisión mensual',
        'estado' => 'programada',
    ]);

    $response = $this->actingAs($this->especialista)
        ->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/cancelar", [
            'motivo_cancelacion' => 'Paciente llamó para cancelar por viaje inesperado',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Cita cancelada exitosamente.');

    $cita->refresh();
    expect($cita->estado)->toBe('cancelada')
        ->and($cita->motivo_cancelacion)->toBe('Paciente llamó para cancelar por viaje inesperado');
});

it('rechaza reprogramar si el horario genera conflicto (constraint parcial db)', function () {
    // Cita que vamos a reprogramar
    $citaTarget = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->addDays(1)->setTime(10, 0),
        'motivo' => 'Cita original a reprogramar',
        'estado' => 'programada',
    ]);

    $fechaConflicto = now()->addDays(3)->setTime(14, 0);

    // Cita que ya ocupa ese espacio
    Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => $fechaConflicto,
        'motivo' => 'Cita que bloquea',
        'estado' => 'programada',
    ]);

    $response = $this->actingAs($this->especialista)
        ->patch("/expedientes/{$this->expediente->id}/citas/{$citaTarget->id}/reprogramar", [
            'fecha_hora' => $fechaConflicto->format('Y-m-d H:i'),
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors([
        'fecha_hora' => 'El horario seleccionado ya no está disponible o existe un conflicto en la agenda del especialista.',
    ]);

    // Verificar que no se alteró la base de datos
    $citaTarget->refresh();
    expect($citaTarget->estado)->toBe('programada');
    expect(Cita::where('cita_origen_id', $citaTarget->id)->count())->toBe(0);
});

it('rechaza operacion con 403 si es un especialista de la misma area pero no asignado', function () {
    $especialista2 = Especialista::factory()->create();
    $especialista2->roles()->attach($this->roleSpecialist);
    $profesional2 = Profesional::factory()->create([
        'user_id' => $especialista2->id,
        'area_id' => $this->area->id, // MISMA AREA
    ]);

    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id, // Asignada a Especialista 1
        'area_id' => $this->area->id,
        'fecha_hora' => now()->addDays(1)->setTime(10, 0),
        'motivo' => 'Cita de S1',
        'estado' => 'programada',
    ]);

    $response = $this->actingAs($especialista2)
        ->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/cancelar", [
            'motivo_cancelacion' => 'Intento de cancelacion no autorizada',
        ]);

    $response->assertForbidden(); // 403
});

it('rechaza reprogramar una cita que ya esta cancelada con error 422', function () {
    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->addDays(1)->setTime(10, 0),
        'motivo' => 'Cita cancelada',
        'estado' => 'cancelada', // Estado invalido para reprogramar
    ]);

    $response = $this->actingAs($this->especialista)
        ->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/reprogramar", [
            'fecha_hora' => now()->addDays(5)->format('Y-m-d H:i'),
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors([
        'estado' => 'Solo se pueden reprogramar citas que estén en estado programada.',
    ]); // 422 manejado por validation message
});
