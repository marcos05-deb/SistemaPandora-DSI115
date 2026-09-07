<?php

namespace Tests\Feature\Cita;

use App\Models\Area;
use App\Models\Cita;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use OwenIt\Auditing\Models\Audit;

beforeEach(function () {
    session(['_sym_key' => base64_encode(random_bytes(32))]);
    \Illuminate\Support\Facades\Config::set('audit.console', true);
    
    $this->area = Area::factory()->create(['nombre' => 'Psicología']);
    $this->otherArea = Area::factory()->create(['nombre' => 'Nutrición']);

    $role = \App\Models\Role::firstOrCreate(['slug' => 'specialist'], ['nombre' => 'Specialist', 'descripcion' => 'Especialista']);
    
    $this->user = Especialista::factory()->create();
    $this->user->roles()->attach($role);
    
    \Illuminate\Support\Facades\DB::table('profesionales')->insert([
        'user_id' => $this->user->id,
        'area_id' => $this->area->id,
        'especialidad' => 'Psicología Clínica',
        'numero_registro' => '12345',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    $this->profesional = Profesional::where('user_id', $this->user->id)->first();

    $this->otherUser = Especialista::factory()->create();
    $this->otherUser->roles()->attach($role);
    
    \Illuminate\Support\Facades\DB::table('profesionales')->insert([
        'user_id' => $this->otherUser->id,
        'area_id' => $this->area->id,
        'especialidad' => 'Psicología Clínica',
        'numero_registro' => '67890',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    $this->otherProfesional = Profesional::where('user_id', $this->otherUser->id)->first();

    $this->paciente = Paciente::factory()->create();
    $this->expediente = Expediente::factory()->create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => 'abierto',
    ]);
});

it('permite marcar asistencia y crea audit log', function () {
    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->subDays(1),
        'motivo' => 'Consulta general',
        'estado' => 'programada',
    ]);

    $response = $this->actingAs($this->user)->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/asistencia", [
        'estado' => 'asistio',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('message', 'Asistencia registrada exitosamente.');
    $response->assertSessionHas('variant', 'success');

    $this->assertDatabaseHas('citas', [
        'id' => $cita->id,
        'estado' => 'asistio',
        'registrado_por_profesional_id' => $this->profesional->id,
    ]);

    $cita->refresh();
    expect($cita->fecha_registro_asistencia)->not->toBeNull();

    // Verify audit log
    $audit = Audit::where('auditable_type', Cita::class)
        ->where('auditable_id', $cita->id)
        ->where('event', 'updated')
        ->first();
    
    if (!$audit) {
        $allEvents = Audit::where('auditable_type', Cita::class)->where('auditable_id', $cita->id)->pluck('event');
        dd("Expected 'updated' event but got only: " . $allEvents->implode(', '));
    }
    
    expect($audit)->not->toBeNull();
    expect($audit->event)->toBe('updated');
});

it('permite marcar asistencia el mismo día de la cita incluso antes de su hora programada', function () {
    Carbon::setTestNow('2026-09-06 09:00:00');
    
    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => Carbon::parse('2026-09-06 15:00:00'),
        'motivo' => 'Consulta general',
        'estado' => 'programada',
    ]);

    $response = $this->actingAs($this->user)->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/asistencia", [
        'estado' => 'no_asistio',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('message', 'Asistencia registrada exitosamente.');

    $this->assertDatabaseHas('citas', [
        'id' => $cita->id,
        'estado' => 'no_asistio',
    ]);
    
    Carbon::setTestNow();
});

it('falla con 403 si un especialista intenta marcar asistencia de una cita donde no esta asignado (Compliance V4)', function () {
    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->otherProfesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->subDays(1),
        'motivo' => 'Consulta general',
        'estado' => 'programada',
    ]);

    $response = $this->actingAs($this->user)->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/asistencia", [
        'estado' => 'asistio',
    ]);

    $response->assertForbidden();
});

it('falla con 422 si la cita no esta en estado programada (Compliance V11)', function () {
    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->subDays(1),
        'motivo' => 'Consulta general',
        'estado' => 'cancelada',
    ]);

    $response = $this->actingAs($this->user)->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/asistencia", [
        'estado' => 'asistio',
    ]);

    $response->assertSessionHasErrors(['estado' => 'Solo se puede registrar asistencia de citas programadas.']);
});

it('falla con 422 si se intenta marcar asistencia de una cita futura en dias posteriores', function () {
    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => now()->addDays(2),
        'motivo' => 'Consulta general',
        'estado' => 'programada',
    ]);

    $response = $this->actingAs($this->user)->patch("/expedientes/{$this->expediente->id}/citas/{$cita->id}/asistencia", [
        'estado' => 'asistio',
    ]);

    $response->assertSessionHasErrors(['fecha_hora' => 'No se puede registrar asistencia de citas en el futuro.']);
});
