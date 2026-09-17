<?php

declare(strict_types=1);

use App\Enums\EstadoCita;
use App\Models\Area;
use App\Models\Cita;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Role;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    session(['_sym_key' => str_repeat('a', 32)]);

    $this->areaA = Area::factory()->create(['nombre' => 'Psicología RF01']);
    $this->areaB = Area::factory()->create(['nombre' => 'Medicina RF01']);

    $role = Role::firstOrCreate(
        ['slug' => 'specialist'],
        ['nombre' => 'Especialista', 'is_active' => true]
    );

    $this->user = Especialista::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $this->user->roles()->attach($role);

    $this->profA = Profesional::factory()->create([
        'user_id' => $this->user->id,
        'area_id' => $this->areaA->id,
    ]);
    $this->profB = Profesional::factory()->create([
        'user_id' => $this->user->id,
        'area_id' => $this->areaB->id,
    ]);

    $this->otro = Especialista::factory()->create(['is_active' => true]);
    $this->otro->roles()->attach($role);
    $this->profOtro = Profesional::factory()->create([
        'user_id' => $this->otro->id,
        'area_id' => $this->areaA->id,
    ]);

    $paciente = Paciente::factory()->create();
    $this->expedienteA = Expediente::factory()->create([
        'paciente_id' => $paciente->codigo,
        'area_id' => $this->areaA->id,
        'estado' => 'abierto',
    ]);
    $this->expedienteB = Expediente::factory()->create([
        'paciente_id' => $paciente->codigo,
        'area_id' => $this->areaB->id,
        'estado' => 'abierto',
    ]);

    $this->consultaA = Consulta::create([
        'expediente_id' => $this->expedienteA->id,
        'profesional_id' => $this->profA->id,
        'motivo_consulta' => 'Motivo A',
        'notas_clinicas' => 'Notas A',
        'diagnostico' => 'Dx A',
        'plan_atencion' => 'Plan A',
        'tecnica_utilizada' => 'Entrevista',
        'fecha_consulta' => now(),
    ]);
    $this->consultaB = Consulta::create([
        'expediente_id' => $this->expedienteB->id,
        'profesional_id' => $this->profB->id,
        'motivo_consulta' => 'Motivo B',
        'notas_clinicas' => 'Notas B',
        'diagnostico' => 'Dx B',
        'plan_atencion' => 'Plan B',
        'tecnica_utilizada' => 'Entrevista',
        'fecha_consulta' => now(),
    ]);

    $this->base = now()->addDays(4)->setTime(10, 0)->seconds(0);
});

it('rechaza solapamiento del mismo perfil', function () {
    Cita::create([
        'expediente_id' => $this->expedienteA->id,
        'consulta_id' => $this->consultaA->id,
        'profesional_id' => $this->profA->id,
        'area_id' => $this->areaA->id,
        'fecha_hora' => $this->base,
        'motivo' => 'Cita base',
        'estado' => EstadoCita::Programada->value,
    ]);

    $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('citas.store', $this->expedienteA->id), [
            'consulta_id' => $this->consultaA->id,
            'fecha_hora' => $this->base->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'motivo' => 'Solapada mismo perfil',
            'acordada_con_paciente' => true,
        ])
        ->assertSessionHasErrors('fecha_hora');
});

it('rechaza solapamiento entre dos áreas de la misma persona', function () {
    Cita::create([
        'expediente_id' => $this->expedienteA->id,
        'consulta_id' => $this->consultaA->id,
        'profesional_id' => $this->profA->id,
        'area_id' => $this->areaA->id,
        'fecha_hora' => $this->base,
        'motivo' => 'Cita psicología',
        'estado' => EstadoCita::Programada->value,
    ]);

    $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('citas.store', $this->expedienteB->id), [
            'consulta_id' => $this->consultaB->id,
            'fecha_hora' => $this->base->format('Y-m-d H:i:s'),
            'motivo' => 'Cita medicina solapada',
            'acordada_con_paciente' => true,
        ])
        ->assertSessionHasErrors('fecha_hora');

    expect(
        Cita::withoutGlobalScopes()
            ->where('profesional_user_id', $this->user->id)
            ->where('estado', EstadoCita::Programada->value)
            ->count()
    )->toBe(1);
});

it('permite la misma hora a personas distintas', function () {
    Cita::create([
        'expediente_id' => $this->expedienteA->id,
        'consulta_id' => $this->consultaA->id,
        'profesional_id' => $this->profA->id,
        'area_id' => $this->areaA->id,
        'fecha_hora' => $this->base,
        'motivo' => 'Cita persona 1',
        'estado' => EstadoCita::Programada->value,
    ]);

    expect(Cita::hayConflictoHorario($this->otro->id, $this->base))->toBeFalse();

    Cita::create([
        'expediente_id' => $this->expedienteA->id,
        'profesional_id' => $this->profOtro->id,
        'profesional_user_id' => $this->otro->id,
        'area_id' => $this->areaA->id,
        'fecha_hora' => $this->base,
        'motivo' => 'Cita persona 2',
        'estado' => EstadoCita::Programada->value,
    ]);

    expect(
        Cita::withoutGlobalScopes()
            ->where('estado', EstadoCita::Programada->value)
            ->where('fecha_hora', $this->base)
            ->count()
    )->toBe(2);
});

it('permite citas contiguas sin solapamiento', function () {
    Cita::create([
        'expediente_id' => $this->expedienteA->id,
        'profesional_id' => $this->profA->id,
        'area_id' => $this->areaA->id,
        'fecha_hora' => $this->base,
        'motivo' => '10:00-11:00',
        'estado' => EstadoCita::Programada->value,
    ]);

    expect(Cita::hayConflictoHorario($this->user->id, $this->base->copy()->addHour()))->toBeFalse();

    $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->post(route('citas.store', $this->expedienteB->id), [
            'consulta_id' => $this->consultaB->id,
            'fecha_hora' => $this->base->copy()->addHour()->format('Y-m-d H:i:s'),
            'motivo' => '11:00-12:00 otra área',
            'acordada_con_paciente' => true,
        ])
        ->assertRedirect();
});

it('rechaza reprogramar hacia horario ocupado en otra área', function () {
    $citaA = Cita::create([
        'expediente_id' => $this->expedienteA->id,
        'profesional_id' => $this->profA->id,
        'area_id' => $this->areaA->id,
        'fecha_hora' => $this->base->copy()->addDays(1),
        'motivo' => 'Cita a reprogramar',
        'estado' => EstadoCita::Programada->value,
    ]);

    Cita::create([
        'expediente_id' => $this->expedienteB->id,
        'profesional_id' => $this->profB->id,
        'area_id' => $this->areaB->id,
        'fecha_hora' => $this->base->copy()->addDays(2),
        'motivo' => 'Ocupado en medicina',
        'estado' => EstadoCita::Programada->value,
    ]);

    $this->actingAs($this->user)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->patch(route('citas.reprogramar', [$this->expedienteA->id, $citaA->id]), [
            'fecha_hora' => $this->base->copy()->addDays(2)->format('Y-m-d H:i:s'),
            'motivo_reprogramacion' => 'Intento chocar con otra área',
            'acordada_con_paciente' => true,
        ])
        ->assertSessionHasErrors('fecha_hora');
});
