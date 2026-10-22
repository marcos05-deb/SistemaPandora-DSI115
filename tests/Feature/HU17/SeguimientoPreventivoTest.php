<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AlertaPreventiva;
use App\Models\Area;
use App\Models\Carrera;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\EvaluacionRiesgo;
use App\Models\Expediente;
use App\Models\Facultad;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Rubrica;
use App\Models\RubricaVersion;
use App\Models\SeguimientoPreventivo;
use App\Services\AreaEncryptionService;
use App\Services\EvaluadorRiesgoPreventivo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    $this->withoutMiddleware(PreventRequestForgery::class);
    config(['app.area_key_secret' => str_repeat('HU17-area-key-', 4)]);
    $this->withSession(['_sym_key' => base64_encode(str_repeat('s', SODIUM_CRYPTO_SECRETBOX_KEYBYTES))]);

    $this->area = Area::factory()->create();
    $this->coordinator = Especialista::factory()
        ->withRole('area_coordinator')
        ->withProfesional($this->area)
        ->create();
    $this->responsible = Especialista::factory()
        ->withRole('specialist')
        ->withProfesional($this->area)
        ->create();
    $this->otherSpecialist = Especialista::factory()
        ->withRole('specialist')
        ->withProfesional($this->area)
        ->create();
    $this->rubrica = Rubrica::query()
        ->where('codigo', Rubrica::CODIGO_RIESGO_PREVENTIVO)
        ->firstOrFail();
    $this->version = hu17CreateVersion($this->rubrica, $this->coordinator->profesional, 1, now()->subDay());
    $this->alert = hu17CreateAlert($this->area, $this->responsible->profesional, $this->version);

    $this->actingAs($this->responsible);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** @return array<string, string> */
function hu17InertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? '',
        'Accept' => 'application/json',
    ];
}

/** @return array{valoracion: string, accion_preventiva: string, proxima_revision: string} */
function hu17ValidFollowup(?string $responsableId = null, array $overrides = []): array
{
    return array_merge([
        'valoracion' => 'HU17 synthetic assessment for test coverage.',
        'accion_preventiva' => 'HU17 synthetic preventive action for test coverage.',
        'responsable_profesional_id' => $responsableId ?? '',
        'proxima_revision' => now()->addDays(30)->toDateString(),
    ], $overrides);
}

function hu17CreateVersion(
    Rubrica $rubrica,
    Profesional $professional,
    int $number,
    Carbon $effectiveAt,
): RubricaVersion {
    $version = RubricaVersion::query()->create([
        'rubrica_id' => $rubrica->id,
        'numero_version' => $number,
        'vigente_desde' => $effectiveAt,
        'umbral_puntaje_global' => null,
        'justificacion_cambio' => $number === 1 ? null : 'HU17 synthetic rubric change.',
        'validada_en' => now()->subDay()->toDateString(),
        'referencia_validacion' => 'HU17 test validation reference',
        'es_demo' => false,
        'creada_por_profesional_id' => $professional->id,
    ]);

    foreach ([
        ['tipo_senal' => 'crisis_psicologica', 'clave_indicador' => null, 'modo_calculo' => 'frecuencia', 'ventana_dias' => 90, 'ponderacion' => '1.00', 'umbral' => '1.00'],
        ['tipo_senal' => 'ausencia_cita', 'clave_indicador' => null, 'modo_calculo' => 'frecuencia', 'ventana_dias' => 30, 'ponderacion' => '1.00', 'umbral' => '2.00'],
        ['tipo_senal' => 'tendencia_suicida', 'clave_indicador' => 'ideas_suicidas', 'modo_calculo' => 'puntaje', 'ventana_dias' => null, 'ponderacion' => '1.00', 'umbral' => '1.00'],
    ] as $variable) {
        $version->variables()->create($variable);
    }

    return $version;
}

function hu17CreateAlert(
    Area $area,
    Profesional $responsible,
    RubricaVersion $version,
    ?Paciente $patient = null,
    string $state = 'activa',
): AlertaPreventiva {
    $patient ??= Paciente::factory()->create([
        'creado_por_profesional_id' => $responsible->id,
    ]);
    $evaluation = EvaluacionRiesgo::query()->create([
        'paciente_id' => $patient->codigo,
        'area_id' => $area->id,
        'rubrica_version_id' => $version->id,
        'disparador' => 'crisis_psicologica',
        'puntaje' => '1.00',
        'detalle' => 'HU17 synthetic evaluation detail.',
        'estado_evaluacion' => 'evaluada',
        'evento_origen_tipo' => Consulta::class,
        'evento_origen_id' => (string) Str::uuid(),
        'evaluada_en' => now(),
    ]);

    return AlertaPreventiva::query()->create([
        'paciente_id' => $patient->codigo,
        'area_id' => $area->id,
        'evaluacion_riesgo_id' => $evaluation->id,
        'rubrica_version_id' => $version->id,
        'senal' => 'crisis_psicologica',
        'valor_calculado' => '1.00',
        'umbral' => '1.00',
        'estado' => $state,
        'responsable_profesional_id' => $responsible->id,
        'generada_en' => now()->subMinute(),
        'resultado_cierre' => $state === 'cerrada' ? 'HU17 synthetic terminal close result.' : null,
        'justificacion_descarte' => $state === 'descartada' ? 'HU17 synthetic terminal discard reason.' : null,
    ]);
}

/** @return array<string, string> */
function hu17WorkerEnvironment(array $overrides = []): array
{
    $environment = [];
    foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
        if (is_string($key) && (is_string($value) || is_numeric($value) || is_bool($value))) {
            $environment[$key] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }
    }

    return array_merge($environment, [
        'APP_ENV' => 'testing',
        'APP_KEY' => (string) config('app.key'),
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => (string) config('database.connections.pgsql.host'),
        'DB_PORT' => (string) config('database.connections.pgsql.port'),
        'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
        'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
        'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
        'DB_ADMIN_USERNAME' => (string) config('database.connections.pgsql_admin.username'),
        'DB_ADMIN_PASSWORD' => (string) config('database.connections.pgsql_admin.password'),
        'AREA_KEY_SECRET' => (string) config('app.area_key_secret'),
    ], $overrides);
}

test('HU17-E01 should_show_the_alert_pinned_to_its_original_rubric_version', function (): void {
    // Arrange
    $nextVersion = hu17CreateVersion(
        $this->rubrica,
        $this->coordinator->profesional,
        2,
        now()->addDay(),
    );

    // Act
    $response = $this->withHeaders(hu17InertiaHeaders())->get(route('alertas.show', $this->alert->id));
    $page = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

    // Assert
    expect($response->getStatusCode())->toBe(200)
        ->and($page['component'])->toBe('Alertas/Show')
        ->and($page['props']['alerta']['version_rubrica']['numero'])->toBe(1)
        ->and($page['props']['responsableSeguimiento']['id'])->toBe((string) $this->responsible->profesional->id)
        ->and($page['props']['alerta']['version_rubrica']['numero'])->not->toBe($nextVersion->numero_version);
});

test('HU17-E02 should_authorize_alert_detail_by_role_and_area', function (): void {
    // Arrange
    $otherArea = Area::factory()->create();
    $otherAreaSpecialist = Especialista::factory()->withRole('specialist')->withProfesional($otherArea)->create();
    $referent = Especialista::factory()->withRole('psychosocial_referent')->withProfesional($this->area)->create();
    $sysadmin = Especialista::factory()->withRole('sysadmin')->withProfesional($this->area)->create();

    // Act
    $responsibleResponse = $this->actingAs($this->responsible)->withHeaders(hu17InertiaHeaders())->get(route('alertas.show', $this->alert->id));
    $specialistResponse = $this->actingAs($this->otherSpecialist)->withHeaders(hu17InertiaHeaders())->get(route('alertas.show', $this->alert->id));
    $coordinatorResponse = $this->actingAs($this->coordinator)->withHeaders(hu17InertiaHeaders())->get(route('alertas.show', $this->alert->id));
    $otherAreaResponse = $this->actingAs($otherAreaSpecialist)->withHeaders(hu17InertiaHeaders())->get(route('alertas.show', $this->alert->id));
    $referentResponse = $this->actingAs($referent)->withHeaders(hu17InertiaHeaders())->get(route('alertas.show', $this->alert->id));
    $sysadminResponse = $this->actingAs($sysadmin)->withHeaders(hu17InertiaHeaders())->get(route('alertas.show', $this->alert->id));
    Auth::logout();
    $guestResponse = $this->withHeaders(['Accept' => 'text/html'])
        ->get(route('alertas.show', $this->alert->id));
    $responsibleFollowup = $this->actingAs($this->responsible)->post(route('alertas.seguimientos.store', $this->alert->id), hu17ValidFollowup($this->responsible->profesional->id));
    $specialistFollowup = $this->actingAs($this->otherSpecialist)->post(route('alertas.seguimientos.store', $this->alert->id), hu17ValidFollowup($this->otherSpecialist->profesional->id));
    $coordinatorFollowup = $this->actingAs($this->coordinator)->post(route('alertas.seguimientos.store', $this->alert->id), hu17ValidFollowup($this->coordinator->profesional->id));
    $otherAreaFollowup = $this->actingAs($otherAreaSpecialist)->postJson(route('alertas.seguimientos.store', $this->alert->id), hu17ValidFollowup($otherAreaSpecialist->profesional->id));
    $referentFollowup = $this->actingAs($referent)->postJson(route('alertas.seguimientos.store', $this->alert->id), hu17ValidFollowup($referent->profesional->id));
    $sysadminFollowup = $this->actingAs($sysadmin)->postJson(route('alertas.seguimientos.store', $this->alert->id), hu17ValidFollowup($sysadmin->profesional->id));
    Auth::logout();
    $guestFollowup = $this->withHeaders(['Accept' => 'text/html'])
        ->post(route('alertas.seguimientos.store', $this->alert->id), hu17ValidFollowup());

    // Assert
    expect($responsibleResponse->getStatusCode())->toBe(200)
        ->and($specialistResponse->getStatusCode())->toBe(200)
        ->and($coordinatorResponse->getStatusCode())->toBe(200)
        ->and($otherAreaResponse->getStatusCode())->toBeIn([403, 404])
        ->and($referentResponse->getStatusCode())->toBe(403)
        ->and($sysadminResponse->getStatusCode())->toBe(403)
        ->and($guestResponse->getStatusCode())->toBe(302)
        ->and($guestResponse->headers->get('Location'))->toContain('/login')
        ->and($responsibleFollowup->getStatusCode())->toBe(302)
        ->and($specialistFollowup->getStatusCode())->toBe(302)
        ->and($coordinatorFollowup->getStatusCode())->toBe(302)
        ->and($otherAreaFollowup->getStatusCode())->toBeIn([403, 404])
        ->and($referentFollowup->getStatusCode())->toBe(403)
        ->and($sysadminFollowup->getStatusCode())->toBe(403)
        ->and($guestFollowup->getStatusCode())->toBe(302)
        ->and(SeguimientoPreventivo::query()->where('alerta_preventiva_id', $this->alert->id)->count())->toBe(3);
});

test('HU17-E03 should_encrypt_followup_text_with_its_area_and_assign_the_recorder', function (): void {
    // Arrange
    $payload = hu17ValidFollowup($this->responsible->profesional->id);
    $otherArea = Area::factory()->create();
    $otherAreaSpecialist = Especialista::factory()->withRole('specialist')->withProfesional($otherArea)->create();

    // Act
    $response = $this->post(route('alertas.seguimientos.store', $this->alert->id), $payload);
    $tamperedResponsibleResponse = $this->postJson(
        route('alertas.seguimientos.store', $this->alert->id),
        hu17ValidFollowup($otherAreaSpecialist->profesional->id),
    );
    $followup = SeguimientoPreventivo::query()->where('alerta_preventiva_id', $this->alert->id)->firstOrFail();
    $rawFollowup = DB::table('seguimientos_preventivos')->where('id', $followup->id)->first();
    $decryptedValue = app(AreaEncryptionService::class)->decrypt($rawFollowup->valoracion, $this->area->id);
    $wrongAreaRejected = false;
    try {
        app(AreaEncryptionService::class)->decrypt($rawFollowup->valoracion, $otherArea->id);
    } catch (RuntimeException) {
        $wrongAreaRejected = true;
    }

    // Assert
    expect($response->getStatusCode())->toBe(302)
        ->and($followup->responsable_profesional_id)->toBe($this->responsible->profesional->id)
        ->and($followup->registrado_por_profesional_id)->toBe($this->responsible->profesional->id)
        ->and($decryptedValue)->toBe($payload['valoracion'])
        ->and($rawFollowup->valoracion)->not->toBe($payload['valoracion'])
        ->and($rawFollowup->accion_preventiva)->not->toContain($payload['accion_preventiva'])
        ->and($wrongAreaRejected)->toBeTrue()
        ->and($tamperedResponsibleResponse->getStatusCode())->toBe(422)
        ->and($tamperedResponsibleResponse->json('errors'))->toHaveKey('responsable_profesional_id')
        ->and($this->alert->fresh()->estado)->toBe('en_seguimiento');
});

test('HU17-E04 should_preserve_followups_as_append_only_records', function (): void {
    // Arrange
    $this->post(route('alertas.seguimientos.store', $this->alert->id), hu17ValidFollowup($this->responsible->profesional->id));
    $followup = SeguimientoPreventivo::query()->where('alerta_preventiva_id', $this->alert->id)->firstOrFail();

    // Act
    $eloquentUpdateRejected = false;
    try {
        $followup->update(['proxima_revision' => now()->addDays(31)->toDateString()]);
    } catch (LogicException) {
        $eloquentUpdateRejected = true;
    }
    $eloquentDeleteRejected = false;
    try {
        $followup->delete();
    } catch (LogicException) {
        $eloquentDeleteRejected = true;
    }
    $updateRejected = false;
    try {
        DB::connection('pgsql')->transaction(fn () => DB::connection('pgsql')
            ->table('seguimientos_preventivos')
            ->where('id', $followup->id)
            ->update(['proxima_revision' => now()->addDays(31)->toDateString()]));
    } catch (QueryException) {
        $updateRejected = true;
    }
    $deleteRejected = false;
    try {
        DB::connection('pgsql')->transaction(fn () => DB::connection('pgsql')
            ->table('seguimientos_preventivos')
            ->where('id', $followup->id)
            ->delete());
    } catch (QueryException) {
        $deleteRejected = true;
    }

    // Assert
    expect($eloquentUpdateRejected)->toBeTrue()
        ->and($eloquentDeleteRejected)->toBeTrue()
        ->and($updateRejected)->toBeTrue()
        ->and($deleteRejected)->toBeTrue()
        ->and(SeguimientoPreventivo::query()->whereKey($followup->id)->exists())->toBeTrue();
});

test('HU17-E05 should_require_followup_fields_and_enforce_inclusive_review_date_bounds', function (): void {
    // Arrange
    Carbon::setTestNow(Carbon::parse('2026-10-21 10:00:00', config('app.timezone')));
    $url = route('alertas.seguimientos.store', $this->alert->id);
    $missingFieldsResponse = $this->postJson($url, []);
    $responsibleId = $this->responsible->profesional->id;
    $beforeTodayResponse = $this->postJson($url, hu17ValidFollowup($responsibleId, ['proxima_revision' => now()->subDay()->toDateString()]));
    $afterMaximumResponse = $this->postJson($url, hu17ValidFollowup($responsibleId, ['proxima_revision' => now()->addDays(366)->toDateString()]));

    // Act
    $todayResponse = $this->post($url, hu17ValidFollowup($responsibleId, ['proxima_revision' => now()->toDateString()]));
    $maximumDateResponse = $this->post($url, hu17ValidFollowup($responsibleId, ['proxima_revision' => now()->addDays(365)->toDateString()]));

    // Assert
    expect($missingFieldsResponse->getStatusCode())->toBe(422)
        ->and($missingFieldsResponse->json('errors'))->toHaveKeys(['valoracion', 'accion_preventiva', 'responsable_profesional_id', 'proxima_revision'])
        ->and($beforeTodayResponse->getStatusCode())->toBe(422)
        ->and($beforeTodayResponse->json('errors'))->toHaveKey('proxima_revision')
        ->and($afterMaximumResponse->getStatusCode())->toBe(422)
        ->and($afterMaximumResponse->json('errors'))->toHaveKey('proxima_revision')
        ->and($todayResponse->getStatusCode())->toBe(302)
        ->and($maximumDateResponse->getStatusCode())->toBe(302)
        ->and(SeguimientoPreventivo::query()->where('alerta_preventiva_id', $this->alert->id)->count())->toBe(2);
});

test('HU17-E06 should_require_close_and_discard_reasons_in_application_and_database', function (): void {
    // Arrange
    $discardAlert = hu17CreateAlert($this->area, $this->responsible->profesional, $this->version);
    $missingCloseReason = $this->postJson(route('alertas.cerrar', $this->alert->id), []);
    $missingDiscardReason = $this->postJson(route('alertas.descartar', $discardAlert->id), []);

    // Act
    $closeCheckRejected = false;
    try {
        DB::connection('pgsql')->transaction(fn () => DB::connection('pgsql')
            ->table('alertas_preventivas')
            ->where('id', $this->alert->id)
            ->update(['estado' => 'cerrada']));
    } catch (QueryException) {
        $closeCheckRejected = true;
    }
    $discardCheckRejected = false;
    try {
        DB::connection('pgsql')->transaction(fn () => DB::connection('pgsql')
            ->table('alertas_preventivas')
            ->where('id', $discardAlert->id)
            ->update(['estado' => 'descartada']));
    } catch (QueryException) {
        $discardCheckRejected = true;
    }
    $closedResponse = $this->post(route('alertas.cerrar', $this->alert->id), [
        'resultado' => 'HU17 synthetic documented closure result.',
    ]);
    $discardedResponse = $this->post(route('alertas.descartar', $discardAlert->id), [
        'justificacion' => 'HU17 synthetic discard justification.',
    ]);
    $closed = $this->alert->fresh();
    $discarded = $discardAlert->fresh();

    // Assert
    expect($missingCloseReason->getStatusCode())->toBe(422)
        ->and($missingCloseReason->json('errors'))->toHaveKey('resultado')
        ->and($missingDiscardReason->getStatusCode())->toBe(422)
        ->and($missingDiscardReason->json('errors'))->toHaveKey('justificacion')
        ->and($closeCheckRejected)->toBeTrue()
        ->and($discardCheckRejected)->toBeTrue()
        ->and($closedResponse->getStatusCode())->toBe(302)
        ->and($discardedResponse->getStatusCode())->toBe(302)
        ->and($closed->estado)->toBe('cerrada')
        ->and($closed->cerrada_en)->not->toBeNull()
        ->and($closed->cerrada_por_profesional_id)->toBe($this->responsible->profesional->id)
        ->and($discarded->estado)->toBe('descartada')
        ->and($discarded->descartada_en)->not->toBeNull()
        ->and($discarded->descartada_por_profesional_id)->toBe($this->responsible->profesional->id);
});

test('HU17-E07 should_reject_followups_and_transitions_after_terminal_states', function (): void {
    // Arrange
    $closedAlert = hu17CreateAlert($this->area, $this->responsible->profesional, $this->version, state: 'cerrada');
    $discardedAlert = hu17CreateAlert($this->area, $this->responsible->profesional, $this->version, state: 'descartada');

    // Act
    $closedFollowupResponse = $this->postJson(route('alertas.seguimientos.store', $closedAlert->id), hu17ValidFollowup($this->responsible->profesional->id));
    $closedAgainResponse = $this->postJson(route('alertas.cerrar', $closedAlert->id), ['resultado' => 'HU17 another documented closure result.']);
    $closedDiscardResponse = $this->postJson(route('alertas.descartar', $closedAlert->id), ['justificacion' => 'HU17 cross-terminal transition attempt.']);
    $discardedCloseResponse = $this->postJson(route('alertas.cerrar', $discardedAlert->id), ['resultado' => 'HU17 cross-terminal transition attempt.']);
    $discardedAgainResponse = $this->postJson(route('alertas.descartar', $discardedAlert->id), ['justificacion' => 'HU17 another discard justification.']);

    // Assert
    expect($closedFollowupResponse->getStatusCode())->toBeIn([409, 422])
        ->and($closedAgainResponse->getStatusCode())->toBeIn([409, 422])
        ->and($closedDiscardResponse->getStatusCode())->toBeIn([409, 422])
        ->and($discardedCloseResponse->getStatusCode())->toBeIn([409, 422])
        ->and($discardedAgainResponse->getStatusCode())->toBeIn([409, 422])
        ->and($closedAlert->fresh()->estado)->toBe('cerrada')
        ->and($discardedAlert->fresh()->estado)->toBe('descartada')
        ->and(SeguimientoPreventivo::query()->whereIn('alerta_preventiva_id', [$closedAlert->id, $discardedAlert->id])->count())->toBe(0);
});

test('HU17-E08 should_serialize_concurrent_terminal_transitions_under_a_row_lock', function (): void {
    // Arrange
    $admin = DB::connection('pgsql_admin');
    $area = Area::on('pgsql_admin')->create(['nombre' => 'HU17 concurrent area '.Str::uuid(), 'requiere_aprobacion_estricta' => false]);
    $user = Especialista::on('pgsql_admin')->create([
        'name' => 'HU17 concurrent actor',
        'email' => 'hu17-'.Str::uuid().'@example.test',
        'password' => 'test-password',
        'phone' => null,
        'is_active' => true,
        'kdf_salt' => base64_encode(random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES)),
        'failed_login_attempts' => 0,
        'locked_until' => null,
        'must_change_password' => false,
    ]);
    $professional = Profesional::on('pgsql_admin')->create([
        'user_id' => $user->id,
        'area_id' => $area->id,
        'especialidad' => 'HU17 concurrency test',
        'numero_registro' => 'HU17-'.Str::uuid(),
    ]);
    $faculty = Facultad::on('pgsql_admin')->create(['nombre' => 'HU17 faculty '.Str::uuid()]);
    $career = Carrera::on('pgsql_admin')->create(['nombre' => 'HU17 career '.Str::uuid(), 'facultad_id' => $faculty->id]);
    $patient = Paciente::on('pgsql_admin')->create([
        'carnet' => 'HU17-'.Str::uuid(),
        'carrera_id' => $career->id,
        'creado_por_profesional_id' => $professional->id,
        'sexo' => 'M',
        'estado_civil' => 'Soltero',
        'nombre_completo' => 'HU17 concurrent test patient',
        'direccion' => 'HU17 test address',
        'fecha_nacimiento' => '2000-01-01',
        'profesion_ocupacion' => 'HU17 test occupation',
        'fecha_primera_consulta' => now()->toDateString(),
        'motivo_consulta' => 'HU17 test reason',
    ]);
    $concurrentRubrica = Rubrica::on('pgsql_admin')->create([
        'codigo' => 'hu17_concurrent_'.Str::uuid(),
        'nombre' => 'HU17 concurrent test rubric',
    ]);
    $version = RubricaVersion::on('pgsql_admin')->create([
        'rubrica_id' => $concurrentRubrica->id,
        'numero_version' => 1,
        'vigente_desde' => now()->subDay(),
        'umbral_puntaje_global' => null,
        'justificacion_cambio' => 'HU17 concurrent test version',
        'validada_en' => now()->subDay()->toDateString(),
        'referencia_validacion' => 'HU17 concurrent test reference',
        'es_demo' => false,
        'creada_por_profesional_id' => $professional->id,
    ]);
    $evaluation = EvaluacionRiesgo::on('pgsql_admin')->create([
        'paciente_id' => $patient->codigo,
        'area_id' => $area->id,
        'rubrica_version_id' => $version->id,
        'disparador' => 'crisis_psicologica',
        'puntaje' => '1.00',
        'detalle' => 'HU17 concurrent test detail',
        'estado_evaluacion' => 'evaluada',
        'evento_origen_tipo' => Consulta::class,
        'evento_origen_id' => (string) Str::uuid(),
        'evaluada_en' => now(),
    ]);
    $alert = AlertaPreventiva::on('pgsql_admin')->create([
        'paciente_id' => $patient->codigo,
        'area_id' => $area->id,
        'evaluacion_riesgo_id' => $evaluation->id,
        'rubrica_version_id' => $version->id,
        'senal' => 'crisis_psicologica',
        'valor_calculado' => '1.00',
        'umbral' => '1.00',
        'estado' => 'activa',
        'responsable_profesional_id' => $professional->id,
        'generada_en' => now(),
    ]);
    $barrier = sys_get_temp_dir().'/hu17-terminal-'.Str::uuid();
    mkdir($barrier, 0700, true);
    $workerScript = <<<'PHP'
        $base = getenv('HU17_BASE_PATH');
        require $base.'/vendor/autoload.php';
        $app = require $base.'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        file_put_contents(getenv('HU17_ATTEMPTED_FILE'), 'attempting');
        $alert = App\Models\AlertaPreventiva::query()->findOrFail(getenv('HU17_ALERT_ID'));
        $user = App\Models\Especialista::query()->findOrFail(getenv('HU17_USER_ID'));
        try {
            app(App\Services\AlertaSeguimientoService::class)->cerrar(
                $alert,
                $user,
                ['resultado' => 'HU17 concurrent terminal result.'],
            );
            echo json_encode(['result' => 'closed'], JSON_THROW_ON_ERROR);
        } catch (Illuminate\Validation\ValidationException) {
            echo json_encode(['result' => 'terminal'], JSON_THROW_ON_ERROR);
        }
        PHP;
    $makeWorker = static function (string $name) use ($workerScript, $barrier, $alert, $user): Process {
        $process = new Process(
            [PHP_BINARY, '-r', $workerScript],
            base_path(),
            hu17WorkerEnvironment([
                'HU17_BASE_PATH' => base_path(),
                'HU17_ALERT_ID' => (string) $alert->id,
                'HU17_USER_ID' => (string) $user->id,
                'HU17_ATTEMPTED_FILE' => $barrier.'/'.$name,
            ]),
            timeout: 30,
        );
        $process->start();

        return $process;
    };
    $first = null;
    $second = null;
    $lock = $admin->table('alertas_preventivas')->where('id', $alert->id);
    $admin->beginTransaction();

    try {
        $lock->lockForUpdate()->first();
        $first = $makeWorker('first-attempted');
        $second = $makeWorker('second-attempted');
        $deadline = microtime(true) + 15;
        while (! is_file($barrier.'/first-attempted') || ! is_file($barrier.'/second-attempted')) {
            if (microtime(true) > $deadline || ! $first->isRunning() || ! $second->isRunning()) {
                throw new RuntimeException('HU17 concurrent workers did not reach the transition call.');
            }
            usleep(5_000);
        }
        usleep(200_000);
        $bothWaitedForLock = $first->isRunning() && $second->isRunning();
        $admin->commit();
        $first->wait();
        $second->wait();
        $firstResult = json_decode(trim($first->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        $secondResult = json_decode(trim($second->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        $results = [$firstResult['result'], $secondResult['result']];

        // Assert
        expect($bothWaitedForLock)->toBeTrue()
            ->and($first->isSuccessful())->toBeTrue()
            ->and($second->isSuccessful())->toBeTrue()
            ->and(collect($results)->filter(fn (string $result): bool => $result === 'closed')->count())->toBe(1)
            ->and(collect($results)->filter(fn (string $result): bool => $result === 'terminal')->count())->toBe(1)
            ->and($admin->table('alertas_preventivas')->where('id', $alert->id)->value('estado'))->toBe('cerrada');
    } finally {
        if ($admin->transactionLevel() > 0) {
            $admin->rollBack();
        }
        if ($first?->isRunning() || $second?->isRunning()) {
            $first?->stop(1);
            $second?->stop(1);
        }
        $admin->table('audits')->whereIn('auditable_id', [
            $alert->id,
            $evaluation->id,
            $version->id,
            $concurrentRubrica->id,
            $patient->codigo,
            $professional->id,
        ])->delete();
        $admin->table('alertas_preventivas')->where('id', $alert->id)->delete();
        $admin->table('evaluaciones_riesgo')->where('id', $evaluation->id)->delete();
        $admin->table('rubrica_versiones')->where('id', $version->id)->delete();
        $admin->table('rubricas')->where('id', $concurrentRubrica->id)->delete();
        $admin->table('pacientes')->where('codigo', $patient->codigo)->delete();
        $admin->table('carreras')->where('id', $career->id)->delete();
        $admin->table('facultades')->where('id', $faculty->id)->delete();
        $admin->table('profesionales')->where('id', $professional->id)->delete();
        $admin->table('users')->where('id', $user->id)->delete();
        $admin->table('areas')->where('id', $area->id)->delete();
        foreach (glob($barrier.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($barrier);
    }
});

test('HU17-E09 should_audit_followup_actor_ip_and_redact_encrypted_content', function (): void {
    // Arrange
    $payload = hu17ValidFollowup($this->responsible->profesional->id);
    $discardAlert = hu17CreateAlert($this->area, $this->responsible->profesional, $this->version);

    // Act
    $detailResponse = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.41'])
        ->withHeaders(hu17InertiaHeaders())
        ->get(route('alertas.show', $this->alert->id));
    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.41'])
        ->post(route('alertas.seguimientos.store', $this->alert->id), $payload);
    $followup = SeguimientoPreventivo::query()->where('alerta_preventiva_id', $this->alert->id)->firstOrFail();
    $followupAudit = DB::table('audits')
        ->where('auditable_type', SeguimientoPreventivo::class)
        ->where('auditable_id', $followup->id)
        ->where('event', 'created')
        ->latest('id')
        ->first();
    $readAudit = DB::table('audits')
        ->where('auditable_type', AlertaPreventiva::class)
        ->where('auditable_id', $this->alert->id)
        ->where('user_id', $this->responsible->id)
        ->where('event', 'accessed')
        ->latest('id')
        ->first();
    $readValues = json_decode((string) $readAudit?->new_values, true, flags: JSON_THROW_ON_ERROR);
    $closeResponse = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.41'])
        ->post(route('alertas.cerrar', $this->alert->id), ['resultado' => 'HU17 synthetic outcome documenting closure.']);
    $closeAudit = DB::table('audits')
        ->where('auditable_type', AlertaPreventiva::class)
        ->where('auditable_id', $this->alert->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();
    $closeValues = json_decode((string) $closeAudit?->new_values, true, flags: JSON_THROW_ON_ERROR);
    $discardResponse = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.41'])
        ->post(route('alertas.descartar', $discardAlert->id), ['justificacion' => 'HU17 synthetic justification to document discard.']);
    $discardAudit = DB::table('audits')
        ->where('auditable_type', AlertaPreventiva::class)
        ->where('auditable_id', $discardAlert->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();
    $discardValues = json_decode((string) $discardAudit?->new_values, true, flags: JSON_THROW_ON_ERROR);
    $auditText = json_encode([
        'followup_old' => $followupAudit?->old_values,
        'followup_new' => $followupAudit?->new_values,
        'close_old' => $closeAudit?->old_values,
        'close_new' => $closeAudit?->new_values,
        'discard_old' => $discardAudit?->old_values,
        'discard_new' => $discardAudit?->new_values,
    ], JSON_THROW_ON_ERROR);
    $followupValues = json_decode((string) $followupAudit?->new_values, true, flags: JSON_THROW_ON_ERROR);

    // Assert
    expect($response->getStatusCode())->toBe(302)
        ->and($detailResponse->getStatusCode())->toBe(200)
        ->and($readAudit)->not->toBeNull()
        ->and($readValues['action'])->toBe('read_alert_detail')
        ->and($readAudit->user_id)->toBe($this->responsible->id)
        ->and((string) $readAudit->ip_address)->toBe('203.0.113.41')
        ->and($followupAudit)->not->toBeNull()
        ->and($followupAudit->user_id)->toBe($this->responsible->id)
        ->and((string) $followupAudit->ip_address)->toBe('203.0.113.41')
        ->and($followupValues['valoracion'])->toBe('[CIFRADO]')
        ->and($followupValues['accion_preventiva'])->toBe('[CIFRADO]')
        ->and($closeResponse->getStatusCode())->toBe(302)
        ->and($closeAudit)->not->toBeNull()
        ->and((string) $closeAudit->ip_address)->toBe('203.0.113.41')
        ->and($closeValues['resultado_cierre'])->toBe('[CIFRADO]')
        ->and($closeValues['cerrada_por_profesional_id'])->toBe('[CIFRADO]')
        ->and($discardResponse->getStatusCode())->toBe(302)
        ->and($discardAudit)->not->toBeNull()
        ->and((string) $discardAudit->ip_address)->toBe('203.0.113.41')
        ->and($discardValues['justificacion_descarte'])->toBe('[CIFRADO]')
        ->and($discardValues['descartada_por_profesional_id'])->toBe('[CIFRADO]')
        ->and($auditText)->not->toContain($payload['valoracion'])
        ->and($auditText)->not->toContain($payload['accion_preventiva'])
        ->and($auditText)->not->toContain('HU17 synthetic outcome documenting closure.')
        ->and($auditText)->not->toContain('HU17 synthetic justification to document discard.');
});

test('HU17-E10 should_create_a_new_alert_when_the_same_signal_recurs_after_closure', function (): void {
    // Arrange
    $patient = $this->alert->paciente;
    $expediente = Expediente::factory()->create([
        'paciente_id' => $patient->codigo,
        'area_id' => $this->area->id,
        'estado' => Expediente::ESTADO_ABIERTO,
        'derivado_por_profesional_id' => $this->responsible->profesional->id,
        'fecha_derivacion' => now()->subDays(10),
    ]);
    $this->post(route('alertas.cerrar', $this->alert->id), ['resultado' => 'HU17 synthetic closure result.']);
    $consulta = Consulta::query()->create([
        'expediente_id' => $expediente->id,
        'profesional_id' => $this->responsible->profesional->id,
        'motivo_consulta' => 'HU17 synthetic recurrence consultation.',
        'notas_clinicas' => 'HU17 synthetic recurrence note.',
        'diagnostico' => null,
        'plan_atencion' => 'HU17 synthetic plan.',
        'fecha_consulta' => now(),
        'tecnica_utilizada' => 'HU17 test technique',
        'evaluacion_inicial' => [],
        'crisis_psicologica' => true,
        'indicadores_tendencia_suicida' => [],
    ]);

    // Act
    app(EvaluadorRiesgoPreventivo::class)->evaluarConsulta($consulta);
    $alerts = AlertaPreventiva::query()->where('paciente_id', $patient->codigo)
        ->where('area_id', $this->area->id)
        ->where('senal', 'crisis_psicologica')
        ->orderBy('generada_en')
        ->get();

    // Assert
    expect($alerts)->toHaveCount(2)
        ->and($alerts->pluck('estado')->all())->toBe(['cerrada', 'activa'])
        ->and($alerts->pluck('id')->unique()->count())->toBe(2);
});

test('HU17-E11 should_expose_action_permissions_in_inertia_can_props', function (): void {
    // Arrange
    $specialist = $this->actingAs($this->otherSpecialist)
        ->withHeaders(hu17InertiaHeaders())
        ->get(route('alertas.show', $this->alert->id));
    $coordinator = $this->actingAs($this->coordinator)
        ->withHeaders(hu17InertiaHeaders())
        ->get(route('alertas.show', $this->alert->id));
    $responsible = $this->actingAs($this->responsible)
        ->withHeaders(hu17InertiaHeaders())
        ->get(route('alertas.show', $this->alert->id));
    $specialistCan = json_decode($specialist->getContent(), true, flags: JSON_THROW_ON_ERROR)['props']['alerta']['can'];
    $coordinatorCan = json_decode($coordinator->getContent(), true, flags: JSON_THROW_ON_ERROR)['props']['alerta']['can'];
    $responsibleCan = json_decode($responsible->getContent(), true, flags: JSON_THROW_ON_ERROR)['props']['alerta']['can'];

    // Act
    $referent = Especialista::factory()->withRole('psychosocial_referent')->withProfesional($this->area)->create();
    $referentResponse = $this->actingAs($referent)
        ->withHeaders(hu17InertiaHeaders())
        ->get(route('alertas.show', $this->alert->id));

    // Assert
    expect($specialist->getStatusCode())->toBe(200)
        ->and($specialistCan)->toMatchArray(['seguimiento' => true, 'cerrar' => false, 'descartar' => false])
        ->and($coordinatorCan)->toMatchArray(['seguimiento' => true, 'cerrar' => true, 'descartar' => true])
        ->and($responsibleCan)->toMatchArray(['seguimiento' => true, 'cerrar' => true, 'descartar' => true])
        ->and($referentResponse->getStatusCode())->toBe(403);
});

test('HU17-E12 should_complete_followup_and_close_flow_with_encrypted_resolution', function (): void {
    // Arrange
    $followupPayload = hu17ValidFollowup($this->responsible->profesional->id);

    // Act
    $followupResponse = $this->post(route('alertas.seguimientos.store', $this->alert->id), $followupPayload);
    $closeResponse = $this->post(route('alertas.cerrar', $this->alert->id), [
        'resultado' => 'HU17 synthetic outcome documenting closure.',
    ]);
    $detailResponse = $this->withHeaders(hu17InertiaHeaders())->get(route('alertas.show', $this->alert->id));
    $detail = json_decode($detailResponse->getContent(), true, flags: JSON_THROW_ON_ERROR)['props']['alerta'];
    $rawAlert = DB::table('alertas_preventivas')->where('id', $this->alert->id)->first();

    // Assert
    expect($followupResponse->getStatusCode())->toBe(302)
        ->and($closeResponse->getStatusCode())->toBe(302)
        ->and($detailResponse->getStatusCode())->toBe(200)
        ->and($detail['estado'])->toBe('cerrada')
        ->and($detail['seguimientos'])->toHaveCount(1)
        ->and($detail['resultado_cierre'])->toBe('HU17 synthetic outcome documenting closure.')
        ->and($detail['can'])->toMatchArray(['seguimiento' => false, 'cerrar' => false, 'descartar' => false])
        ->and($rawAlert->resultado_cierre)->not->toContain('HU17 synthetic outcome documenting closure.');
});

test('HU17-E13 should_allow_terminal_transitions_only_to_the_responsible_or_area_coordinator', function (): void {
    // Arrange
    $specialistResponse = $this->actingAs($this->otherSpecialist)
        ->postJson(route('alertas.cerrar', $this->alert->id), ['resultado' => 'HU17 synthetic closure result.']);
    $otherArea = Area::factory()->create();
    $otherAreaSpecialist = Especialista::factory()->withRole('specialist')->withProfesional($otherArea)->create();
    $otherAreaResponse = $this->actingAs($otherAreaSpecialist)
        ->postJson(route('alertas.cerrar', $this->alert->id), ['resultado' => 'HU17 synthetic closure result.']);
    $referent = Especialista::factory()->withRole('psychosocial_referent')->withProfesional($this->area)->create();
    $referentResponse = $this->actingAs($referent)
        ->post(route('alertas.descartar', $this->alert->id), ['justificacion' => 'HU17 synthetic discard justification.']);
    $sysadmin = Especialista::factory()->withRole('sysadmin')->withProfesional($this->area)->create();
    $sysadminResponse = $this->actingAs($sysadmin)
        ->postJson(route('alertas.cerrar', $this->alert->id), ['resultado' => 'HU17 synthetic closure result.']);
    Auth::logout();
    $guestResponse = $this->withHeaders(['Accept' => 'text/html'])
        ->post(route('alertas.cerrar', $this->alert->id), ['resultado' => 'HU17 synthetic closure result.']);
    $coordinatorResponse = $this->actingAs($this->coordinator)
        ->post(route('alertas.descartar', $this->alert->id), ['justificacion' => 'HU17 synthetic discard justification.']);

    // Act
    $freshAlert = $this->alert->fresh();

    // Assert
    expect($specialistResponse->getStatusCode())->toBe(403)
        ->and($otherAreaResponse->getStatusCode())->toBeIn([403, 404])
        ->and($referentResponse->getStatusCode())->toBe(403)
        ->and($sysadminResponse->getStatusCode())->toBe(403)
        ->and($guestResponse->getStatusCode())->toBe(302)
        ->and($coordinatorResponse->getStatusCode())->toBe(302)
        ->and($freshAlert->estado)->toBe('descartada')
        ->and($freshAlert->descartada_por_profesional_id)->toBe($this->coordinator->profesional->id);
});
