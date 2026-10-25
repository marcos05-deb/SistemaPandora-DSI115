<?php

declare(strict_types=1);

use App\Models\AlertaPreventiva;
use App\Models\Area;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\EvaluacionRiesgo;
use App\Models\Paciente;
use App\Models\Rubrica;
use App\Models\RubricaVersion;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->withoutMiddleware(PreventRequestForgery::class);
    config(['app.area_key_secret' => str_repeat('area-secret-', 4)]);
    $this->withSession(['_sym_key' => base64_encode(str_repeat('s', SODIUM_CRYPTO_SECRETBOX_KEYBYTES))]);

    $this->area = Area::factory()->create();
    $this->coordinator = Especialista::factory()
        ->withRole('area_coordinator')
        ->withProfesional($this->area)
        ->create();
});

it('incluye resumen de alertas en el dashboard del coordinador', function (): void {
    $rubrica = Rubrica::query()->where('codigo', Rubrica::CODIGO_RIESGO_PREVENTIVO)->firstOrFail();
    $version = RubricaVersion::query()->create([
        'rubrica_id' => $rubrica->id,
        'numero_version' => 9101,
        'vigente_desde' => Carbon::now()->subDay(),
        'umbral_puntaje_global' => null,
        'justificacion_cambio' => 'Dashboard overview test',
        'validada_en' => Carbon::now()->subDay()->toDateString(),
        'referencia_validacion' => 'Dashboard overview',
        'es_demo' => false,
        'creada_por_profesional_id' => $this->coordinator->profesional->id,
    ]);

    $paciente = Paciente::factory()->create([
        'creado_por_profesional_id' => $this->coordinator->profesional->id,
    ]);

    $evaluacion = EvaluacionRiesgo::query()->create([
        'paciente_id' => $paciente->codigo,
        'area_id' => $this->area->id,
        'rubrica_version_id' => $version->id,
        'disparador' => 'ausencia_cita',
        'puntaje' => '2.00',
        'detalle' => 'Dashboard overview evaluation',
        'estado_evaluacion' => 'evaluada',
        'evento_origen_tipo' => Consulta::class,
        'evento_origen_id' => (string) Str::uuid(),
        'evaluada_en' => Carbon::now(),
    ]);

    AlertaPreventiva::query()->create([
        'paciente_id' => $paciente->codigo,
        'area_id' => $this->area->id,
        'evaluacion_riesgo_id' => $evaluacion->id,
        'rubrica_version_id' => $version->id,
        'senal' => 'ausencia_cita',
        'valor_calculado' => '2.00',
        'umbral' => '2.00',
        'estado' => 'activa',
        'responsable_profesional_id' => $this->coordinator->profesional->id,
        'generada_en' => Carbon::now(),
    ]);

    $this->actingAs($this->coordinator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('alertOverview.visible', true)
            ->where('alertOverview.total', 1)
            ->where('alertOverview.abiertas', 1)
            ->has('alertOverview.por_estado', 4)
            ->has('alertOverview.por_senal', 3)
            ->has('alertOverview.urgentes', 1)
            ->where('alertOverview.urgentes.0.paciente_carnet', $paciente->carnet)
        );
});

it('oculta el resumen de alertas al referente psicosocial', function (): void {
    $referent = Especialista::factory()
        ->withRole('psychosocial_referent')
        ->withProfesional($this->area)
        ->create();

    $this->actingAs($referent)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('alertOverview.visible', false)
            ->where('alertOverview.total', 0)
        );
});
