<?php

declare(strict_types=1);

use App\DTO\FiltrosIndicador;
use App\Enums\TipoIndicador;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Area;
use App\Models\Carrera;
use App\Models\Cita;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Facultad;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Rubrica;
use App\Models\RubricaVersion;
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
    config(['app.area_key_secret' => str_repeat('HU18-area-key-', 4)]);
    Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00', config('app.timezone')));
    $this->withSession(['_sym_key' => base64_encode(str_repeat('h', SODIUM_CRYPTO_SECRETBOX_KEYBYTES))]);

    $this->area = Area::factory()->create(['nombre' => 'HU18 Psychology']);
    $this->otherArea = Area::factory()->create(['nombre' => 'HU18 Nutrition']);
    $this->coordinator = Especialista::factory()
        ->withRole('area_coordinator')
        ->withProfesional($this->area)
        ->create();
    $this->coordinatorProfessional = $this->coordinator->profesional;
    $this->specialist = Especialista::factory()
        ->withRole('specialist')
        ->withProfesional($this->area)
        ->create();
    $this->otherAreaSpecialist = Especialista::factory()
        ->withRole('specialist')
        ->withProfesional($this->otherArea)
        ->create();
    $this->referent = Especialista::factory()
        ->withRole('psychosocial_referent')
        ->withProfesional($this->area)
        ->create();
    $this->sysadmin = Especialista::factory()->withRole('sysadmin')->create();

    $faculty = Facultad::factory()->create();
    $career = Carrera::factory()->create(['facultad_id' => $faculty->id]);
    [$this->patient, $this->expediente] = hu18CreatePatientFile(
        $this->area,
        $this->coordinatorProfessional,
        $career->id,
    );
    [$this->otherPatient, $this->otherExpediente] = hu18CreatePatientFile(
        $this->otherArea,
        $this->otherAreaSpecialist->profesional,
        $career->id,
    );
    $this->version = hu18CreateDemoVersion($this->coordinatorProfessional);
    $this->actingAs($this->coordinator);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** @return array<string, string> */
function hu18InertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? '',
        'Accept' => 'application/json',
    ];
}

/** @return array<string, mixed> */
function hu18Page(TestResponse $response): array
{
    return json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)['props'];
}

/** @return array{0: Paciente, 1: Expediente} */
function hu18CreatePatientFile(Area $area, Profesional $professional, int $careerId, array $overrides = []): array
{
    $patient = Paciente::factory()->create([
        'carrera_id' => $careerId,
        'creado_por_profesional_id' => $professional->id,
        ...($overrides['patient'] ?? []),
    ]);
    $expediente = Expediente::factory()->create([
        'paciente_id' => $patient->codigo,
        'area_id' => $area->id,
        'estado' => Expediente::ESTADO_ABIERTO,
        'derivado_por_profesional_id' => $professional->id,
        'fecha_derivacion' => now()->subDays(10),
        ...($overrides['expediente'] ?? []),
    ]);

    return [$patient, $expediente];
}

function hu18CreateDemoVersion(Profesional $professional): RubricaVersion
{
    $rubrica = Rubrica::query()->where('codigo', Rubrica::CODIGO_RIESGO_PREVENTIVO)->firstOrFail();

    return $rubrica->versiones()->create([
        'numero_version' => 1,
        'vigente_desde' => now()->subDay(),
        'umbral_puntaje_global' => null,
        'justificacion_cambio' => null,
        'validada_en' => null,
        'referencia_validacion' => null,
        'es_demo' => true,
        'creada_por_profesional_id' => $professional->id,
    ]);
}

function hu18CreateConsultation(Expediente $expediente, Profesional $professional, CarbonImmutable $date, array $overrides = []): Consulta
{
    $consulta = new Consulta;
    $consulta->expediente_id = $expediente->id;
    $consulta->setRelation('expediente', $expediente);
    $consulta->fill(array_merge([
        'profesional_id' => $professional->id,
        'motivo_consulta' => 'HU18 synthetic consultation reason.',
        'notas_clinicas' => 'HU18 synthetic private consultation note.',
        'diagnostico' => 'HU18 synthetic diagnosis.',
        'plan_atencion' => 'HU18 synthetic plan for continued care.',
        'tecnica_utilizada' => 'HU18 synthetic technique',
        'fecha_consulta' => $date,
        'evaluacion_inicial' => [],
        'crisis_psicologica' => 'No',
        'indicadores_tendencia_suicida' => [],
    ], $overrides));
    $consulta->save();

    return $consulta;
}

function hu18CreateAppointment(Expediente $expediente, Profesional $professional, string $status, CarbonImmutable $date): Cita
{
    $cita = new Cita;
    $cita->expediente_id = $expediente->id;
    $cita->profesional_id = $professional->id;
    $cita->area_id = $expediente->area_id;
    $cita->fecha_hora = $date;
    $cita->motivo = 'HU18 synthetic appointment.';
    $cita->estado = $status;

    if (in_array($status, ['asistida', 'ausente'], true)) {
        $cita->fecha_registro_asistencia = $date->addMinutes(30);
        $cita->registrado_por_profesional_id = $professional->id;
    } elseif ($status === 'cancelada') {
        $cita->motivo_cancelacion = 'HU18 synthetic cancellation reason.';
        $cita->fecha_cancelacion = $date->addMinutes(30);
        $cita->cancelado_por_profesional_id = $professional->id;
    } elseif ($status === 'reprogramada') {
        $cita->motivo_reprogramacion = 'HU18 synthetic reschedule reason.';
        $cita->fecha_reprogramacion = $date->addMinutes(30);
        $cita->reprogramado_por_profesional_id = $professional->id;
    }

    $cita->save();

    return $cita;
}

function hu18Get(TestCase $test, array $filters = []): TestResponse
{
    return $test->withHeaders(hu18InertiaHeaders())->get(route('indicadores.index', $filters));
}

/** @return array<string, mixed> */
function hu18Props(TestResponse $response): array
{
    return hu18Page($response);
}

function hu18Value(array $result, string $indicator): mixed
{
    return $result['totales'][$indicator]['valor'] ?? null;
}

function hu18Row(array $result, string $indicator, int $areaId, ?string $segment = null): ?array
{
    return collect($result['filas'])->first(fn (array $row): bool => $row['indicador'] === $indicator
        && (int) $row['area_id'] === $areaId
        && ($segment === null || ($row['segmento'] ?? null) === $segment));
}

test('HU18-E02 should_apply_complementary_suppression_when_one_area_cell_is_small', function (): void {
    // Arrange
    $day = CarbonImmutable::parse('2026-10-10 12:00:00', config('app.timezone'));
    foreach (range(1, 4) as $index) {
        hu18CreateConsultation($this->expediente, $this->coordinatorProfessional, $day->startOfDay()->addHours($index));
    }
    foreach (range(1, 8) as $index) {
        hu18CreateConsultation($this->otherExpediente, $this->otherAreaSpecialist->profesional, $day->startOfDay()->addHours($index));
    }

    // Act
    $response = $this->actingAs($this->sysadmin)->withHeaders(hu18InertiaHeaders())->get(route('indicadores.index', [
        'desde' => '2026-10-10',
        'hasta' => '2026-10-10',
        'indicadores' => [TipoIndicador::ConsultasRegistradas->value],
    ]));
    $result = hu18Props($response)['resultados'];

    // Assert
    expect($response->getStatusCode())->toBe(200)
        ->and(hu18Row($result, TipoIndicador::ConsultasRegistradas->value, (int) $this->area->id)['supresion'])->toBe('primaria')
        ->and(hu18Row($result, TipoIndicador::ConsultasRegistradas->value, (int) $this->otherArea->id)['supresion'])->toBe('complementaria')
        ->and($result['totales'][TipoIndicador::ConsultasRegistradas->value]['supresion'])->toBe('complementaria');
});

test('HU18-E03 should_filter_inclusive_local_day_boundaries_using_utc_ranges', function (): void {
    // Arrange
    $day = CarbonImmutable::parse('2026-10-01', config('app.timezone'));
    foreach ([
        $day->startOfDay(),
        $day->startOfDay()->addHours(8),
        $day->endOfDay()->subSecond(),
        $day->endOfDay(),
        $day->startOfDay()->addHours(12),
    ] as $date) {
        hu18CreateConsultation($this->expediente, $this->coordinatorProfessional, $date);
    }
    hu18CreateConsultation($this->expediente, $this->coordinatorProfessional, $day->addDay()->startOfDay());

    // Act
    $response = hu18Get($this, [
        'desde' => '2026-10-01',
        'hasta' => '2026-10-01',
        'area_id' => $this->area->id,
        'indicadores' => [TipoIndicador::ConsultasRegistradas->value],
    ]);
    $result = hu18Props($response)['resultados'];

    // Assert
    expect($response->getStatusCode())->toBe(200)
        ->and(hu18Value($result, TipoIndicador::ConsultasRegistradas->value))->toBe(5)
        ->and(hu18Row($result, TipoIndicador::ConsultasRegistradas->value, (int) $this->area->id)['valor'])->toBe(5);
});

test('HU18-E05 should_count_each_patient_once_in_the_institution_total_across_areas', function (): void {
    // Arrange
    $day = CarbonImmutable::parse('2026-10-10', config('app.timezone'));
    $sharedPatientFile = Expediente::factory()->create([
        'paciente_id' => $this->patient->codigo,
        'area_id' => $this->otherArea->id,
        'estado' => Expediente::ESTADO_ABIERTO,
        'derivado_por_profesional_id' => $this->otherAreaSpecialist->profesional->id,
        'fecha_derivacion' => $day,
    ]);
    hu18CreateConsultation($this->expediente, $this->coordinatorProfessional, $day);
    hu18CreateConsultation($sharedPatientFile, $this->otherAreaSpecialist->profesional, $day);
    hu18CreateConsultation($this->otherExpediente, $this->otherAreaSpecialist->profesional, $day->addHours(1));

    foreach (range(1, 5) as $index) {
        [$patient, $file] = hu18CreatePatientFile($this->area, $this->coordinatorProfessional, $this->patient->carrera_id);
        hu18CreateConsultation($file, $this->coordinatorProfessional, $day->addHours($index + 1));
    }
    foreach (range(1, 4) as $index) {
        [$patient, $file] = hu18CreatePatientFile($this->otherArea, $this->otherAreaSpecialist->profesional, $this->patient->carrera_id);
        hu18CreateConsultation($file, $this->otherAreaSpecialist->profesional, $day->addHours($index + 7));
    }

    // Act
    $response = $this->actingAs($this->sysadmin)->withHeaders(hu18InertiaHeaders())->get(route('indicadores.index', [
        'desde' => '2026-10-10',
        'hasta' => '2026-10-10',
        'indicadores' => [TipoIndicador::PacientesAtendidos->value],
    ]));
    $result = hu18Props($response)['resultados'];

    // Assert
    expect($response->getStatusCode())->toBe(200)
        ->and(hu18Row($result, TipoIndicador::PacientesAtendidos->value, (int) $this->area->id)['valor'])->toBe(6)
        ->and(hu18Row($result, TipoIndicador::PacientesAtendidos->value, (int) $this->otherArea->id)['valor'])->toBe(6)
        ->and(hu18Value($result, TipoIndicador::PacientesAtendidos->value))->toBe(11);
});

test('HU18-E04 should_limit_area_scope_to_coordinator_and_allow_sysadmin_all_or_one', function (): void {
    // Arrange
    $coordinatorResponse = hu18Get($this, ['area_id' => $this->area->id]);
    $foreignAreaResponse = hu18Get($this, ['area_id' => $this->otherArea->id]);
    $allAreasResponse = $this->actingAs($this->sysadmin)->withHeaders(hu18InertiaHeaders())->get(route('indicadores.index'));
    $oneAreaResponse = $this->withHeaders(hu18InertiaHeaders())->get(route('indicadores.index', ['area_id' => $this->otherArea->id]));

    $this->actingAs($this->coordinator)->flushHeaders()->get(route('indicadores.index', ['area_id' => $this->area->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Indicadores/Index')
            ->where('layout', 'clinical')
            ->where('can.indicadores', true));
    $this->actingAs($this->sysadmin)->flushHeaders()->get(route('indicadores.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Indicadores/Index')
            ->where('layout', 'admin')
            ->where('can.indicadores', true));

    // Assert
    expect($coordinatorResponse->getStatusCode())->toBe(200)
        ->and($foreignAreaResponse->getStatusCode())->toBe(403)
        ->and($allAreasResponse->getStatusCode())->toBe(200)
        ->and(hu18Props($allAreasResponse)['resultados']['areas'])->toHaveCount(2)
        ->and($oneAreaResponse->getStatusCode())->toBe(200)
        ->and(hu18Props($oneAreaResponse)['resultados']['areas'])->toHaveCount(1)
        ->and(hu18Props($oneAreaResponse)['resultados']['areas_disponibles'])->toHaveCount(2);
});

test('HU18-E04 should_revoke_area_access_when_the_coordinator_profile_is_archived', function (): void {
    // Arrange
    $this->coordinatorProfessional->delete();

    // Act
    $response = hu18Get($this);

    // Assert
    expect($response->getStatusCode())->toBe(403);
});

test('HU18-E05 should_combine_period_area_and_indicator_filters', function (): void {
    // Arrange
    $day = CarbonImmutable::parse('2026-10-10', config('app.timezone'));
    foreach (range(1, 6) as $index) {
        hu18CreateConsultation($this->expediente, $this->coordinatorProfessional, $day->addHours($index));
    }
    foreach (range(1, 7) as $index) {
        hu18CreateAppointment($this->expediente, $this->coordinatorProfessional, 'asistida', $day->addHours($index));
    }

    // Act
    $consultasResponse = hu18Get($this, [
        'desde' => '2026-10-10',
        'hasta' => '2026-10-10',
        'area_id' => $this->area->id,
        'indicadores' => [TipoIndicador::ConsultasRegistradas->value],
    ]);
    $citasResponse = hu18Get($this, [
        'desde' => '2026-10-10',
        'hasta' => '2026-10-10',
        'area_id' => $this->area->id,
        'indicadores' => [TipoIndicador::CitasAsistidas->value],
    ]);
    $consultas = hu18Props($consultasResponse);
    $citas = hu18Props($citasResponse);

    // Assert
    expect($consultasResponse->getStatusCode())->toBe(200)
        ->and(array_keys($consultas['resultados']['totales']))->toBe([TipoIndicador::ConsultasRegistradas->value])
        ->and(hu18Value($consultas['resultados'], TipoIndicador::ConsultasRegistradas->value))->toBe(6)
        ->and(array_keys($citas['resultados']['totales']))->toBe([TipoIndicador::CitasAsistidas->value])
        ->and(hu18Value($citas['resultados'], TipoIndicador::CitasAsistidas->value))->toBe(7);
});

test('HU18-E06 should_return_zero_totals_without_an_error_when_the_period_is_empty', function (): void {
    // Arrange
    $filters = [
        'desde' => '2000-01-01',
        'hasta' => '2000-01-30',
        'area_id' => $this->area->id,
    ];

    // Act
    $response = hu18Get($this, $filters);
    $result = hu18Props($response)['resultados'];

    // Assert
    expect($response->getStatusCode())->toBe(200)
        ->and($result['sin_datos'])->toBeTrue()
        ->and(collect($result['totales'])->pluck('valor')->unique()->all())->toBe([0]);
});

test('HU18-E07 should_reject_invalid_dates_periods_areas_and_indicator_values', function (): void {
    // Arrange
    $invalidDate = $this->getJson(route('indicadores.index', ['desde' => 'bad-date']));
    $invalidRange = $this->getJson(route('indicadores.index', ['desde' => '2025-10-20', 'hasta' => '2026-10-22']));
    $maximumRange = $this->getJson(route('indicadores.index', ['desde' => '2025-10-22', 'hasta' => '2026-10-22']));
    $reversedRange = $this->getJson(route('indicadores.index', ['desde' => '2026-10-20', 'hasta' => '2026-10-10']));
    $invalidArea = $this->getJson(route('indicadores.index', ['area_id' => 'not-an-id']));
    $invalidIndicator = $this->getJson(route('indicadores.index', ['indicadores' => ['private_patient_list']]));

    // Assert
    expect($invalidDate->getStatusCode())->toBe(422)
        ->and($invalidDate->json('errors'))->toHaveKey('desde')
        ->and($invalidRange->getStatusCode())->toBe(422)
        ->and($invalidRange->json('errors'))->toHaveKey('hasta')
        ->and($maximumRange->getStatusCode())->toBe(200)
        ->and($reversedRange->getStatusCode())->toBe(422)
        ->and($reversedRange->json('errors'))->toHaveKey('hasta')
        ->and($invalidArea->getStatusCode())->toBe(422)
        ->and($invalidArea->json('errors'))->toHaveKey('area_id')
        ->and($invalidIndicator->getStatusCode())->toBe(422)
        ->and($invalidIndicator->json('errors'))->toHaveKey('indicadores.0');
});

test('HU18-E08 should_never_serialize_patient_identifiers_or_clinical_text', function (): void {
    // Arrange
    $this->patient->forceFill([
        'carnet' => 'HU18SECRET01',
        'nombre_completo' => 'HU18 Private Patient Name',
    ])->save();
    hu18CreateConsultation($this->expediente, $this->coordinatorProfessional, now()->subDay()->toImmutable(), [
        'notas_clinicas' => 'HU18 private clinical note marker.',
        'diagnostico' => 'HU18 private diagnosis marker.',
    ]);

    // Act
    $response = hu18Get($this, ['area_id' => $this->area->id]);
    $serialized = json_encode(hu18Props($response), JSON_THROW_ON_ERROR);
    $audit = DB::table('audits')
        ->where('auditable_type', 'App\\Analytics\\Indicadores')
        ->where('auditable_id', 'aggregate')
        ->where('user_id', $this->coordinator->id)
        ->where('event', 'accessed')
        ->latest('id')
        ->first();
    $auditValues = json_decode((string) $audit?->new_values, true, flags: JSON_THROW_ON_ERROR);
    $auditText = json_encode(['old_values' => $audit?->old_values, 'new_values' => $audit?->new_values], JSON_THROW_ON_ERROR);

    // Assert
    expect($response->getStatusCode())->toBe(200)
        ->and($serialized)->not->toContain('HU18SECRET01')
        ->and($serialized)->not->toContain('HU18 Private Patient Name')
        ->and($serialized)->not->toContain('HU18 private clinical note marker.')
        ->and($serialized)->not->toContain('HU18 private diagnosis marker.')
        ->and($serialized)->not->toContain((string) $this->patient->codigo)
        ->and($audit)->not->toBeNull()
        ->and((string) $audit->user_id)->toBe((string) $this->coordinator->id)
        ->and($audit->event)->toBe('accessed')
        ->and($audit->auditable_type)->toBe('App\\Analytics\\Indicadores')
        ->and($audit->auditable_id)->toBe('aggregate')
        ->and($audit->created_at)->not->toBeNull()
        ->and($audit->ip_address)->not->toBeNull()
        ->and($auditValues['action'])->toBe('read_institutional_indicators')
        ->and($auditValues['filters']['area_id'])->toBe($this->area->id)
        ->and($auditText)->not->toContain('HU18SECRET01')
        ->and($auditText)->not->toContain('HU18 Private Patient Name')
        ->and($auditText)->not->toContain('HU18 private clinical note marker.');
});

test('HU18-E09 should_suppress_small_cells_and_their_complementary_area_cell', function (): void {
    // Arrange
    $day = CarbonImmutable::parse('2026-10-10', config('app.timezone'));
    foreach (range(1, 2) as $index) {
        hu18CreateAppointment($this->expediente, $this->coordinatorProfessional, 'asistida', $day->addHours($index));
    }
    foreach (range(1, 6) as $index) {
        hu18CreateAppointment($this->otherExpediente, $this->otherAreaSpecialist->profesional, 'asistida', $day->addHours($index));
    }

    // Act
    $response = $this->actingAs($this->sysadmin)->withHeaders(hu18InertiaHeaders())->get(route('indicadores.index', [
        'desde' => '2026-10-10',
        'hasta' => '2026-10-10',
        'indicadores' => [TipoIndicador::CitasAsistidas->value],
    ]));
    $result = hu18Props($response)['resultados'];

    // Assert
    expect($response->getStatusCode())->toBe(200)
        ->and(hu18Row($result, TipoIndicador::CitasAsistidas->value, (int) $this->area->id)['supresion'])->toBe('primaria')
        ->and(hu18Row($result, TipoIndicador::CitasAsistidas->value, (int) $this->otherArea->id)['supresion'])->toBe('complementaria')
        ->and($result['totales'][TipoIndicador::CitasAsistidas->value]['valor'])->toBeNull();
});

test('HU18-E10 should_authorize_only_coordinators_and_sysadmins', function (): void {
    // Arrange
    $coordinator = hu18Get($this, []);
    $sysadmin = $this->actingAs($this->sysadmin)->withHeaders(hu18InertiaHeaders())->get(route('indicadores.index'));
    $specialist = $this->actingAs($this->specialist)->getJson(route('indicadores.index'));
    $otherAreaSpecialist = $this->actingAs($this->otherAreaSpecialist)->getJson(route('indicadores.index'));
    $referent = $this->actingAs($this->referent)->getJson(route('indicadores.index'));
    Auth::logout();
    $guest = $this->withHeaders(['Accept' => 'text/html'])->get(route('indicadores.index'));

    // Assert
    expect($coordinator->getStatusCode())->toBe(200)
        ->and($sysadmin->getStatusCode())->toBe(200)
        ->and($specialist->getStatusCode())->toBe(403)
        ->and($otherAreaSpecialist->getStatusCode())->toBe(403)
        ->and($referent->getStatusCode())->toBe(403)
        ->and($guest->getStatusCode())->toBe(302)
        ->and($guest->headers->get('Location'))->toContain('/login');
});

test('HU18-E11 should_execute_a_fixed_number_of_aggregate_source_queries', function (): void {
    // Arrange
    $service = app(IndicadorService::class);
    $filters = new FiltrosIndicador(
        '2026-10-01',
        '2026-10-22',
        null,
        TipoIndicador::valores(),
    );
    $connection = DB::connection();
    $connection->flushQueryLog();
    $connection->enableQueryLog();

    // Act
    $service->calcular($filters, $this->sysadmin);
    $firstSourceQueryCount = hu18AggregateQueryCount($connection->getQueryLog());
    $extraArea = Area::factory()->create(['nombre' => 'HU18 third area']);
    $connection->flushQueryLog();
    $service->calcular($filters, $this->sysadmin);
    $secondSourceQueryCount = hu18AggregateQueryCount($connection->getQueryLog());
    $connection->disableQueryLog();

    // Assert
    expect($firstSourceQueryCount)->toBe(5)
        ->and($secondSourceQueryCount)->toBe(5)
        ->and(Area::query()->whereKey($extraArea->id)->exists())->toBeTrue();
});

test('HU18-E12 should_return_only_arrays_and_scalars_from_the_aggregate_service', function (): void {
    // Arrange
    $filters = new FiltrosIndicador(
        '2026-10-01',
        '2026-10-22',
        $this->area->id,
        TipoIndicador::valores(),
    );

    // Act
    $result = app(IndicadorService::class)->calcular($filters, $this->coordinator)->toArray();
    $isPlainData = hu18ContainsOnlyArraysAndScalars($result);

    // Assert
    expect($isPlainData)->toBeTrue()
        ->and(json_encode($result, JSON_THROW_ON_ERROR))->not->toContain((string) $this->patient->codigo);
});

function hu18AggregateQueryCount(array $queries): int
{
    return collect($queries)->filter(static fn (array $query): bool => str_contains(strtolower($query['query']), ' from "consultas')
        || str_contains(strtolower($query['query']), ' from "expedientes')
        || str_contains(strtolower($query['query']), ' from "citas')
        || str_contains(strtolower($query['query']), ' from "alertas_preventivas')
        || str_contains(strtolower($query['query']), ' from "seguimientos_preventivos'))->count();
}

function hu18ContainsOnlyArraysAndScalars(mixed $value): bool
{
    if (is_array($value)) {
        foreach ($value as $child) {
            if (! hu18ContainsOnlyArraysAndScalars($child)) {
                return false;
            }
        }

        return true;
    }

    return is_scalar($value) || $value === null;
}
