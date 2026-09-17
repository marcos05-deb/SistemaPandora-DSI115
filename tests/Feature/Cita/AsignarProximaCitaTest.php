<?php

declare(strict_types=1);

use App\Models\Area;
use App\Models\Cita;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use OwenIt\Auditing\Models\Audit;

beforeEach(function () {
    session(['_sym_key' => str_repeat('a', 32)]);

    $this->area = Area::factory()->create();
    $this->otraArea = Area::factory()->create();

    $this->roleSpecialist = Role::create([
        'nombre' => 'Specialist',
        'slug' => 'specialist',
        'description' => 'Especialista',
        'is_active' => true,
    ]);

    $this->userSpecialist = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $this->userSpecialist->roles()->attach($this->roleSpecialist->id);
    $this->profesional = Profesional::factory()->create([
        'user_id' => $this->userSpecialist->id,
        'area_id' => $this->area->id,
    ]);

    $this->otroSpecialist = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $this->otroSpecialist->roles()->attach($this->roleSpecialist->id);
    Profesional::factory()->create([
        'user_id' => $this->otroSpecialist->id,
        'area_id' => $this->otraArea->id,
    ]);

    $this->paciente = Paciente::factory()->create();
    $this->expediente = Expediente::create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => Expediente::ESTADO_EN_ATENCION,
        'fecha_derivacion' => now(),
    ]);

    $this->consulta = Consulta::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'motivo_consulta' => 'Seguimiento clínico',
        'notas_clinicas' => 'Evolución favorable',
        'diagnostico' => 'Ansiedad leve',
        'plan_atencion' => 'Continuar terapia cognitivo conductual',
        'tecnica_utilizada' => 'Entrevista',
        'fecha_consulta' => now(),
    ]);

    $this->otroExpediente = Expediente::create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->otraArea->id,
        'estado' => Expediente::ESTADO_EN_ATENCION,
        'fecha_derivacion' => now(),
    ]);

    $this->consultaAjena = Consulta::create([
        'expediente_id' => $this->otroExpediente->id,
        'profesional_id' => $this->otroSpecialist->profesional->id,
        'motivo_consulta' => 'Consulta de otra área',
        'notas_clinicas' => 'No aplica',
        'diagnostico' => 'N/A',
        'plan_atencion' => 'Plan de otra área clínica',
        'tecnica_utilizada' => 'Entrevista',
        'fecha_consulta' => now(),
    ]);
});

it('creates a cita linked to the active consulta', function () {
    $this->actingAs($this->userSpecialist);
    $fechaHora = now()->addDays(2)->format('Y-m-d H:i:s');

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('citas.store', $this->expediente->id), [
            'consulta_id' => $this->consulta->id,
            'fecha_hora' => $fechaHora,
            'motivo' => 'Seguimiento post consulta',
            'acordada_con_paciente' => true,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $cita = Cita::first();
    expect($cita)->not->toBeNull()
        ->and($cita->consulta_id)->toBe($this->consulta->id)
        ->and($cita->expediente_id)->toBe($this->expediente->id)
        ->and($cita->profesional_id)->toBe($this->profesional->id)
        ->and($cita->motivo)->toBe('Seguimiento post consulta');
});

it('rejects cita without consulta_id', function () {
    $this->actingAs($this->userSpecialist);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('pacientes.show', $this->paciente->carnet))
        ->post(route('citas.store', $this->expediente->id), [
            'fecha_hora' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'motivo' => 'Sin consulta origen',
            'acordada_con_paciente' => true,
        ]);

    $response->assertSessionHasErrors('consulta_id');
    expect(Cita::count())->toBe(0);
});

it('rejects consulta that belongs to another expediente', function () {
    $this->actingAs($this->userSpecialist);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('pacientes.show', $this->paciente->carnet))
        ->post(route('citas.store', $this->expediente->id), [
            'consulta_id' => $this->consultaAjena->id,
            'fecha_hora' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'motivo' => 'Consulta ajena',
            'acordada_con_paciente' => true,
        ]);

    $response->assertSessionHasErrors('consulta_id');
    expect(Cita::count())->toBe(0);
});

it('rejects past fecha_hora', function () {
    $this->actingAs($this->userSpecialist);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('pacientes.show', $this->paciente->carnet))
        ->post(route('citas.store', $this->expediente->id), [
            'consulta_id' => $this->consulta->id,
            'fecha_hora' => now()->subHour()->format('Y-m-d H:i:s'),
            'motivo' => 'Fecha pasada',
            'acordada_con_paciente' => true,
        ]);

    $response->assertSessionHasErrors('fecha_hora');
    expect(Cita::count())->toBe(0);
});

it('rejects scheduling on closed expediente', function () {
    $this->actingAs($this->userSpecialist);
    $this->expediente->update(['estado' => Expediente::ESTADO_CERRADO]);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('pacientes.show', $this->paciente->carnet))
        ->post(route('citas.store', $this->expediente->id), [
            'consulta_id' => $this->consulta->id,
            'fecha_hora' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'motivo' => 'Expediente cerrado',
            'acordada_con_paciente' => true,
        ]);

    $response->assertSessionHasErrors('expediente');
    expect(Cita::count())->toBe(0);
});

it('rejects conflicting horario for the same specialist', function () {
    $this->actingAs($this->userSpecialist);
    $fechaHora = now()->addDays(2)->format('Y-m-d H:i:00');

    Cita::create([
        'expediente_id' => $this->expediente->id,
        'consulta_id' => $this->consulta->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => $fechaHora,
        'motivo' => 'Primera cita',
        'estado' => 'programada',
    ]);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('pacientes.show', $this->paciente->carnet))
        ->post(route('citas.store', $this->expediente->id), [
            'consulta_id' => $this->consulta->id,
            'fecha_hora' => $fechaHora,
            'motivo' => 'Conflicto',
            'acordada_con_paciente' => true,
        ]);

    $response->assertSessionHasErrors('fecha_hora');
    expect(Cita::count())->toBe(1);
});

it('rejects overlapping duration even when timestamps differ', function () {
    $this->actingAs($this->userSpecialist);
    $inicio = now()->addDays(2)->setTime(10, 0);

    Cita::create([
        'expediente_id' => $this->expediente->id,
        'consulta_id' => $this->consulta->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->area->id,
        'fecha_hora' => $inicio,
        'motivo' => 'Primera cita',
        'estado' => 'programada',
    ]);

    $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('pacientes.show', $this->paciente->carnet))
        ->post(route('citas.store', $this->expediente->id), [
            'consulta_id' => $this->consulta->id,
            'fecha_hora' => $inicio->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'motivo' => 'Solapamiento por duración',
            'acordada_con_paciente' => true,
        ]);

    $response->assertSessionHasErrors('fecha_hora');
    expect(Cita::count())->toBe(1);
});

it('audits cita creation', function () {
    $this->actingAs($this->userSpecialist);

    $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('citas.store', $this->expediente->id), [
            'consulta_id' => $this->consulta->id,
            'fecha_hora' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'motivo' => 'Cita auditada',
            'acordada_con_paciente' => true,
        ])
        ->assertRedirect();

    $cita = Cita::first();
    $audit = Audit::query()
        ->where('auditable_type', Cita::class)
        ->where('auditable_id', $cita->id)
        ->where('event', 'created')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($this->userSpecialist->id)
        ->and($audit->new_values['consulta_id'] ?? null)->toBe($this->consulta->id);
});

it('rejects scheduling from a previous consulta of the same expediente', function () {
    $consultaAntigua = Consulta::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'motivo_consulta' => 'Primera consulta',
        'notas_clinicas' => 'Inicio',
        'diagnostico' => 'Inicial',
        'plan_atencion' => 'Plan inicial de atención clínica',
        'tecnica_utilizada' => 'Entrevista',
        'fecha_consulta' => now()->subDays(5),
    ]);

    // Asegura que $this->consulta sea la activa (más reciente).
    $this->consulta->update(['fecha_consulta' => now()]);

    $this->actingAs($this->userSpecialist)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('pacientes.show', $this->paciente->carnet))
        ->post(route('citas.store', $this->expediente->id), [
            'consulta_id' => $consultaAntigua->id,
            'fecha_hora' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'motivo' => 'Intento con consulta anterior',
            'acordada_con_paciente' => true,
        ])
        ->assertSessionHasErrors('consulta_id');

    expect(Cita::count())->toBe(0);
});

it('allows scheduling only with the most recent active consulta', function () {
    $this->consulta->update(['fecha_consulta' => now()->subDays(3)]);

    $consultaNueva = Consulta::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'motivo_consulta' => 'Consulta activa actual',
        'notas_clinicas' => 'Reciente',
        'diagnostico' => 'Actual',
        'plan_atencion' => 'Plan actual de atención clínica',
        'tecnica_utilizada' => 'Entrevista',
        'fecha_consulta' => now(),
    ]);

    $this->actingAs($this->userSpecialist)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('citas.store', $this->expediente->id), [
            'consulta_id' => $consultaNueva->id,
            'fecha_hora' => now()->addDays(4)->format('Y-m-d H:i:s'),
            'motivo' => 'Desde consulta activa',
            'acordada_con_paciente' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Cita::first()->consulta_id)->toBe($consultaNueva->id);
});
it('rejects cita without patient agreement confirmation', function () {
    $this->actingAs($this->userSpecialist);

    $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('pacientes.show', $this->paciente->carnet))
        ->post(route('citas.store', $this->expediente->id), [
            'consulta_id' => $this->consulta->id,
            'fecha_hora' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'motivo' => 'Sin confirmación de acuerdo',
        ])
        ->assertSessionHasErrors('acordada_con_paciente');

    expect(Cita::count())->toBe(0);
});

it('rejects cita when patient agreement is false', function () {
    $this->actingAs($this->userSpecialist);

    $this->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('pacientes.show', $this->paciente->carnet))
        ->post(route('citas.store', $this->expediente->id), [
            'consulta_id' => $this->consulta->id,
            'fecha_hora' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'motivo' => 'Confirmación en falso',
            'acordada_con_paciente' => false,
        ])
        ->assertSessionHasErrors('acordada_con_paciente');

    expect(Cita::count())->toBe(0);
});

it('rejects sequential overlapping intervals for the same professional', function () {
    $this->actingAs($this->userSpecialist);
    $base = now()->addDays(5)->setTime(10, 0);

    $ok = 0;
    $fail = 0;

    foreach ([0, 30] as $offset) {
        $response = $this->withSession(['_sym_key' => str_repeat('a', 32)])
            ->from(route('pacientes.show', $this->paciente->carnet))
            ->post(route('citas.store', $this->expediente->id), [
                'consulta_id' => $this->consulta->id,
                'fecha_hora' => $base->copy()->addMinutes($offset)->format('Y-m-d H:i:s'),
                'motivo' => "Cita offset {$offset}",
                'acordada_con_paciente' => true,
            ]);

        if ($response->getSession()->has('errors')) {
            $fail++;
        } else {
            $ok++;
        }
    }

    expect($ok)->toBe(1)
        ->and($fail)->toBe(1)
        ->and(Cita::count())->toBe(1);
});
