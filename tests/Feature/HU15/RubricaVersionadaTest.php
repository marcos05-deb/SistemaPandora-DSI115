<?php

declare(strict_types=1);

use App\Exceptions\RubricaInmutableException;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Area;
use App\Models\Especialista;
use App\Models\EvaluacionRiesgo;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Rubrica;
use App\Models\RubricaVariable;
use App\Models\RubricaVersion;
use App\Services\RubricaResolver;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\AuditsImmutability;

beforeEach(function (): void {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->withSession(['_sym_key' => str_repeat('a', 32)]);

    $this->area = Area::factory()->create();
    $this->coordinator = Especialista::factory()
        ->withRole('area_coordinator')
        ->withProfesional($this->area)
        ->create();
    $this->professional = $this->coordinator->profesional;
    $this->rubrica = Rubrica::query()
        ->where('codigo', Rubrica::CODIGO_RIESGO_PREVENTIVO)
        ->firstOrFail();

    $this->actingAs($this->coordinator);
});

function hu15Variables(): array
{
    return [
        [
            'tipo_senal' => 'ausencia_cita',
            'clave_indicador' => null,
            'modo_calculo' => 'frecuencia',
            'ventana_dias' => 30,
            'ponderacion' => '1.00',
            'umbral' => '2.00',
        ],
        [
            'tipo_senal' => 'crisis_psicologica',
            'clave_indicador' => null,
            'modo_calculo' => 'frecuencia',
            'ventana_dias' => 90,
            'ponderacion' => '1.00',
            'umbral' => '1.00',
        ],
        [
            'tipo_senal' => 'tendencia_suicida',
            'clave_indicador' => 'ideas_suicidas',
            'modo_calculo' => 'puntaje',
            'ventana_dias' => null,
            'ponderacion' => '1.00',
            'umbral' => '1.00',
        ],
    ];
}

function hu15ValidPayload(array $overrides = []): array
{
    return array_merge([
        'vigente_desde' => now()->addDays(2)->setTime(9, 0)->format('Y-m-d\TH:i:sP'),
        'justificacion_cambio' => null,
        'validada_en' => now()->subDay()->toDateString(),
        'referencia_validacion' => 'Protocolo de validación 2026',
        'variables' => hu15Variables(),
    ], $overrides);
}

/** @return array<string, string> */
function hu15InertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? '',
        'Accept' => 'application/json',
    ];
}

function hu15CreateVersion(
    Rubrica $rubrica,
    Profesional $professional,
    int $number,
    CarbonInterface $effectiveAt,
    array $overrides = [],
): RubricaVersion {
    return RubricaVersion::query()->create(array_merge([
        'rubrica_id' => $rubrica->id,
        'numero_version' => $number,
        'vigente_desde' => $effectiveAt,
        'umbral_puntaje_global' => null,
        'justificacion_cambio' => $number > 1 ? 'Ajuste basado en revisión clínica.' : null,
        'validada_en' => now()->subDay()->toDateString(),
        'referencia_validacion' => 'Protocolo de validación 2026',
        'es_demo' => false,
        'creada_por_profesional_id' => $professional->id,
    ], $overrides));
}

function hu15CreateEvaluation(
    Profesional $professional,
    Area $area,
    RubricaVersion $version,
    string $eventId,
    string $detail = 'Detalle clínico reservado de evaluación.',
): EvaluacionRiesgo {
    $patient = Paciente::factory()->create([
        'creado_por_profesional_id' => $professional->id,
    ]);

    return EvaluacionRiesgo::query()->create([
        'paciente_id' => $patient->codigo,
        'area_id' => $area->id,
        'rubrica_version_id' => $version->id,
        'disparador' => 'crisis_psicologica',
        'puntaje' => '8.75',
        'detalle' => $detail,
        'evento_origen_tipo' => 'App\\Models\\Cita',
        'evento_origen_id' => $eventId,
        'evaluada_en' => now(),
    ]);
}

/** @return array<string, string> */
function hu15WorkerEnvironment(array $overrides = []): array
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
    ], $overrides);
}

test('HU15-E01 should_persist_version_one_author_effective_date_reference_and_all_variables', function (): void {
    // Arrange
    $payload = hu15ValidPayload();

    // Act
    $response = $this->post(route('rubricas.store'), $payload);
    $version = RubricaVersion::query()->with(['variables', 'creadaPor'])->firstOrFail();

    // Assert
    expect($response->getStatusCode())->toBe(302)
        ->and(array_key_exists('umbral_puntaje_global', $payload))->toBeFalse()
        ->and($version->numero_version)->toBe(1)
        ->and($version->creada_por_profesional_id)->toBe($this->professional->id)
        ->and($version->creadaPor->id)->toBe($this->professional->id)
        ->and($version->vigente_desde->toIso8601String())
        ->toBe(Carbon::parse($payload['vigente_desde'], config('app.timezone'))->toIso8601String())
        ->and($version->validada_en->toDateString())->toBe($payload['validada_en'])
        ->and($version->referencia_validacion)->toBe($payload['referencia_validacion'])
        ->and($version->umbral_puntaje_global)->toBeNull()
        ->and($version->variables)->toHaveCount(3)
        ->and($version->variables->pluck('tipo_senal')->sort()->values()->all())
        ->toBe(collect(RubricaVariable::TIPOS_SENAL)->sort()->values()->all());
});

test('HU15-E02 should_return_422_when_a_required_signal_type_is_missing', function (): void {
    // Arrange
    $variables = hu15Variables();
    $variables[2] = [
        'tipo_senal' => 'ausencia_cita',
        'clave_indicador' => null,
        'modo_calculo' => 'acumulacion',
        'ventana_dias' => null,
        'ponderacion' => '1.00',
        'umbral' => '1.00',
    ];
    $payload = hu15ValidPayload(['variables' => $variables]);

    // Act
    $response = $this->postJson(route('rubricas.store'), $payload);

    // Assert
    expect($response->getStatusCode())->toBe(422)
        ->and($response->json('errors'))->toHaveKey('variables')
        ->and($this->rubrica->versiones()->count())->toBe(0);
});

test('HU15-E03 should_return_422_for_invalid_threshold_weight_window_indicator_and_duplicate_tuple', function (): void {
    // Arrange
    $invalidPayloads = [];

    $variables = hu15Variables();
    $variables[0]['umbral'] = '0.00';
    $invalidPayloads[] = [hu15ValidPayload(['variables' => $variables]), 'variables.0.umbral'];

    $variables = hu15Variables();
    $variables[0]['umbral'] = '-1.00';
    $invalidPayloads[] = [hu15ValidPayload(['variables' => $variables]), 'variables.0.umbral'];

    $variables = hu15Variables();
    $variables[0]['ponderacion'] = '-1.00';
    $invalidPayloads[] = [hu15ValidPayload(['variables' => $variables]), 'variables.0.ponderacion'];

    $variables = hu15Variables();
    $variables[0]['ponderacion'] = '10000.00';
    $invalidPayloads[] = [hu15ValidPayload(['variables' => $variables]), 'variables.0.ponderacion'];

    $variables = hu15Variables();
    $variables[0]['ventana_dias'] = 0;
    $invalidPayloads[] = [hu15ValidPayload(['variables' => $variables]), 'variables.0.ventana_dias'];

    $variables = hu15Variables();
    $variables[0]['ventana_dias'] = null;
    $invalidPayloads[] = [hu15ValidPayload(['variables' => $variables]), 'variables.0.ventana_dias'];

    $variables = hu15Variables();
    $variables[2]['clave_indicador'] = null;
    $invalidPayloads[] = [hu15ValidPayload(['variables' => $variables]), 'variables.2.clave_indicador'];

    $variables = hu15Variables();
    $variables[] = $variables[0];
    $invalidPayloads[] = [hu15ValidPayload(['variables' => $variables]), 'variables.3.tipo_senal'];
    $zeroWeightVariables = hu15Variables();
    $zeroWeightVariables[0]['ponderacion'] = '0.00';
    $zeroWeightPayload = hu15ValidPayload(['variables' => $zeroWeightVariables]);

    // Act
    $zeroWeightResponse = $this->postJson(route('rubricas.store'), $zeroWeightPayload);
    $validationResults = [];
    foreach ($invalidPayloads as [$payload, $errorKey]) {
        $response = $this->postJson(route('rubricas.store'), $payload);
        $validationResults[] = [$response->getStatusCode(), array_key_exists($errorKey, $response->json('errors'))];
    }
    $versionsAfterInvalidPayloads = $this->rubrica->versiones()->count();

    $firstVersionResponse = $this->post(route('rubricas.store'), hu15ValidPayload());
    $missingJustificationPayload = hu15ValidPayload([
        'vigente_desde' => now()->addDays(5)->setTime(9, 0)->format('Y-m-d\TH:i:sP'),
    ]);
    unset($missingJustificationPayload['justificacion_cambio']);
    $missingJustificationResponse = $this->postJson(route('rubricas.store'), $missingJustificationPayload);

    // Assert
    expect($zeroWeightResponse->getStatusCode())->toBe(422)
        ->and($zeroWeightResponse->json('errors'))->toHaveKey('variables.0.ponderacion')
        ->and($versionsAfterInvalidPayloads)->toBe(0)
        ->and($validationResults)->toBe([
            [422, true],
            [422, true],
            [422, true],
            [422, true],
            [422, true],
            [422, true],
            [422, true],
            [422, true],
        ])
        ->and($firstVersionResponse->getStatusCode())->toBe(302)
        ->and($missingJustificationResponse->getStatusCode())->toBe(422)
        ->and($missingJustificationResponse->json('errors'))->toHaveKey('justificacion_cambio')
        ->and($this->rubrica->versiones()->count())->toBe(1);
});

test('HU15-E04 should_create_version_two_without_changing_version_one_or_its_raw_variables', function (): void {
    // Arrange
    $this->post(route('rubricas.store'), hu15ValidPayload());
    $versionOne = $this->rubrica->versiones()->orderBy('numero_version')->firstOrFail();
    $versionOneRaw = (array) DB::table('rubrica_versiones')->where('id', $versionOne->id)->first();
    $variablesOneRaw = DB::table('rubrica_variables')
        ->where('rubrica_version_id', $versionOne->id)
        ->orderBy('id')
        ->get()
        ->map(static fn (object $row): array => (array) $row)
        ->all();

    // Act
    $secondPayload = hu15ValidPayload([
        'vigente_desde' => now()->addDays(5)->setTime(9, 0)->format('Y-m-d\TH:i:sP'),
        'justificacion_cambio' => 'Nueva versión preservando el historial.',
    ]);
    $response = $this->post(route('rubricas.store'), $secondPayload);
    $versions = $this->rubrica->versiones()->orderBy('numero_version')->get();
    $versionOneAfter = (array) DB::table('rubrica_versiones')->where('id', $versionOne->id)->first();
    $variablesOneAfter = DB::table('rubrica_variables')
        ->where('rubrica_version_id', $versionOne->id)
        ->orderBy('id')
        ->get()
        ->map(static fn (object $row): array => (array) $row)
        ->all();

    // Assert
    expect($response->getStatusCode())->toBe(302)
        ->and($versions->pluck('numero_version')->all())->toBe([1, 2])
        ->and($versionOneAfter)->toBe($versionOneRaw)
        ->and($variablesOneAfter)->toBe($variablesOneRaw);
});

test('HU15-E05 should_reject_eloquent_mutations_and_app_sql_update_delete_truncate', function (): void {
    // Arrange
    $version = hu15CreateVersion($this->rubrica, $this->professional, 1, now()->subDay());
    $variable = $version->variables()->create(hu15Variables()[0]);
    $evaluation = hu15CreateEvaluation(
        $this->professional,
        $this->area,
        $version,
        (string) Str::uuid(),
    );
    $modelsAndChanges = [
        [$this->rubrica, ['nombre' => 'Mutated rubric']],
        [$version, ['justificacion_cambio' => 'Mutated version']],
        [$variable, ['ponderacion' => '9.00']],
        [$evaluation, ['detalle' => 'Mutated risk evaluation']],
    ];

    // Act
    $eloquentMutationsRejected = [];
    foreach ($modelsAndChanges as [$model, $changes]) {
        $updateRejected = false;
        try {
            $model->forceFill($changes)->save();
        } catch (RubricaInmutableException) {
            $updateRejected = true;
        }

        $deleteRejected = false;
        try {
            $model->delete();
        } catch (RubricaInmutableException) {
            $deleteRejected = true;
        }

        $eloquentMutationsRejected[] = [$updateRejected, $deleteRejected];
    }

    $sqlStates = [];
    foreach ([
        ['UPDATE public.rubrica_versiones SET justificacion_cambio = ? WHERE id = ?', ['no', $version->id]],
        ['DELETE FROM public.rubrica_versiones WHERE id = ?', [$version->id]],
        ['TRUNCATE TABLE public.rubrica_versiones', []],
        ['UPDATE public.rubrica_variables SET ponderacion = ? WHERE id = ?', ['9.00', $variable->id]],
        ['DELETE FROM public.rubrica_variables WHERE id = ?', [$variable->id]],
        ['TRUNCATE TABLE public.rubrica_variables', []],
        ['UPDATE public.evaluaciones_riesgo SET puntaje = ? WHERE id = ?', ['9.00', $evaluation->id]],
        ['DELETE FROM public.evaluaciones_riesgo WHERE id = ?', [$evaluation->id]],
        ['TRUNCATE TABLE public.evaluaciones_riesgo', []],
    ] as [$sql, $bindings]) {
        try {
            DB::connection('pgsql')->transaction(
                static fn () => DB::connection('pgsql')->statement($sql, $bindings)
            );
            $sqlStates[] = null;
        } catch (QueryException $exception) {
            $sqlStates[] = $exception->errorInfo[0] ?? $exception->getCode();
        }
    }

    // Assert
    expect($eloquentMutationsRejected)->toBe([
        [true, true],
        [true, true],
        [true, true],
        [true, true],
    ])->and($sqlStates)->toBe(array_fill(0, 9, '42501'));
});

test('HU15-E06 should_resolve_effective_versions_and_mark_a_future_version_as_programmed', function (): void {
    // Arrange
    $now = now();
    $versionOne = hu15CreateVersion($this->rubrica, $this->professional, 1, $now->copy()->subDays(2));
    $versionTwo = hu15CreateVersion($this->rubrica, $this->professional, 2, $now->copy()->addDays(2));
    $demoVersion = hu15CreateVersion(
        $this->rubrica,
        $this->professional,
        3,
        $now->copy()->addDays(4),
        [
            'es_demo' => true,
            'validada_en' => null,
            'referencia_validacion' => null,
        ],
    );
    $resolver = app(RubricaResolver::class);

    // Act
    $beforeVersionOne = $resolver->vigente($versionOne->vigente_desde->copy()->subSecond());
    $beforeVersionTwo = $resolver->vigente($now);
    $afterVersionTwo = $resolver->vigente($versionTwo->vigente_desde->copy()->addSecond());
    $headers = hu15InertiaHeaders();
    $response = $this->withHeaders($headers)
        ->get(route('rubricas.show', $versionTwo->id));
    $page = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    $demoResponse = $this->withHeaders($headers)
        ->get(route('rubricas.show', $demoVersion->id));
    $demoPage = json_decode($demoResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

    // Assert
    expect($beforeVersionOne)->toBeNull()
        ->and($beforeVersionTwo?->id)->toBe($versionOne->id)
        ->and($afterVersionTwo?->id)->toBe($versionTwo->id)
        ->and($page['props']['version']['estado_vigencia'])->toBe('programada')
        ->and($demoPage['props']['version']['estado_vigencia'])->toBe('demo');
});

test('HU15-E07 should_return_422_when_effective_date_is_in_the_past_or_not_after_latest', function (): void {
    // Arrange
    $latest = hu15CreateVersion($this->rubrica, $this->professional, 1, now()->addDays(2));
    $pastPayload = hu15ValidPayload([
        'vigente_desde' => now()->subDay()->format('Y-m-d\TH:i:sP'),
    ]);
    $notAfterLatestPayload = hu15ValidPayload([
        'vigente_desde' => $latest->vigente_desde->copy()->subDay()->format('Y-m-d\TH:i:sP'),
    ]);

    // Act
    $pastResponse = $this->postJson(route('rubricas.store'), $pastPayload);
    $latestResponse = $this->postJson(route('rubricas.store'), $notAfterLatestPayload);

    // Assert
    expect($pastResponse->getStatusCode())->toBe(422)
        ->and($pastResponse->json('errors'))->toHaveKey('vigente_desde')
        ->and($latestResponse->getStatusCode())->toBe(422)
        ->and($latestResponse->json('errors'))->toHaveKey('vigente_desde')
        ->and($this->rubrica->versiones()->count())->toBe(1);
});

test('HU15-E08 should_keep_an_evaluation_pinned_to_version_one_after_versions_two_and_three', function (): void {
    // Arrange
    $now = now();
    $versionOne = hu15CreateVersion($this->rubrica, $this->professional, 1, $now->copy()->subDay());
    $evaluation = hu15CreateEvaluation(
        $this->professional,
        $this->area,
        $versionOne,
        (string) Str::uuid(),
        'Narrativa de riesgo que debe permanecer cifrada por área.',
    );

    // Act
    $versionTwo = hu15CreateVersion($this->rubrica, $this->professional, 2, $now->copy()->addDay());
    $versionThree = hu15CreateVersion($this->rubrica, $this->professional, 3, $now->copy()->addDays(3));
    $persistedEvaluation = $evaluation->fresh();

    // Assert
    expect($persistedEvaluation->rubrica_version_id)->toBe($versionOne->id)
        ->and($persistedEvaluation->versionRubrica->id)->toBe($versionOne->id)
        ->and($persistedEvaluation->versionRubrica->id)->not->toBe($versionTwo->id)
        ->and($persistedEvaluation->versionRubrica->id)->not->toBe($versionThree->id)
        ->and($persistedEvaluation->detalle)->toBe('Narrativa de riesgo que debe permanecer cifrada por área.');
});

test('HU15-E09 should_authorize_only_coordinators_across_areas_and_deny_other_roles_and_guests', function (): void {
    // Arrange
    $otherArea = Area::factory()->create();
    $otherAreaCoordinator = Especialista::factory()
        ->withRole('area_coordinator')
        ->withProfesional($otherArea)
        ->create();
    $specialist = Especialista::factory()->withRole('specialist')->withProfesional($this->area)->create();
    $psychosocialReferent = Especialista::factory()
        ->withRole('psychosocial_referent')
        ->withProfesional($this->area)
        ->create();
    $sysadmin = Especialista::factory()->withRole('sysadmin')->withProfesional($this->area)->create();

    // Act
    $coordinatorResponse = $this->actingAs($this->coordinator)->get(route('rubricas.index'));
    $otherAreaResponse = $this->actingAs($otherAreaCoordinator)->get(route('rubricas.create'));
    $specialistResponse = $this->actingAs($specialist)->get(route('rubricas.index'));
    $referentResponse = $this->actingAs($psychosocialReferent)->get(route('rubricas.index'));
    $sysadminResponse = $this->actingAs($sysadmin)->get(route('rubricas.index'));
    Auth::logout();
    $guestResponse = $this->get(route('rubricas.index'));

    // Assert
    expect($coordinatorResponse->getStatusCode())->toBe(200)
        ->and($otherAreaResponse->getStatusCode())->toBe(200)
        ->and($specialistResponse->getStatusCode())->toBe(403)
        ->and($referentResponse->getStatusCode())->toBe(403)
        ->and($sysadminResponse->getStatusCode())->toBe(403)
        ->and($guestResponse->getStatusCode())->toBe(302)
        ->and($guestResponse->headers->get('Location'))->toContain('/login');
});

test('HU15-E10 should_assign_sequential_numbers_under_concurrent_creation_and_reject_duplicate_dates', function (): void {
    // Arrange
    $admin = DB::connection('pgsql_admin');
    $area = Area::on('pgsql_admin')->create([
        'nombre' => 'HU15 Concurrent Area '.Str::uuid(),
        'requiere_aprobacion_estricta' => false,
    ]);
    $author = Especialista::on('pgsql_admin')->create([
        'name' => 'HU15 Concurrent Author',
        'email' => 'hu15-'.Str::uuid().'@example.test',
        'password' => 'test-password',
        'phone' => null,
        'is_active' => true,
        'kdf_salt' => base64_encode(random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES)),
        'failed_login_attempts' => 0,
        'locked_until' => null,
        'must_change_password' => false,
    ]);
    $professional = Profesional::on('pgsql_admin')->create([
        'user_id' => $author->id,
        'area_id' => $area->id,
        'especialidad' => 'Psicología clínica',
        'numero_registro' => 'HU15-'.Str::uuid(),
    ]);
    $now = now();
    $payloadOne = hu15ValidPayload([
        'vigente_desde' => $now->copy()->addDays(2)->format('Y-m-d\TH:i:sP'),
    ]);
    $payloadTwo = hu15ValidPayload([
        'vigente_desde' => $now->copy()->addDays(3)->format('Y-m-d\TH:i:sP'),
        'justificacion_cambio' => 'Segunda versión concurrente serializada.',
    ]);
    $barrier = sys_get_temp_dir().'/hu15-versioning-'.Str::uuid();
    mkdir($barrier, 0700, true);
    $holder = null;
    $writer = null;

    $workerScript = <<<'PHP'
        $base = getenv('HU15_BASE_PATH');
        require $base.'/vendor/autoload.php';
        $app = require $base.'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        $connection = Illuminate\Support\Facades\DB::connection('pgsql');
        $rubrica = App\Models\Rubrica::query()->where('codigo', App\Models\Rubrica::CODIGO_RIESGO_PREVENTIVO)->firstOrFail();
        $professional = App\Models\Profesional::query()->findOrFail(getenv('HU15_PROFESSIONAL_ID'));
        $payload = json_decode(base64_decode(getenv('HU15_PAYLOAD'), true), true, flags: JSON_THROW_ON_ERROR);

        if (getenv('HU15_MODE') === 'holder') {
            $connection->beginTransaction();
            $connection->selectOne('SELECT public.lock_rubrica_for_versioning(?)', [$rubrica->id]);
            file_put_contents(getenv('HU15_READY_FILE'), 'locked');
            $deadline = microtime(true) + 15;
            while (! is_file(getenv('HU15_RELEASE_FILE'))) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('Timed out waiting to release the version lock.');
                }
                usleep(5_000);
            }
        } else {
            file_put_contents(getenv('HU15_ATTEMPTED_FILE'), 'attempting');
        }

        $version = app(\App\Services\RubricaVersionService::class)->crearVersion($rubrica, $payload, $professional);
        if (getenv('HU15_MODE') === 'holder') {
            $connection->commit();
        }

        echo json_encode(['id' => $version->id, 'number' => $version->numero_version], JSON_THROW_ON_ERROR);
        PHP;

    $makeWorker = static function (string $mode, array $payload, string $signalFile) use (
        $workerScript,
        $professional,
        $barrier,
    ): Process {
        $signalVariables = $mode === 'holder'
            ? ['HU15_READY_FILE' => $signalFile, 'HU15_RELEASE_FILE' => $barrier.'/release']
            : ['HU15_ATTEMPTED_FILE' => $signalFile];

        $process = new Process(
            [PHP_BINARY, '-r', $workerScript],
            base_path(),
            hu15WorkerEnvironment([
                'HU15_BASE_PATH' => base_path(),
                'HU15_PROFESSIONAL_ID' => (string) $professional->id,
                'HU15_PAYLOAD' => base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)),
                'HU15_MODE' => $mode,
                ...$signalVariables,
            ]),
            timeout: 30,
        );
        $process->start();

        return $process;
    };

    try {
        // Act
        $holder = $makeWorker('holder', $payloadOne, $barrier.'/ready');
        $readyDeadline = microtime(true) + 15;
        while (! is_file($barrier.'/ready')) {
            if (! $holder->isRunning()) {
                $holder->wait();
                throw new RuntimeException('The first version worker exited before acquiring the rubric lock (exit '.$holder->getExitCode().'). '.$holder->getErrorOutput().$holder->getOutput());
            }
            if (microtime(true) > $readyDeadline) {
                throw new RuntimeException('The first version worker did not acquire the rubric lock before the deadline. '.$holder->getErrorOutput().$holder->getOutput());
            }
            usleep(5_000);
        }

        $writer = $makeWorker('writer', $payloadTwo, $barrier.'/attempted');
        $attemptDeadline = microtime(true) + 15;
        while (! is_file($barrier.'/attempted')) {
            if (! $writer->isRunning()) {
                $writer->wait();
                throw new RuntimeException('The second version worker exited before reaching the lock call (exit '.$writer->getExitCode().'). '.$writer->getErrorOutput().$writer->getOutput());
            }
            if (microtime(true) > $attemptDeadline) {
                throw new RuntimeException('The second version worker did not reach the lock call before the deadline. '.$writer->getErrorOutput().$writer->getOutput());
            }
            usleep(5_000);
        }

        usleep(200_000);
        $writerWaitedOnLock = $writer->isRunning();
        file_put_contents($barrier.'/release', 'release');
        $holder->wait();
        $writer->wait();

        // Assert
        expect($writerWaitedOnLock)->toBeTrue()
            ->and($holder->isSuccessful())->toBeTrue()
            ->and($writer->isSuccessful())->toBeTrue();

        $holderResult = json_decode(trim($holder->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        $writerResult = json_decode(trim($writer->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        $duplicateDateResponse = $this->postJson(route('rubricas.store'), array_merge($payloadTwo, [
            'justificacion_cambio' => 'Reintento de fecha ya registrada.',
        ]));
        $storedNumbers = $this->rubrica->versiones()->orderBy('numero_version')->pluck('numero_version')->all();

        expect([$holderResult['number'], $writerResult['number']])->toBe([1, 2])
            ->and($storedNumbers)->toBe([1, 2])
            ->and($duplicateDateResponse->getStatusCode())->toBe(422)
            ->and($duplicateDateResponse->json('errors'))->toHaveKey('vigente_desde');
    } finally {
        if ($holder?->isRunning()) {
            file_put_contents($barrier.'/release', 'release');
            $holder->wait();
        }
        if ($writer?->isRunning()) {
            $writer->stop(1);
        }

        AuditsImmutability::bypass(function () use ($admin, $professional, $author, $area): void {
            $committedVersionIds = $admin->table('rubrica_versiones')
                ->where('rubrica_id', $this->rubrica->id)
                ->pluck('id')
                ->all();
            $committedVariableIds = $admin->table('rubrica_variables')
                ->whereIn('rubrica_version_id', $committedVersionIds)
                ->pluck('id')
                ->all();
            $admin->table('audits')
                ->whereIn('auditable_id', [...$committedVersionIds, ...$committedVariableIds])
                ->delete();
            $admin->table('rubrica_variables')->whereIn('id', $committedVariableIds)->delete();
            $admin->table('rubrica_versiones')->whereIn('id', $committedVersionIds)->delete();
            $admin->table('profesionales')->where('id', $professional->id)->delete();
            $admin->table('users')->where('id', $author->id)->delete();
            $admin->table('areas')->where('id', $area->id)->delete();
        });

        foreach (glob($barrier.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($barrier);
    }
});

test('HU15-E11 should_expose_allowlisted_fields_and_stable_permissions_without_role_props', function (): void {
    // Arrange
    $now = now();
    $effectiveVersion = hu15CreateVersion($this->rubrica, $this->professional, 1, $now->copy()->subDay());
    foreach (hu15Variables() as $variable) {
        $effectiveVersion->variables()->create($variable);
    }
    $futureVersion = hu15CreateVersion($this->rubrica, $this->professional, 2, $now->copy()->addDay());

    // Act
    $headers = hu15InertiaHeaders();
    $showResponse = $this->withHeaders($headers)->get(route('rubricas.show', $effectiveVersion->id));
    $showPage = json_decode($showResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
    $indexResponse = $this->withHeaders($headers)->get(route('rubricas.index'));
    $indexPage = json_decode($indexResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
    $patient = Paciente::factory()->create([
        'creado_por_profesional_id' => $this->professional->id,
    ]);
    Expediente::factory()->create([
        'paciente_id' => $patient->codigo,
        'area_id' => $this->area->id,
    ]);
    $patientResponse = $this->withHeaders($headers)->get(route('pacientes.show', $patient->carnet));
    $patientPage = json_decode($patientResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
    $versionsByNumber = collect($indexPage['props']['versions']['data'])->keyBy('numero_version');
    $listedEffectiveVersion = $versionsByNumber[1];
    $listedFutureVersion = $versionsByNumber[2];

    // Assert
    expect($showResponse->getStatusCode())->toBe(200)
        ->and($showPage['component'])->toBe('Rubricas/Show')
        ->and(array_keys($showPage['props']['version']))->toBe([
            'id',
            'numero_version',
            'vigente_desde',
            'umbral_puntaje_global',
            'justificacion_cambio',
            'validada_en',
            'referencia_validacion',
            'es_demo',
            'created_at',
            'es_vigente',
            'estado_vigencia',
            'responsable_profesional',
            'variables',
        ])
        ->and(array_keys($showPage['props']['version']['variables'][0]))->toBe([
            'id',
            'tipo_senal',
            'clave_indicador',
            'modo_calculo',
            'ventana_dias',
            'ponderacion',
            'umbral',
        ])
        ->and($showPage['props']['version']['umbral_puntaje_global'])->toBeNull()
        ->and($showPage['props']['clinicalPermissions'])->toMatchArray([
            'rubricas' => true,
            'crear' => true,
            'alertas' => true,
        ])
        ->and($indexResponse->getStatusCode())->toBe(200)
        ->and($indexPage['component'])->toBe('Rubricas/Index')
        ->and($listedEffectiveVersion['id'])->toBe($effectiveVersion->id)
        ->and($listedEffectiveVersion['es_vigente'])->toBeTrue()
        ->and($listedEffectiveVersion['estado_vigencia'])->toBe('vigente')
        ->and($listedFutureVersion['id'])->toBe($futureVersion->id)
        ->and($listedFutureVersion['es_vigente'])->toBeFalse()
        ->and($listedFutureVersion['estado_vigencia'])->toBe('programada')
        ->and($indexPage['props']['clinicalPermissions'])->toMatchArray([
            'rubricas' => true,
            'crear' => true,
            'alertas' => true,
        ])
        ->and($patientResponse->getStatusCode())->toBe(200)
        ->and($patientPage['props']['can'])->not->toHaveKey('rubricas')
        ->and($patientPage['props']['clinicalPermissions'])->toMatchArray([
            'rubricas' => true,
            'crear' => true,
            'alertas' => true,
        ])
        ->and(array_key_exists('roles', $indexPage['props']))->toBeFalse()
        ->and(array_key_exists('creada_por_profesional_id', $listedEffectiveVersion))->toBeFalse()
        ->and(array_key_exists('roles', $listedEffectiveVersion))->toBeFalse();
});

test('HU15-E12 should_audit_created_user_ip_and_redact_phi_from_raw_audit_values', function (): void {
    // Arrange
    request()->server->set('REMOTE_ADDR', '203.0.113.41');
    $version = hu15CreateVersion($this->rubrica, $this->professional, 1, now()->addDay());
    $privateNarrative = 'HU15-PHI-RAW-AUDIT-CHECK-8294';

    // Act
    $versionAudit = DB::table('audits')
        ->where('auditable_type', RubricaVersion::class)
        ->where('auditable_id', $version->id)
        ->orderByDesc('id')
        ->first();
    $evaluation = hu15CreateEvaluation(
        $this->professional,
        $this->area,
        $version,
        (string) Str::uuid(),
        $privateNarrative,
    );
    $audit = DB::table('audits')
        ->where('auditable_type', EvaluacionRiesgo::class)
        ->where('auditable_id', $evaluation->id)
        ->orderByDesc('id')
        ->first();

    // Assert
    expect($versionAudit)->not->toBeNull()
        ->and($versionAudit->event)->toBe('created')
        ->and($versionAudit->user_id)->toBe($this->coordinator->id)
        ->and((string) $versionAudit->ip_address)->not->toBe('')
        ->and($audit)->not->toBeNull();
    $evaluationAuditValues = json_decode($audit->new_values, true, flags: JSON_THROW_ON_ERROR);
    $evaluationAuditRawValues = json_encode([
        'old_values' => $audit->old_values,
        'new_values' => $audit->new_values,
    ], JSON_THROW_ON_ERROR);

    expect($evaluationAuditValues['detalle'])->toBe('[CIFRADO]')
        ->and($evaluationAuditRawValues)->not->toContain($privateNarrative);
});

test('HU15-E13 should_not_register_rubric_update_delete_or_evaluation_mutation_routes', function (): void {
    // Arrange
    $mutationMethods = ['PUT', 'PATCH', 'DELETE'];

    // Act
    $mutationRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(static function ($route) use ($mutationMethods): bool {
            if (! preg_match('#^(rubricas|evaluaciones-riesgo)(?:/|$)#', $route->uri())) {
                return false;
            }

            return count(array_intersect($route->methods(), $mutationMethods)) > 0;
        })
        ->map(static fn ($route): string => $route->uri().' ['.implode(',', $route->methods()).']')
        ->values()
        ->all();

    // Assert
    expect($mutationRoutes)->toBeEmpty();
});
