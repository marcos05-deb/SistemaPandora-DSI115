<?php

declare(strict_types=1);

use App\DTO\FiltrosIndicador;
use App\Enums\TipoIndicador;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Area;
use App\Models\Carrera;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Facultad;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Services\IndicadorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

beforeEach(function (): void {
    $this->withoutMiddleware(PreventRequestForgery::class);
    config(['app.area_key_secret' => str_repeat('HU19-area-key-', 4)]);
    Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00', config('app.timezone')));
    $this->withSession(['_sym_key' => base64_encode(str_repeat('r', SODIUM_CRYPTO_SECRETBOX_KEYBYTES))]);

    $this->area = Area::factory()->create(['nombre' => 'HU19 Psychology']);
    $this->otherArea = Area::factory()->create(['nombre' => 'HU19 Nutrition']);
    $this->coordinator = Especialista::factory()
        ->withRole('area_coordinator')
        ->withProfesional($this->area)
        ->create();
    $this->professional = $this->coordinator->profesional;
    $this->otherSpecialist = Especialista::factory()
        ->withRole('specialist')
        ->withProfesional($this->otherArea)
        ->create();
    $this->specialist = Especialista::factory()
        ->withRole('specialist')
        ->withProfesional($this->area)
        ->create();
    $this->referent = Especialista::factory()
        ->withRole('psychosocial_referent')
        ->withProfesional($this->area)
        ->create();
    $this->sysadmin = Especialista::factory()->withRole('sysadmin')->create();

    $faculty = Facultad::factory()->create();
    $career = Carrera::factory()->create(['facultad_id' => $faculty->id]);
    [, $this->file] = hu19CreatePatientFile($this->area, $this->professional, $career->id);
    [, $this->otherFile] = hu19CreatePatientFile($this->otherArea, $this->otherSpecialist->profesional, $career->id);

    foreach (range(1, 6) as $index) {
        hu19CreateConsultation($this->file, $this->professional, CarbonImmutable::parse('2026-09-10', config('app.timezone'))->addHours($index));
    }
    foreach (range(1, 7) as $index) {
        hu19CreateConsultation($this->file, $this->professional, CarbonImmutable::parse('2026-10-10', config('app.timezone'))->addHours($index));
    }
    foreach (range(1, 6) as $index) {
        hu19CreateConsultation($this->otherFile, $this->otherSpecialist->profesional, CarbonImmutable::parse('2026-10-12', config('app.timezone'))->addHours($index));
    }

    $this->actingAs($this->coordinator);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** @return array<string, string> */
function hu19InertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? '',
        'Accept' => 'application/json',
    ];
}

/** @return array<string, mixed> */
function hu19Page(TestResponse $response): array
{
    return json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)['props'];
}

/** @return array{0: Paciente, 1: Expediente} */
function hu19CreatePatientFile(Area $area, Profesional $professional, int $careerId): array
{
    $patient = Paciente::factory()->create([
        'carrera_id' => $careerId,
        'creado_por_profesional_id' => $professional->id,
    ]);
    $file = Expediente::factory()->create([
        'paciente_id' => $patient->codigo,
        'area_id' => $area->id,
        'estado' => Expediente::ESTADO_ABIERTO,
        'derivado_por_profesional_id' => $professional->id,
        'fecha_derivacion' => now()->subDays(20),
    ]);

    return [$patient, $file];
}

function hu19CreateConsultation(Expediente $file, Profesional $professional, CarbonImmutable $date, array $overrides = []): Consulta
{
    $consulta = new Consulta;
    $consulta->expediente_id = $file->id;
    $consulta->setRelation('expediente', $file);
    $consulta->fill(array_merge([
        'profesional_id' => $professional->id,
        'motivo_consulta' => 'HU19 synthetic consultation reason.',
        'notas_clinicas' => 'HU19 synthetic private clinical note.',
        'diagnostico' => 'HU19 synthetic diagnosis.',
        'plan_atencion' => 'HU19 synthetic plan for report testing.',
        'tecnica_utilizada' => 'HU19 synthetic technique',
        'fecha_consulta' => $date,
        'evaluacion_inicial' => [],
        'crisis_psicologica' => 'No',
        'indicadores_tendencia_suicida' => [],
    ], $overrides));
    $consulta->save();

    return $consulta;
}

/** @return array<string, mixed> */
function hu19ValidParameters(int|string $areaId, array $overrides = []): array
{
    return array_merge([
        'desde' => '2026-09-01',
        'hasta' => '2026-10-22',
        'area' => (string) $areaId,
        'indicadores' => [TipoIndicador::ConsultasRegistradas->value],
        'agrupar_por' => [
            'indicador',
        ],
    ], $overrides);
}

function hu19Generate(TestCase $test, array $parameters): TestResponse
{
    return $test->withHeaders(hu19InertiaHeaders())->get(route('reportes.generate', $parameters));
}

/** @return array<string, mixed> */
function hu19Props(TestResponse $response): array
{
    return hu19Page($response);
}

function hu19SourceQueryCount(array $queries): int
{
    return collect($queries)->filter(static fn (array $query): bool => str_contains(strtolower($query['query']), ' from "consultas')
        || str_contains(strtolower($query['query']), ' from "expedientes')
        || str_contains(strtolower($query['query']), ' from "citas')
        || str_contains(strtolower($query['query']), ' from "alertas_preventivas')
        || str_contains(strtolower($query['query']), ' from "seguimientos_preventivos'))->count();
}

function hu19SqlTimestamp(CarbonImmutable $date): string
{
    return $date->format('Y-m-d H:i:s.uP');
}

test('HU19-E01 should_require_period_area_and_indicators_before_aggregate_calculation', function (): void {
    // Arrange
    $base = hu19ValidParameters($this->area->id);
    $withoutFrom = $base;
    unset($withoutFrom['desde']);
    $withoutArea = $base;
    unset($withoutArea['area']);
    $withoutIndicators = $base;
    unset($withoutIndicators['indicadores']);
    $connection = DB::connection();
    $connection->flushQueryLog();
    $connection->enableQueryLog();

    // Act
    $missingFrom = $this->getJson(route('reportes.generate', $withoutFrom));
    $missingArea = $this->getJson(route('reportes.generate', $withoutArea));
    $missingIndicators = $this->getJson(route('reportes.generate', $withoutIndicators));
    $aggregateQueries = hu19SourceQueryCount($connection->getQueryLog());
    $connection->disableQueryLog();

    // Assert
    expect($missingFrom->getStatusCode())->toBe(422)
        ->and($missingFrom->json('errors'))->toHaveKey('desde')
        ->and($missingArea->getStatusCode())->toBe(422)
        ->and($missingArea->json('errors'))->toHaveKey('area')
        ->and($missingIndicators->getStatusCode())->toBe(422)
        ->and($missingIndicators->json('errors'))->toHaveKey('indicadores')
        ->and($aggregateQueries)->toBe(0);
});

test('HU19-E02 should_echo_valid_parameters_and_show_the_report_inertia_page', function (): void {
    // Arrange
    $parameters = hu19ValidParameters($this->area->id);
    $this->flushHeaders()->get(route('reportes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reportes/Index')
            ->where('can.reportes', true));

    // Act
    $response = hu19Generate($this, $parameters);
    $props = hu19Props($response);

    // Assert
    expect($response->getStatusCode())->toBe(200)
        ->and($props['reporte']['parametros_aplicados']['desde'])->toBe($parameters['desde'])
        ->and($props['reporte']['parametros_aplicados']['hasta'])->toBe($parameters['hasta'])
        ->and($props['reporte']['parametros_aplicados']['area'])->toBe((int) $parameters['area'])
        ->and($props['reporte']['parametros_aplicados']['indicadores'])->toBe($parameters['indicadores'])
        ->and($props['reporte']['parametros_aplicados']['agrupar_por'])->toBe(['indicador']);
});

test('HU19-E03 should_match_each_report_total_to_the_shared_indicator_service', function (): void {
    // Arrange
    $parameters = hu19ValidParameters($this->area->id);
    $expected = app(IndicadorService::class)->calcular(new FiltrosIndicador(
        $parameters['desde'],
        $parameters['hasta'],
        $this->area->id,
        $parameters['indicadores'],
    ), $this->coordinator)->totales;

    // Act
    $response = hu19Generate($this, $parameters);
    $actual = hu19Props($response)['reporte']['totales'];

    // Assert
    expect($response->getStatusCode())->toBe(200)
        ->and($actual)->toBe($expected);
});

test('HU19-E04 should_group_by_month_and_area_and_match_independent_sql', function (): void {
    // Arrange
    $parameters = hu19ValidParameters($this->area->id, [
        'area' => 'todas',
        'desde' => '2026-09-01',
        'hasta' => '2026-10-31',
        'agrupar_por' => ['mes', 'area'],
    ]);
    foreach (range(1, 6) as $index) {
        hu19CreateConsultation($this->otherFile, $this->otherSpecialist->profesional, CarbonImmutable::parse('2026-09-15', config('app.timezone'))->addHours($index));
    }
    $monthCounts = DB::table('consultas as consulta')
        ->join('expedientes as expediente', 'expediente.id', '=', 'consulta.expediente_id')
        ->whereNull('consulta.deleted_at')
        ->whereNull('expediente.deleted_at')
        ->where('consulta.fecha_consulta', '>=', hu19SqlTimestamp(CarbonImmutable::parse('2026-09-01', config('app.timezone'))->startOfDay()->utc()))
        ->where('consulta.fecha_consulta', '<=', hu19SqlTimestamp(CarbonImmutable::parse('2026-09-30', config('app.timezone'))->endOfDay()->utc()))
        ->selectRaw('expediente.area_id, COUNT(*) AS total')
        ->groupBy('expediente.area_id')
        ->pluck('total', 'area_id')
        ->map(static fn (mixed $value): int => (int) $value)
        ->all();

    // Act
    $response = hu19Generate($this->actingAs($this->sysadmin), $parameters);
    $report = hu19Props($response)['reporte'];
    $september = collect($report['grupos'])->filter(fn (array $group): bool => ($group['dimensiones']['mes'] ?? null) === '2026-09');

    // Assert
    expect($response->getStatusCode())->toBe(200)
        ->and($september)->not->toBeEmpty();
    foreach ($september as $group) {
        $areaId = (int) $group['dimensiones']['area_id'];
        expect($group['valores'][TipoIndicador::ConsultasRegistradas->value]['valor'])->toBe($monthCounts[$areaId] ?? 0);
    }
});

test('HU19-E04 should_group_by_week_and_indicator_with_exact_week_totals', function (): void {
    // Arrange
    $from = '2026-10-05';
    $until = '2026-10-18';
    $expected = [];
    foreach ([['2026-10-05', '2026-10-11'], ['2026-10-12', '2026-10-18']] as [$start, $end]) {
        $expected[$start] = DB::table('consultas as consulta')
            ->join('expedientes as expediente', 'expediente.id', '=', 'consulta.expediente_id')
            ->where('expediente.area_id', $this->area->id)
            ->whereNull('consulta.deleted_at')
            ->whereNull('expediente.deleted_at')
            ->where('consulta.fecha_consulta', '>=', hu19SqlTimestamp(CarbonImmutable::parse($start, config('app.timezone'))->startOfDay()->utc()))
            ->where('consulta.fecha_consulta', '<=', hu19SqlTimestamp(CarbonImmutable::parse($end, config('app.timezone'))->endOfDay()->utc()))
            ->count();
    }

    // Act
    $response = hu19Generate($this, hu19ValidParameters($this->area->id, [
        'area' => (string) $this->area->id,
        'desde' => $from,
        'hasta' => $until,
        'agrupar_por' => ['semana', 'indicador'],
    ]));
    $groupsByWeek = collect(hu19Props($response)['reporte']['grupos'])
        ->mapWithKeys(fn (array $group): array => [
            $group['dimensiones']['semana'] => $group['valores'][TipoIndicador::ConsultasRegistradas->value]['valor'],
        ])
        ->all();

    // Assert
    expect($response->getStatusCode())->toBe(200)
        ->and($groupsByWeek)->toBe($expected);
});

test('HU19-E05 should_return_zero_totals_for_an_empty_period', function (): void {
    // Arrange
    $parameters = hu19ValidParameters($this->area->id, [
        'desde' => '2000-01-01',
        'hasta' => '2000-01-30',
    ]);

    // Act
    $response = hu19Generate($this, $parameters);
    $report = hu19Props($response)['reporte'];

    // Assert
    expect($response->getStatusCode())->toBe(200)
        ->and($report['sin_datos'])->toBeTrue()
        ->and(collect($report['totales'])->pluck('valor')->unique()->all())->toBe([0]);
});

test('HU19-E06 should_restrict_areas_by_role_and_allow_sysadmin_all_areas', function (): void {
    // Arrange
    $foreignArea = hu19Generate($this, hu19ValidParameters($this->area->id, ['area' => (string) $this->otherArea->id]));
    $coordinatorArea = hu19Generate($this, hu19ValidParameters($this->area->id));
    $allAreas = $this->actingAs($this->sysadmin)
        ->withHeaders(hu19InertiaHeaders())
        ->get(route('reportes.generate', hu19ValidParameters($this->area->id, ['area' => 'todas', 'agrupar_por' => ['area', 'indicador']])));

    // Assert
    expect($foreignArea->getStatusCode())->toBe(403)
        ->and($coordinatorArea->getStatusCode())->toBe(200)
        ->and($allAreas->getStatusCode())->toBe(200)
        ->and(hu19Props($allAreas)['reporte']['parametros_aplicados']['area'])->toBe('todas')
        ->and(collect(hu19Props($allAreas)['reporte']['grupos'])->pluck('dimensiones.area_id')->unique())->toHaveCount(2);
});

test('HU19-E07 should_share_the_approved_columns_but_limit_coordinator_content_to_its_area', function (): void {
    // Arrange
    $coordinator = hu19Generate($this, hu19ValidParameters($this->area->id, ['agrupar_por' => ['area', 'indicador']]));
    $sysadmin = $this->actingAs($this->sysadmin)
        ->withHeaders(hu19InertiaHeaders())
        ->get(route('reportes.generate', hu19ValidParameters($this->area->id, ['area' => 'todas', 'agrupar_por' => ['area', 'indicador']])));
    $coordinatorReport = hu19Props($coordinator)['reporte'];
    $sysadminReport = hu19Props($sysadmin)['reporte'];

    // Assert
    expect(array_column($coordinatorReport['columnas'], 'id'))->toBe(array_column($sysadminReport['columnas'], 'id'))
        ->and(collect($coordinatorReport['grupos'])->pluck('dimensiones.area_id')->unique()->all())->toBe([$this->area->id])
        ->and(collect($sysadminReport['grupos'])->pluck('dimensiones.area_id')->unique())->toHaveCount(2);
});

test('HU19-E08 should_deny_specialist_referent_and_guest_users', function (): void {
    // Arrange
    $parameters = hu19ValidParameters($this->area->id);
    $specialist = $this->actingAs($this->specialist)->getJson(route('reportes.generate', $parameters));
    $referent = $this->actingAs($this->referent)->getJson(route('reportes.generate', $parameters));
    Auth::logout();
    $guest = $this->flushHeaders()->withHeaders(['Accept' => 'text/html'])->get(route('reportes.generate', $parameters));

    // Assert
    expect($specialist->getStatusCode())->toBe(403)
        ->and($referent->getStatusCode())->toBe(403)
        ->and($guest->getStatusCode())->toBe(302)
        ->and($guest->headers->get('Location'))->toContain('/login');
});

test('HU19-E09 should_reject_invalid_period_indicator_group_and_area_parameters', function (): void {
    // Arrange
    $invalidRange = $this->getJson(route('reportes.generate', hu19ValidParameters($this->area->id, [
        'desde' => '2025-10-20',
        'hasta' => '2026-10-22',
    ])));
    $invalidIndicator = $this->getJson(route('reportes.generate', hu19ValidParameters($this->area->id, ['indicadores' => ['private_patient_list']])));
    $invalidGroup = $this->getJson(route('reportes.generate', hu19ValidParameters($this->area->id, ['agrupar_por' => ['professional']])));
    $tooManyGroups = $this->getJson(route('reportes.generate', hu19ValidParameters($this->area->id, ['agrupar_por' => ['area', 'mes', 'semana']])));
    $reversedDates = $this->getJson(route('reportes.generate', hu19ValidParameters($this->area->id, ['desde' => '2026-10-20', 'hasta' => '2026-10-10'])));

    // Assert
    expect($invalidRange->getStatusCode())->toBe(422)
        ->and($invalidRange->json('errors'))->toHaveKey('hasta')
        ->and($invalidIndicator->getStatusCode())->toBe(422)
        ->and($invalidIndicator->json('errors'))->toHaveKey('indicadores.0')
        ->and($invalidGroup->getStatusCode())->toBe(422)
        ->and($invalidGroup->json('errors'))->toHaveKey('agrupar_por.0')
        ->and($tooManyGroups->getStatusCode())->toBe(422)
        ->and($tooManyGroups->json('errors'))->toHaveKey('agrupar_por')
        ->and($reversedDates->getStatusCode())->toBe(422)
        ->and($reversedDates->json('errors'))->toHaveKey('hasta');
});

test('HU19-E10 should_not_expose_patient_or_clinical_data_in_the_report_or_audit', function (): void {
    // Arrange
    $patient = $this->file->paciente;
    $patient->forceFill(['carnet' => 'HU19SECRET01', 'nombre_completo' => 'HU19 Private Name'])->save();
    hu19CreateConsultation($this->file, $this->professional, CarbonImmutable::parse('2026-10-20', config('app.timezone')), [
        'notas_clinicas' => 'HU19 private clinical note marker.',
        'diagnostico' => 'HU19 private diagnosis marker.',
    ]);

    // Act
    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.19'])
        ->withHeaders(hu19InertiaHeaders())
        ->get(route('reportes.generate', hu19ValidParameters($this->area->id)));
    $props = hu19Props($response);
    $serialized = json_encode($props, JSON_THROW_ON_ERROR);
    $audit = DB::table('audits')
        ->where('auditable_type', 'App\\Analytics\\Indicadores')
        ->where('auditable_id', 'aggregate')
        ->where('user_id', $this->coordinator->id)
        ->where('event', 'accessed')
        ->whereRaw("new_values::jsonb ->> 'action' = ?", ['generate_parameterized_report'])
        ->latest('id')
        ->first();
    $auditText = json_encode(['old_values' => $audit?->old_values, 'new_values' => $audit?->new_values], JSON_THROW_ON_ERROR);

    // Assert
    expect($response->getStatusCode())->toBe(200)
        ->and($serialized)->not->toContain('HU19SECRET01')
        ->and($serialized)->not->toContain('HU19 Private Name')
        ->and($serialized)->not->toContain('HU19 private clinical note marker.')
        ->and($serialized)->not->toContain('HU19 private diagnosis marker.')
        ->and($serialized)->not->toContain((string) $patient->codigo)
        ->and($audit)->not->toBeNull()
        ->and((string) $audit->ip_address)->toBe('203.0.113.19')
        ->and($audit->created_at)->not->toBeNull()
        ->and($auditText)->not->toContain('HU19SECRET01')
        ->and($auditText)->not->toContain('HU19 private clinical note marker.');
});

test('HU19-E11 should_reproduce_the_same_non_persisted_report_from_the_same_url', function (): void {
    // Arrange
    $url = route('reportes.generate', hu19ValidParameters($this->area->id, ['area' => 'todas', 'agrupar_por' => ['mes', 'indicador']]));

    // Act
    $first = hu19Props($this->actingAs($this->sysadmin)->withHeaders(hu19InertiaHeaders())->get($url))['reporte'];
    $second = hu19Props($this->withHeaders(hu19InertiaHeaders())->get($url))['reporte'];

    // Assert
    expect($second['parametros_aplicados'])->toBe($first['parametros_aplicados'])
        ->and($second['grupos'])->toBe($first['grupos'])
        ->and($second['totales'])->toBe($first['totales'])
        ->and(Schema::hasTable('reportes'))->toBeFalse();
});
