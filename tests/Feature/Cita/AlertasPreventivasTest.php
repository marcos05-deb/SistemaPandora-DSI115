<?php

declare(strict_types=1);

use App\Enums\EstadoCita;
use App\Models\Area;
use App\Models\Cita;
use App\Models\Especialista;
use App\Models\EstadisticaPreventivaAusencia;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Role;
use App\Models\Rubrica;
use App\Models\RubricaVersion;
use App\Services\AlertasPreventivasService;

beforeEach(function () {
    session(['_sym_key' => str_repeat('a', 32)]);

    $this->area = Area::factory()->create();
    $this->otraArea = Area::factory()->create();

    $role = Role::firstOrCreate(
        ['slug' => 'specialist'],
        ['nombre' => 'Especialista', 'is_active' => true]
    );

    $this->especialista = Especialista::factory()->create();
    $this->especialista->roles()->attach($role);
    $this->profesional = Profesional::factory()->create([
        'user_id' => $this->especialista->id,
        'area_id' => $this->area->id,
    ]);

    // HU-16 replaces the fixed threshold with the approved active rubric version (2 absences / 30 days).
    $rubrica = Rubrica::query()->where('codigo', Rubrica::CODIGO_RIESGO_PREVENTIVO)->firstOrFail();
    $version = RubricaVersion::query()->create([
        'rubrica_id' => $rubrica->id,
        'numero_version' => 1,
        'vigente_desde' => now()->subDay(),
        'umbral_puntaje_global' => null,
        'justificacion_cambio' => null,
        'validada_en' => null,
        'referencia_validacion' => null,
        'es_demo' => true,
        'creada_por_profesional_id' => $this->profesional->id,
    ]);
    $version->variables()->create([
        'tipo_senal' => 'ausencia_cita',
        'clave_indicador' => null,
        'modo_calculo' => 'frecuencia',
        'ventana_dias' => 30,
        'ponderacion' => '1.00',
        'umbral' => '2.00',
    ]);

    $this->otroEspecialista = Especialista::factory()->create();
    $this->otroEspecialista->roles()->attach($role);
    Profesional::factory()->create([
        'user_id' => $this->otroEspecialista->id,
        'area_id' => $this->otraArea->id,
    ]);

    $this->paciente = Paciente::factory()->create();
    $this->expediente = Expediente::factory()->create([
        'paciente_id' => $this->paciente->codigo,
        'area_id' => $this->area->id,
        'estado' => 'abierto',
    ]);
});

function registrarAusenciaStat(
    Expediente $expediente,
    Profesional $profesional,
    Area $area,
    $fecha
): void {
    $cita = Cita::create([
        'expediente_id' => $expediente->id,
        'profesional_id' => $profesional->id,
        'area_id' => $area->id,
        'fecha_hora' => $fecha,
        'motivo' => 'Cita para estadística de ausencia',
        'estado' => EstadoCita::Ausente->value,
    ]);

    EstadisticaPreventivaAusencia::query()->create([
        'cita_id' => $cita->id,
        'paciente_id' => $expediente->paciente_id,
        'profesional_id' => $profesional->id,
        'area_id' => $area->id,
        'fecha_registro' => $fecha,
    ]);
}

it('no activa alerta con una sola ausencia en la ventana', function () {
    registrarAusenciaStat($this->expediente, $this->profesional, $this->area, now()->subDays(2));

    $alerta = app(AlertasPreventivasService::class)
        ->alertaPaciente($this->especialista, $this->paciente->codigo);

    expect($alerta['activa'])->toBeFalse()
        ->and($alerta['total'])->toBe(1);
});

it('activa alerta con dos ausencias dentro de 30 días', function () {
    registrarAusenciaStat($this->expediente, $this->profesional, $this->area, now()->subDays(5));
    registrarAusenciaStat($this->expediente, $this->profesional, $this->area, now()->subDays(1));

    $alerta = app(AlertasPreventivasService::class)
        ->alertaPaciente($this->especialista, $this->paciente->codigo);

    expect($alerta['activa'])->toBeTrue()
        ->and($alerta['total'])->toBe(2)
        ->and($alerta['mensaje'])->not->toBeNull();
});

it('ignora ausencias fuera de la ventana de 30 días', function () {
    registrarAusenciaStat($this->expediente, $this->profesional, $this->area, now()->subDays(40));
    registrarAusenciaStat($this->expediente, $this->profesional, $this->area, now()->subDays(2));

    $alerta = app(AlertasPreventivasService::class)
        ->alertaPaciente($this->especialista, $this->paciente->codigo);

    expect($alerta['activa'])->toBeFalse()
        ->and($alerta['total'])->toBe(1);
});

it('no permite a otra área contar ausencias ajenas', function () {
    registrarAusenciaStat($this->expediente, $this->profesional, $this->area, now()->subDays(3));
    registrarAusenciaStat($this->expediente, $this->profesional, $this->area, now()->subDays(1));

    $alerta = app(AlertasPreventivasService::class)
        ->alertaPaciente($this->otroEspecialista, $this->paciente->codigo);

    expect($alerta['activa'])->toBeFalse()
        ->and($alerta['total'])->toBe(0);
});
