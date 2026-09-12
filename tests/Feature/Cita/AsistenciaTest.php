<?php

declare(strict_types=1);

use App\Enums\EstadoCita;
use App\Events\CitaAusenciaRegistrada;
use App\Models\Area;
use App\Models\Cita;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Models\Audit;

beforeEach(function () {
    session(['_sym_key' => str_repeat('a', 32)]);

    $this->area = Area::factory()->create(['nombre' => 'Psicología']);

    $role = Role::firstOrCreate(
        ['slug' => 'specialist'],
        ['nombre' => 'Specialist', 'description' => 'Especialista', 'is_active' => true]
    );

    $this->user = Especialista::factory()->create(['is_active' => true]);
    $this->user->roles()->attach($role->id);

    $this->profesional = Profesional::factory()->create([
        'user_id' => $this->user->id,
        'area_id' => $this->area->id,
    ]);

    $this->otherUser = Especialista::factory()->create(['is_active' => true]);
    $this->otherUser->roles()->attach($role->id);
    $this->otherProfesional = Profesional::factory()->create([
        'user_id' => $this->otherUser->id,
        'area_id' => $this->area->id,
    ]);

    $this->paciente = Paciente::factory()->create();
    $this->expediente = Expediente::factory()->create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => 'abierto',
    ]);
});

it('permite marcar asistencia de una cita ya iniciada y crea audit log', function () {
    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->subHour(),
        'motivo' => 'Consulta general',
        'estado' => EstadoCita::Programada->value,
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/asistencia", [
            'estado' => EstadoCita::Asistida->value,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('variant', 'success');

    $cita->refresh();
    expect($cita->estado)->toBe(EstadoCita::Asistida->value)
        ->and($cita->registrado_por_profesional_id)->toBe($this->profesional->id)
        ->and($cita->fecha_registro_asistencia)->not->toBeNull();

    $audit = Audit::where('auditable_type', Cita::class)
        ->where('auditable_id', $cita->id)
        ->where('event', 'updated')
        ->first();

    expect($audit)->not->toBeNull();
});

it('rechaza asistencia el mismo día antes de la hora programada', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-06 09:00:00', config('app.timezone')));

    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => Carbon::parse('2026-09-06 15:00:00', config('app.timezone')),
        'motivo' => 'Consulta general',
        'estado' => EstadoCita::Programada->value,
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/asistencia", [
            'estado' => EstadoCita::Ausente->value,
        ]);

    $response->assertSessionHasErrors([
        'fecha_hora' => 'No se puede registrar asistencia antes de la hora programada de la cita.',
    ]);
    expect($cita->fresh()->estado)->toBe(EstadoCita::Programada->value);

    Carbon::setTestNow();
});

it('rechaza asistencia de una cita en días futuros', function () {
    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->addDays(2),
        'motivo' => 'Consulta general',
        'estado' => EstadoCita::Programada->value,
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/asistencia", [
            'estado' => EstadoCita::Asistida->value,
        ]);

    $response->assertSessionHasErrors('fecha_hora');
    expect($cita->fresh()->estado)->toBe(EstadoCita::Programada->value);
});

it('falla con 403 si un especialista no asignado intenta marcar asistencia', function () {
    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->otherProfesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->subDays(1),
        'motivo' => 'Consulta general',
        'estado' => EstadoCita::Programada->value,
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/asistencia", [
            'estado' => EstadoCita::Asistida->value,
        ]);

    $response->assertForbidden();
});

it('falla con 422 si la cita no esta programada', function () {
    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->subDays(1),
        'motivo' => 'Consulta general',
        'estado' => EstadoCita::Cancelada->value,
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/asistencia", [
            'estado' => EstadoCita::Asistida->value,
        ]);

    $response->assertSessionHasErrors([
        'estado' => 'Solo se puede registrar asistencia de citas programadas.',
    ]);
});

it('dispara evento de ausencia para estadísticas preventivas', function () {
    Event::fake([CitaAusenciaRegistrada::class]);

    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->subHour(),
        'motivo' => 'Consulta general',
        'estado' => EstadoCita::Programada->value,
    ]);

    $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/asistencia", [
            'estado' => EstadoCita::Ausente->value,
        ])
        ->assertRedirect();

    Event::assertDispatched(CitaAusenciaRegistrada::class, function (CitaAusenciaRegistrada $event) use ($cita) {
        return $event->cita->id === $cita->id
            && $event->cita->estado === EstadoCita::Ausente->value;
    });
});

it('impide doble registro de asistencia', function () {
    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->subHour(),
        'motivo' => 'Consulta general',
        'estado' => EstadoCita::Programada->value,
    ]);

    $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/asistencia", [
            'estado' => EstadoCita::Asistida->value,
        ])
        ->assertRedirect();

    $response = $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/asistencia", [
            'estado' => EstadoCita::Ausente->value,
        ]);

    $response->assertSessionHasErrors('estado');
    expect($cita->fresh()->estado)->toBe(EstadoCita::Asistida->value);
});
