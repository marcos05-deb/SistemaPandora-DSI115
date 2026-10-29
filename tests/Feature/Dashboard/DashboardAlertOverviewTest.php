<?php

declare(strict_types=1);

use App\Models\AlertaPreventiva;
use App\Models\Area;
use App\Models\Audit;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\EvaluacionRiesgo;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Rubrica;
use App\Models\RubricaVersion;
use App\Services\DashboardAlertOverviewService;
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
            ->where('alertOverview.reveal_carnets', true)
            ->where('alertOverview.urgentes.0.paciente_carnet', $paciente->carnet)
            ->has('alertOverview.tendencia_suicida')
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

it('protege conteos pequeños y omite alertas individuales del admin', function (): void {
    $admin = Especialista::factory()->withRole('sysadmin')->create();

    $rubrica = Rubrica::query()->where('codigo', Rubrica::CODIGO_RIESGO_PREVENTIVO)->firstOrFail();
    $version = RubricaVersion::query()->create([
        'rubrica_id' => $rubrica->id,
        'numero_version' => 9102,
        'vigente_desde' => Carbon::now()->subDay(),
        'umbral_puntaje_global' => null,
        'justificacion_cambio' => 'Admin overview test',
        'validada_en' => Carbon::now()->subDay()->toDateString(),
        'referencia_validacion' => 'Admin overview',
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
        'detalle' => 'Admin overview evaluation',
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

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('alertOverview.visible', true)
            ->where('alertOverview.reveal_carnets', false)
            ->where('alertOverview.scope', 'institucional')
            ->where('alertOverview.total', null)
            ->where('alertOverview.abiertas', null)
            ->where('alertOverview.privacidad_protegida', true)
            ->where('alertOverview.por_estado.0.count', null)
            ->where('alertOverview.por_senal.0.count', null)
            ->has('alertOverview.urgentes', 0)
            ->has('alertOverview.tendencia_suicida')
        );

    $audit = Audit::query()->where('user_id', $admin->id)->where('event', 'accessed')
        ->where('tags', 'aggregate_access')->get()->first(
            fn (Audit $row): bool => ($row->new_values['action'] ?? null) === 'view_dashboard_overview'
        );
    expect($audit)->not->toBeNull()
        ->and($audit->new_values['filters']['scope'])->toBe('institucional')
        ->and(json_encode($audit->new_values))->not->toContain($paciente->codigo);
});

it('protege las dos jerarquías y el total de una cohorte sensible pequeña', function (): void {
    $patient = Paciente::factory()->create([
        'creado_por_profesional_id' => $this->coordinator->profesional->id,
        'etiquetas_motivo' => ['Ideas Suicidas'],
    ]);
    Expediente::factory()->create([
        'paciente_id' => $patient->codigo,
        'area_id' => $this->area->id,
        'derivado_por_profesional_id' => $this->coordinator->profesional->id,
    ]);
    $this->actingAs($this->coordinator);
    $overview = app(DashboardAlertOverviewService::class)->forUser($this->coordinator);

    expect($overview['tendencia_suicida']['total_alumnos'])->toBeNull()
        ->and($overview['tendencia_suicida']['por_facultad'])->not->toBeEmpty()
        ->and($overview['tendencia_suicida']['por_carrera'])->not->toBeEmpty();
    foreach ([$overview['tendencia_suicida']['por_facultad'], $overview['tendencia_suicida']['por_carrera'], $overview['por_etiqueta']] as $rows) {
        expect(collect($rows)->every(fn (array $row): bool => $row['count'] === null))->toBeTrue();
    }

    expect(Audit::query()->where('user_id', $this->coordinator->id)->where('tags', 'listing_access')
        ->where('auditable_type', AlertaPreventiva::class)->where('event', 'accessed')->exists())->toBeTrue();
});

it('no registra accesos al resumen cuando la policy lo excluye', function (): void {
    $referent = Especialista::factory()->withRole('psychosocial_referent')->withProfesional($this->area)->create();
    $this->actingAs($referent);
    $before = Audit::query()->where('user_id', $referent->id)->where('event', 'accessed')->count();
    $overview = app(DashboardAlertOverviewService::class)->forUser($referent);

    expect($overview['visible'])->toBeFalse()
        ->and(Audit::query()->where('user_id', $referent->id)->where('event', 'accessed')->count())->toBe($before);
});
