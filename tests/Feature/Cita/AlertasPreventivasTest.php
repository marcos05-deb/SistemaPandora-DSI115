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
