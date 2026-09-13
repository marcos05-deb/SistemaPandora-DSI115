<?php

declare(strict_types=1);

use App\Enums\EstadoCita;
use App\Models\Area;
use App\Models\Cita;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    session(['_sym_key' => str_repeat('a', 32)]);

    $this->area = Area::factory()->create();
    $this->dueño = Especialista::factory()->create();
    $this->ajeno = Especialista::factory()->create();

    $this->perfil = Profesional::factory()->create([
        'user_id' => $this->dueño->id,
        'area_id' => $this->area->id,
    ]);

    $paciente = Paciente::factory()->create();
    $this->expediente = Expediente::factory()->create([
        'paciente_id' => $paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => 'abierto',
    ]);

    $this->base = now()->addDays(6)->setTime(9, 0)->seconds(0);
});

it('deriva profesional_user_id desde el perfil al guardar', function () {
    $cita = Cita::create([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->perfil->id,
        'area_id' => $this->area->id,
        'fecha_hora' => $this->base,
        'motivo' => 'Derivación automática del usuario',
        'estado' => EstadoCita::Programada->value,
    ]);

    expect((int) $cita->fresh()->profesional_user_id)->toBe((int) $this->dueño->id);
});

it('rechaza guardar un profesional_user_id distinto al dueño del perfil', function () {
    $cita = new Cita([
        'expediente_id' => $this->expediente->id,
        'profesional_id' => $this->perfil->id,
        'area_id' => $this->area->id,
        'fecha_hora' => $this->base,
        'motivo' => 'Par inconsistente',
        'estado' => EstadoCita::Programada->value,
    ]);
    $cita->profesional_user_id = $this->ajeno->id;

    expect(fn () => $cita->save())->toThrow(InvalidArgumentException::class);
});

it('rechaza en PostgreSQL un par profesional/usuario inconsistente', function () {
    $sql = <<<'SQL'
        INSERT INTO citas (
            id, expediente_id, profesional_id, profesional_user_id, area_id,
            fecha_hora, estado, motivo, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, 'programada', 'FK compuesta', NOW(), NOW()
        )
    SQL;

    DB::connection('pgsql')->statement('SAVEPOINT pair_guard');

    try {
        DB::connection('pgsql')->insert($sql, [
            (string) Str::uuid(),
            $this->expediente->id,
            $this->perfil->id,
            $this->ajeno->id,
            $this->area->id,
            $this->base->toIso8601String(),
        ]);
        expect(false)->toBeTrue('Debía fallar la FK compuesta');
    } catch (QueryException $e) {
        DB::connection('pgsql')->statement('ROLLBACK TO SAVEPOINT pair_guard');
        expect($e->getMessage())->toContain('citas_profesional_id_user_id_fk');
    }
});
