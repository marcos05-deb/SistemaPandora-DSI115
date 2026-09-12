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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    session(['_sym_key' => str_repeat('a', 32)]);

    $area = Area::factory()->create();
    $role = Role::firstOrCreate(
        ['slug' => 'specialist'],
        ['nombre' => 'Especialista', 'is_active' => true]
    );

    $this->especialista = Especialista::factory()->create();
    $this->especialista->roles()->attach($role);
    $this->profesional = Profesional::factory()->create([
        'user_id' => $this->especialista->id,
        'area_id' => $area->id,
    ]);

    $paciente = Paciente::factory()->create();
    $this->expediente = Expediente::factory()->create([
        'paciente_id' => $paciente->codigo,
        'area_id' => $area->id,
        'estado' => 'abierto',
    ]);

    $this->consulta = Consulta::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->profesional->id,
        'motivo_consulta' => 'Motivo base',
        'notas_clinicas' => 'Notas',
        'diagnostico' => 'Dx',
        'plan_atencion' => 'Plan de atención',
        'tecnica_utilizada' => 'Entrevista',
        'fecha_consulta' => now(),
    ]);

    $this->base = now()->addDays(3)->setTime(10, 0);
});

it('rechaza inserción solapada en BD con SQLSTATE 23P01', function () {
    Cita::create([
        'expediente_id' => $this->expediente->id,
        'consulta_id' => $this->consulta->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->expediente->area_id,
        'fecha_hora' => $this->base,
        'motivo' => 'Primera cita programada',
        'estado' => EstadoCita::Programada->value,
    ]);

    $sql = <<<'SQL'
        INSERT INTO citas (
            id, expediente_id, consulta_id, profesional_id, profesional_user_id, area_id,
            fecha_hora, estado, motivo, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, 'programada', 'Solapada', NOW(), NOW()
        )
    SQL;

    DB::connection('pgsql')->statement('SAVEPOINT overlap_guard');

    try {
        DB::connection('pgsql')->insert($sql, [
            (string) Str::uuid(),
            $this->expediente->id,
            $this->consulta->id,
            $this->profesional->id,
            $this->profesional->user_id,
            $this->expediente->area_id,
            $this->base->copy()->addMinutes(30)->toIso8601String(),
        ]);
        expect(false)->toBeTrue('Debía fallar por exclusión de solapamiento');
    } catch (QueryException $e) {
        DB::connection('pgsql')->statement('ROLLBACK TO SAVEPOINT overlap_guard');
        expect($e->errorInfo[0] ?? null)->toBe('23P01');
    }

    expect(Cita::where('estado', EstadoCita::Programada->value)->count())->toBe(1);
});

it('propaga el conflicto de exclusión al crear cita por HTTP', function () {
    Cita::create([
        'expediente_id' => $this->expediente->id,
        'consulta_id' => $this->consulta->id,
        'profesional_id' => $this->profesional->id,
        'area_id' => $this->expediente->area_id,
        'fecha_hora' => $this->base,
        'motivo' => 'Cita existente',
        'estado' => EstadoCita::Programada->value,
    ]);

    $this->actingAs($this->especialista)
        ->withSession(['_sym_key' => str_repeat('a', 32)])
        ->from(route('pacientes.show', Paciente::find($this->expediente->paciente_id)->carnet))
        ->post(route('citas.store', $this->expediente->id), [
            'consulta_id' => $this->consulta->id,
            'fecha_hora' => $this->base->copy()->addMinutes(15)->format('Y-m-d H:i:s'),
            'motivo' => 'Intento solapado concurrente',
            'acordada_con_paciente' => true,
        ])
        ->assertSessionHasErrors('fecha_hora');

    expect(Cita::where('estado', EstadoCita::Programada->value)->count())->toBe(1);
});
