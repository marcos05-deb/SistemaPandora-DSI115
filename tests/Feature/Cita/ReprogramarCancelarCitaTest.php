<?php

declare(strict_types=1);

namespace Tests\Feature\Cita;

use App\Enums\EstadoCita;
use App\Models\Area;
use App\Models\Cita;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    session(['_sym_key' => str_repeat('a', 32)]);

    $this->area = Area::factory()->create();

    $this->roleSpecialist = Role::create(['nombre' => 'Especialista', 'slug' => 'specialist']);

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

afterEach(function () {
    Carbon::setTestNow();
});

it('permite al especialista reprogramar su propia cita con motivo', function () {
    $citaOriginal = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->addDays(1)->setTime(10, 0),
        'motivo' => 'Motivo clínico original confidencial',
        'estado' => EstadoCita::Programada->value,
    ]);

    $nuevaFecha = now()->addDays(3)->setTime(14, 0)->format('Y-m-d H:i');
    $motivo = 'Paciente solicitó cambio por evaluación académica.';

    $response = $this->actingAs($this->especialista)
        ->patch("/expedientes/{$this->expediente->id}/citas/{$citaOriginal->id}/reprogramar", [
            'fecha_hora' => $nuevaFecha,
            'motivo_reprogramacion' => $motivo,
            'acordada_con_paciente' => true,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Cita reprogramada exitosamente.');

    $citaOriginal->refresh();
    expect($citaOriginal->estado)->toBe(EstadoCita::Reprogramada->value)
        ->and($citaOriginal->motivo_reprogramacion)->toBe($motivo)
        ->and($citaOriginal->reprogramado_por_profesional_id)->toBe($this->profesional->id)
        ->and($citaOriginal->fecha_reprogramacion)->not->toBeNull();

    $citaNueva = Cita::where('cita_origen_id', $citaOriginal->id)->first();
    expect($citaNueva)->not->toBeNull()
        ->and($citaNueva->estado)->toBe(EstadoCita::Programada->value)
        ->and($citaNueva->motivo)->toBe('Motivo clínico original confidencial')
        ->and($citaNueva->fecha_hora->format('Y-m-d H:i'))->toBe($nuevaFecha);
});

it('rechaza reprogramar sin confirmación acordada con el paciente', function () {
    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->addDays(1)->setTime(10, 0),
        'motivo' => 'Consulta',
        'estado' => EstadoCita::Programada->value,
    ]);

    $this->actingAs($this->especialista)
        ->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/reprogramar", [
            'fecha_hora' => now()->addDays(4)->format('Y-m-d H:i'),
            'motivo_reprogramacion' => 'Cambio solicitado por el paciente.',
        ])
        ->assertSessionHasErrors('acordada_con_paciente');
});

it('rechaza reprogramar sin motivo', function () {
    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->addDays(1)->setTime(10, 0),
        'motivo' => 'Consulta',
        'estado' => EstadoCita::Programada->value,
    ]);

    $this->actingAs($this->especialista)
        ->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/reprogramar", [
            'fecha_hora' => now()->addDays(4)->format('Y-m-d H:i'),
            'acordada_con_paciente' => true,
        ])
        ->assertSessionHasErrors('motivo_reprogramacion');
});

it('rechaza reprogramar o cancelar una cita cuya hora ya pasó', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-06 16:00:00', config('app.timezone')));

    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => Carbon::parse('2026-09-06 10:00:00', config('app.timezone')),
        'motivo' => 'Cita pasada',
        'estado' => EstadoCita::Programada->value,
    ]);

    $this->actingAs($this->especialista)
        ->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/reprogramar", [
            'fecha_hora' => now()->addDays(2)->format('Y-m-d H:i'),
            'motivo_reprogramacion' => 'Intento sobre cita pasada',
            'acordada_con_paciente' => true,
        ])
        ->assertSessionHasErrors('fecha_hora');

    $this->actingAs($this->especialista)
        ->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/cancelar", [
            'motivo_cancelacion' => 'Intento de cancelar cita pasada',
        ])
        ->assertSessionHasErrors('fecha_hora');

    expect($cita->fresh()->estado)->toBe(EstadoCita::Programada->value);
});

it('permite al especialista cancelar su propia cita futura', function () {
    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->addDays(2)->setTime(9, 0),
        'motivo' => 'Revisión mensual',
        'estado' => EstadoCita::Programada->value,
    ]);

    $response = $this->actingAs($this->especialista)
        ->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/cancelar", [
            'motivo_cancelacion' => 'Paciente llamó para cancelar por viaje inesperado',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Cita cancelada exitosamente.');

    $cita->refresh();
    expect($cita->estado)->toBe(EstadoCita::Cancelada->value)
        ->and($cita->motivo_cancelacion)->toBe('Paciente llamó para cancelar por viaje inesperado');
});

it('rechaza reprogramar si el horario genera conflicto o solapamiento', function () {
    $citaTarget = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->addDays(1)->setTime(10, 0),
        'motivo' => 'Cita original a reprogramar',
        'estado' => EstadoCita::Programada->value,
    ]);

    $fechaConflicto = now()->addDays(3)->setTime(14, 0);

    Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => $fechaConflicto,
        'motivo' => 'Cita que bloquea',
        'estado' => EstadoCita::Programada->value,
    ]);

    $response = $this->actingAs($this->especialista)
        ->patch("/expedientes/{$this->expediente->id}/citas/{$citaTarget->id}/reprogramar", [
            'fecha_hora' => $fechaConflicto->format('Y-m-d H:i'),
            'motivo_reprogramacion' => 'Intento con horario ocupado',
            'acordada_con_paciente' => true,
        ]);

    $response->assertSessionHasErrors('fecha_hora');

    expect($citaTarget->fresh()->estado)->toBe(EstadoCita::Programada->value);
    expect(Cita::where('cita_origen_id', $citaTarget->id)->count())->toBe(0);
});

it('rechaza operacion con 403 si es un especialista no asignado', function () {
    $especialista2 = Especialista::factory()->create();
    $especialista2->roles()->attach($this->roleSpecialist);
    Profesional::factory()->create([
        'user_id' => $especialista2->id,
        'area_id' => $this->area->id,
    ]);

    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->addDays(1)->setTime(10, 0),
        'motivo' => 'Cita de S1',
        'estado' => EstadoCita::Programada->value,
    ]);

    $this->actingAs($especialista2)
        ->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/cancelar", [
            'motivo_cancelacion' => 'Intento de cancelacion no autorizada',
        ])
        ->assertForbidden();
});

it('rechaza reprogramar una cita cancelada', function () {
    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->addDays(1)->setTime(10, 0),
        'motivo' => 'Cita cancelada',
        'estado' => EstadoCita::Cancelada->value,
    ]);

    $this->actingAs($this->especialista)
        ->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/reprogramar", [
            'fecha_hora' => now()->addDays(5)->format('Y-m-d H:i'),
            'motivo_reprogramacion' => 'No debería aplicar',
            'acordada_con_paciente' => true,
        ])
        ->assertSessionHasErrors([
            'estado' => 'Solo se pueden reprogramar citas que estén en estado programada.',
        ]);
});
