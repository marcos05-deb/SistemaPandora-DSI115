<?php

declare(strict_types=1);

use App\Enums\EstadoCita;
use App\Events\CitaAusenciaRegistrada;
use App\Events\ConsultaSenalesRiesgoRegistradas;
use App\Http\Middleware\HandleInertiaRequests;
use App\Listeners\EvaluarRiesgoTrasAusencia;
use App\Listeners\EvaluarRiesgoTrasConsulta;
use App\Models\AlertaPreventiva;
use App\Models\Area;
use App\Models\Carrera;
use App\Models\Cita;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\EstadisticaPreventivaAusencia;
use App\Models\EvaluacionRiesgo;
use App\Models\Expediente;
use App\Models\Facultad;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Rubrica;
use App\Models\RubricaVersion;
use App\Services\AlertasPreventivasService;
use App\Services\EvaluadorRiesgoPreventivo;
use App\Services\RubricaResolver;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Monolog\Handler\TestHandler;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

beforeEach(function (): void {
    $this->withoutMiddleware(PreventRequestForgery::class);
    config(['app.area_key_secret' => str_repeat('area-secret-', 4)]);
    $this->withSession(['_sym_key' => base64_encode(str_repeat('s', SODIUM_CRYPTO_SECRETBOX_KEYBYTES))]);

    $this->area = Area::factory()->create();
    $this->coordinator = Especialista::factory()
        ->withRole('area_coordinator')
        ->withProfesional($this->area)
        ->create();
    $this->professional = $this->coordinator->profesional;
    $this->specialist = Especialista::factory()
        ->withRole('specialist')
        ->withProfesional($this->area)
        ->create();
    $this->specialistProfessional = $this->specialist->profesional;
    $this->actingAs($this->specialist);

    $this->rubrica = Rubrica::query()->where('codigo', Rubrica::CODIGO_RIESGO_PREVENTIVO)->firstOrFail();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** @return array<int, array<string, mixed>> */
function hu16Variables(array $overrides = []): array
{
    $variables = [
        [
            'tipo_senal' => 'crisis_psicologica',
            'clave_indicador' => null,
            'modo_calculo' => 'frecuencia',
            'ventana_dias' => 30,
            'ponderacion' => '1.00',
            'umbral' => '2.00',
        ],
        [
            'tipo_senal' => 'ausencia_cita',
            'clave_indicador' => null,
            'modo_calculo' => 'frecuencia',
            'ventana_dias' => 30,
            'ponderacion' => '1.00',
            'umbral' => '2.00',
        ],
        [
            'tipo_senal' => 'tendencia_suicida',
            'clave_indicador' => 'ideas_suicidas',
            'modo_calculo' => 'puntaje',
            'ventana_dias' => null,
            'ponderacion' => '1.00',
            'umbral' => '2.00',
        ],
    ];

    foreach ($overrides as $signal => $changes) {
        foreach ($variables as $index => $variable) {
            if ($variable['tipo_senal'] === $signal
                || ($signal === 'tendencia_suicida' && $variable['clave_indicador'] === $signal)) {
                $variables[$index] = array_merge($variable, $changes);
            }
        }
    }

    return $variables;
}

function hu16CreateVersion(
    Rubrica $rubrica,
    Profesional $professional,
    int $number = 1,
    ?Carbon $effectiveAt = null,
    array $variables = [],
    array $versionOverrides = [],
): RubricaVersion {
    $version = RubricaVersion::query()->create(array_merge([
        'rubrica_id' => $rubrica->id,
        'numero_version' => $number,
        'vigente_desde' => $effectiveAt ?? now()->subDay(),
        'umbral_puntaje_global' => null,
        'justificacion_cambio' => $number === 1 ? null : 'Actualización de umbrales para HU16.',
        'validada_en' => now()->subDay()->toDateString(),
        'referencia_validacion' => 'Protocolo de validación HU16',
        'es_demo' => false,
        'creada_por_profesional_id' => $professional->id,
    ], $versionOverrides));

    foreach ($variables ?: hu16Variables() as $variable) {
        $version->variables()->create($variable);
    }

    return $version;
}

/** @return array{Paciente, Expediente} */
function hu16CreatePatientAndExpediente(Area $area, Profesional $professional): array
{
    $facultad = Facultad::factory()->create(['nombre' => 'HU16 Faculty '.Str::uuid()]);
    $carrera = Carrera::factory()->create([
        'nombre' => 'HU16 Career '.Str::uuid(),
        'facultad_id' => $facultad->id,
    ]);
    $patient = Paciente::factory()->create([
        'carrera_id' => $carrera->id,
        'creado_por_profesional_id' => $professional->id,
    ]);
    $expediente = Expediente::factory()->create([
        'paciente_id' => $patient->codigo,
        'area_id' => $area->id,
        'estado' => Expediente::ESTADO_ABIERTO,
        'derivado_por_profesional_id' => $professional->id,
        'fecha_derivacion' => now()->subDays(10),
    ]);

    return [$patient, $expediente];
}

function hu16RegisterConsultation(
    TestCase $test,
    Expediente $expediente,
    array $signals = [],
    bool $evaluate = true,
): Consulta {
    $payload = array_merge([
        'motivo_consulta' => 'Seguimiento preventivo de prueba.',
        'notas_clinicas' => 'Observación clínica reservada HU16.',
        'diagnostico' => null,
        'plan_atencion' => 'Continuar con el seguimiento clínico acordado.',
        'tecnica_utilizada' => 'Entrevista clínica',
        'fecha_consulta' => now()->toDateString(),
        'evaluacion_inicial' => [
            'apariencia_externa' => 'Apariencia de prueba',
            'voz' => 'Voz de prueba',
            'patrones_habla' => 'Habla de prueba',
            'expresiones_faciales' => 'Expresión de prueba',
            'ademanes' => 'Ademanes de prueba',
            'actitudes_tratamiento' => 'Colaborador',
            'impresion' => 'Impresión de prueba',
            'plan_tratamiento' => 'Plan de prueba',
            'pronostico' => 'Pronóstico de prueba',
        ],
        'crisis_psicologica' => false,
        'indicadores_tendencia_suicida' => [],
    ], $signals);

    $existingIds = Consulta::query()
        ->where('expediente_id', $expediente->id)
        ->pluck('id');
    $test->post(route('consultas.store', $expediente->id), $payload);

    $consulta = Consulta::query()
        ->where('expediente_id', $expediente->id)
        ->whereNotIn('id', $existingIds)
        ->firstOrFail();

    if ($evaluate) {
        app(EvaluadorRiesgoPreventivo::class)->evaluarConsulta($consulta);
    }

    return $consulta;
}

function hu16RegisterAbsence(
    TestCase $test,
    Expediente $expediente,
    Profesional $professional,
    ?Carbon $date = null,
    bool $evaluate = true,
): Cita {
    $cita = Cita::query()->create([
        'expediente_id' => $expediente->id,
        'profesional_id' => $professional->id,
        'area_id' => $expediente->area_id,
        'fecha_hora' => ($date ?? now())->copy()->subHour(),
        'motivo' => 'Cita de seguimiento HU16',
        'estado' => EstadoCita::Programada->value,
    ]);

    $test->patch(route('citas.asistencia', [$expediente->id, $cita->id]), [
        'estado' => EstadoCita::Ausente->value,
    ]);

    $cita = $cita->fresh();

    if ($evaluate) {
        app(EvaluadorRiesgoPreventivo::class)->evaluarAusencia($cita);
    }

    return $cita;
}

function hu16CreateHistoricalAbsence(Expediente $expediente, Profesional $professional, Carbon $date): Cita
{
    $cita = Cita::query()->create([
        'expediente_id' => $expediente->id,
        'profesional_id' => $professional->id,
        'area_id' => $expediente->area_id,
        'fecha_hora' => $date,
        'motivo' => 'Ausencia histórica de prueba HU16',
        'estado' => EstadoCita::Ausente->value,
        'fecha_registro_asistencia' => $date,
        'registrado_por_profesional_id' => $professional->id,
    ]);

    EstadisticaPreventivaAusencia::query()->create([
        'cita_id' => $cita->id,
        'paciente_id' => $expediente->paciente_id,
        'profesional_id' => $professional->id,
        'area_id' => $expediente->area_id,
        'fecha_registro' => $date,
    ]);

    return $cita;
}

test('HU16-E01 should_persist_risk_evaluation_when_consultation_records_a_crisis', function (): void {
    // Arrange
    Event::fake([ConsultaSenalesRiesgoRegistradas::class]);
    hu16CreateVersion($this->rubrica, $this->professional);
    [, $expediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);

    // Act
    $consulta = hu16RegisterConsultation($this, $expediente, ['crisis_psicologica' => true]);

    // Assert
    expect(Event::dispatched(ConsultaSenalesRiesgoRegistradas::class))->toHaveCount(1)
        ->and(EvaluacionRiesgo::query()
            ->where('evento_origen_id', $consulta->id)
            ->where('disparador', 'crisis_psicologica')
            ->count())->toBe(1);
});

test('HU16-E02 should_persist_risk_evaluation_when_absence_event_is_recorded', function (): void {
    // Arrange
    Event::fake([CitaAusenciaRegistrada::class]);
    hu16CreateVersion($this->rubrica, $this->professional);
    [$patient, $expediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);

    // Act
    $cita = hu16RegisterAbsence($this, $expediente, $this->specialistProfessional);

    // Assert
    expect(Event::dispatched(CitaAusenciaRegistrada::class))->toHaveCount(1)
        ->and(EvaluacionRiesgo::query()
            ->where('paciente_id', $patient->codigo)
            ->where('evento_origen_id', $cita->id)
            ->where('disparador', 'ausencia_cita')
            ->count())->toBe(1);
});

test('HU16-E03 should_evaluate_only_rubric_included_suicidal_indicators', function (): void {
    // Arrange
    hu16CreateVersion($this->rubrica, $this->professional);
    [, $includedExpediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);
    [, $excludedExpediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);

    // Act
    $included = hu16RegisterConsultation($this, $includedExpediente, [
        'indicadores_tendencia_suicida' => ['ideas_suicidas'],
    ]);
    $excluded = hu16RegisterConsultation($this, $excludedExpediente, [
        'indicadores_tendencia_suicida' => ['indicador_no_incluido'],
    ]);

    // Assert
    expect(EvaluacionRiesgo::query()->where('evento_origen_id', $included->id)->where('disparador', 'tendencia_suicida')->count())->toBe(1)
        ->and(EvaluacionRiesgo::query()->where('evento_origen_id', $excluded->id)->where('disparador', 'tendencia_suicida')->count())->toBe(0);
});

test('HU16-E04 should_apply_below_equal_and_above_thresholds_for_each_signal', function (): void {
    // Arrange
    hu16CreateVersion($this->rubrica, $this->professional, variables: hu16Variables([
        'crisis_psicologica' => ['umbral' => '2.00'],
        'ausencia_cita' => ['umbral' => '2.00'],
        'tendencia_suicida' => ['umbral' => '2.00'],
    ]));
    $outcomes = [];

    // Act
    foreach (['crisis_psicologica', 'ausencia_cita', 'tendencia_suicida'] as $signal) {
        foreach ([1 => false, 2 => true, 3 => true] as $occurrences => $shouldAlert) {
            [$patient, $expediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);
            $source = null;
            for ($index = 0; $index < $occurrences; $index++) {
                $source = match ($signal) {
                    'crisis_psicologica' => hu16RegisterConsultation($this, $expediente, ['crisis_psicologica' => true]),
                    'ausencia_cita' => hu16RegisterAbsence(
                        $this,
                        $expediente,
                        $this->specialistProfessional,
                        now()->subDays($index + 1),
                    ),
                    default => hu16RegisterConsultation($this, $expediente, [
                        'indicadores_tendencia_suicida' => ['ideas_suicidas'],
                    ]),
                };
            }
            $outcomes[] = [
                $signal,
                $occurrences,
                AlertaPreventiva::query()
                    ->where('paciente_id', $patient->codigo)
                    ->where('area_id', $this->area->id)
                    ->where('senal', $signal)
                    ->exists(),
                $shouldAlert,
                $source !== null,
            ];
        }
    }

    // Assert
    expect($outcomes)->toBe([
        ['crisis_psicologica', 1, false, false, true],
        ['crisis_psicologica', 2, true, true, true],
        ['crisis_psicologica', 3, true, true, true],
        ['ausencia_cita', 1, false, false, true],
        ['ausencia_cita', 2, true, true, true],
        ['ausencia_cita', 3, true, true, true],
        ['tendencia_suicida', 1, false, false, true],
        ['tendencia_suicida', 2, true, true, true],
        ['tendencia_suicida', 3, true, true, true],
    ]);
});

test('HU16-E05 should_use_accumulation_since_referral_and_weighted_indicator_score', function (): void {
    // Arrange
    $variables = hu16Variables([
        'ausencia_cita' => ['modo_calculo' => 'acumulacion', 'ventana_dias' => null, 'umbral' => '3.00'],
        'tendencia_suicida' => ['ponderacion' => '2.00', 'umbral' => '2.00'],
    ]);
    hu16CreateVersion($this->rubrica, $this->professional, variables: $variables);
    [$accumulationPatient, $accumulationExpediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);
    $beforeReferral = hu16CreateHistoricalAbsence(
        $accumulationExpediente,
        $this->specialistProfessional,
        now()->subDays(20),
    );
    $accumulationExpediente->update(['fecha_derivacion' => now()->subDays(10)]);
    [$scorePatient, $scoreExpediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);

    // Act
    hu16RegisterAbsence($this, $accumulationExpediente, $this->specialistProfessional, now()->subDays(2));
    $alertCountAfterOnePostReferralAbsence = AlertaPreventiva::query()
        ->where('paciente_id', $accumulationPatient->codigo)
        ->count();
    hu16RegisterAbsence($this, $accumulationExpediente, $this->specialistProfessional, now()->subDay());
    $alertCountAfterTwoPostReferralAbsences = AlertaPreventiva::query()
        ->where('paciente_id', $accumulationPatient->codigo)
        ->count();
    hu16RegisterAbsence($this, $accumulationExpediente, $this->specialistProfessional);
    $scoreConsultation = hu16RegisterConsultation($this, $scoreExpediente, [
        'indicadores_tendencia_suicida' => ['ideas_suicidas'],
    ]);
    $scoreAlert = AlertaPreventiva::query()->where('paciente_id', $scorePatient->codigo)->first();

    // Assert
    expect($beforeReferral->estado)->toBe(EstadoCita::Ausente->value)
        ->and($alertCountAfterOnePostReferralAbsence)->toBe(0)
        ->and($alertCountAfterTwoPostReferralAbsences)->toBe(0)
        ->and(AlertaPreventiva::query()->where('paciente_id', $accumulationPatient->codigo)->exists())->toBeTrue()
        ->and((float) $scoreAlert?->valor_calculado)->toBe(2.0)
        ->and(EvaluacionRiesgo::query()->where('evento_origen_id', $scoreConsultation->id)->exists())->toBeTrue();
});

test('HU16 summary should_use_the_explicit_expediente_area', function (): void {
    // Arrange
    hu16CreateVersion($this->rubrica, $this->professional, variables: hu16Variables([
        'ausencia_cita' => ['umbral' => '2.00'],
    ]));
    [$patient, $firstExpediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);
    $secondArea = Area::factory()->create();
    $secondProfessional = Profesional::factory()->create([
        'user_id' => $this->specialist->id,
        'area_id' => $secondArea->id,
    ]);
    $secondExpediente = Expediente::factory()->create([
        'paciente_id' => $patient->codigo,
        'area_id' => $secondArea->id,
        'estado' => Expediente::ESTADO_ABIERTO,
        'derivado_por_profesional_id' => $secondProfessional->id,
        'fecha_derivacion' => now()->subDays(10),
    ]);
    DB::table('expedientes')->where('id', $firstExpediente->id)->update(['updated_at' => now()->subMinute()]);
    DB::table('expedientes')->where('id', $secondExpediente->id)->update(['updated_at' => now()]);
    hu16CreateHistoricalAbsence($firstExpediente, $this->specialistProfessional, now()->subDays(2));
    hu16CreateHistoricalAbsence($firstExpediente, $this->specialistProfessional, now()->subDay());
    hu16CreateHistoricalAbsence($secondExpediente, $secondProfessional, now()->subDay());

    // Act
    $summary = app(AlertasPreventivasService::class)->alertaPaciente(
        $this->specialist,
        $patient->codigo,
        areaId: $this->area->id,
    );

    // Assert
    expect($summary['activa'])->toBeTrue()
        ->and($summary['total'])->toBe(2)
        ->and($summary['umbral'])->toBe(2);
});

test('HU16 summary should_match_accumulation_since_referral', function (): void {
    // Arrange
    hu16CreateVersion($this->rubrica, $this->professional, variables: hu16Variables([
        'ausencia_cita' => ['modo_calculo' => 'acumulacion', 'ventana_dias' => null, 'umbral' => '2.00'],
    ]));
    [$patient, $expediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);
    $expediente->update(['fecha_derivacion' => now()->subDays(7)]);
    hu16CreateHistoricalAbsence($expediente, $this->specialistProfessional, now()->subDays(14));
    $firstPostReferral = hu16CreateHistoricalAbsence($expediente, $this->specialistProfessional, now()->subDays(5));
    $secondPostReferral = hu16CreateHistoricalAbsence($expediente, $this->specialistProfessional, now()->subDay());

    // Act
    app(EvaluadorRiesgoPreventivo::class)->evaluarAusencia($firstPostReferral);
    app(EvaluadorRiesgoPreventivo::class)->evaluarAusencia($secondPostReferral);
    $summary = app(AlertasPreventivasService::class)->alertaPaciente(
        $this->specialist,
        $patient->codigo,
        areaId: $this->area->id,
    );
    $alert = AlertaPreventiva::query()->where('paciente_id', $patient->codigo)->firstOrFail();

    // Assert
    expect($summary['activa'])->toBeTrue()
        ->and($summary['total'])->toBe(2)
        ->and($summary['ventana_dias'])->toBeNull()
        ->and($summary['modo_calculo'])->toBe('acumulacion')
        ->and((float) $alert->valor_calculado)->toBe((float) $summary['total']);
});

test('HU16 summary should_match_weighted_absence_score', function (): void {
    // Arrange
    hu16CreateVersion($this->rubrica, $this->professional, variables: hu16Variables([
        'ausencia_cita' => [
            'modo_calculo' => 'puntaje',
            'ventana_dias' => null,
            'ponderacion' => '2.00',
            'umbral' => '4.00',
        ],
    ]));
    [$patient, $expediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);
    $expediente->update(['fecha_derivacion' => now()->subDays(2)]);
    $firstAbsence = hu16CreateHistoricalAbsence($expediente, $this->specialistProfessional, now()->subDays(20));
    $secondAbsence = hu16CreateHistoricalAbsence($expediente, $this->specialistProfessional, now()->subDays(10));

    // Act
    app(EvaluadorRiesgoPreventivo::class)->evaluarAusencia($firstAbsence);
    app(EvaluadorRiesgoPreventivo::class)->evaluarAusencia($secondAbsence);
    $summary = app(AlertasPreventivasService::class)->alertaPaciente(
        $this->specialist,
        $patient->codigo,
        areaId: $this->area->id,
    );
    $alert = AlertaPreventiva::query()->where('paciente_id', $patient->codigo)->firstOrFail();

    // Assert
    expect($summary['activa'])->toBeTrue()
        ->and($summary['total'])->toBe(4.0)
        ->and($summary['umbral'])->toBe(4)
        ->and($summary['ventana_dias'])->toBeNull()
        ->and($summary['modo_calculo'])->toBe('puntaje')
        ->and((float) $alert->valor_calculado)->toBe($summary['total']);
});

test('HU16 summary should_select_the_same_triggering_absence_rule_as_evaluation', function (): void {
    // Arrange
    $version = hu16CreateVersion($this->rubrica, $this->professional, variables: hu16Variables([
        'ausencia_cita' => ['umbral' => '1.00'],
    ]));
    $version->variables()->create([
        'tipo_senal' => 'ausencia_cita',
        'clave_indicador' => null,
        'modo_calculo' => 'acumulacion',
        'ventana_dias' => null,
        'ponderacion' => '1.00',
        'umbral' => '0.50',
    ]);
    [$patient, $expediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);
    $expediente->update(['fecha_derivacion' => now()->subDays(5)]);

    // Act
    hu16RegisterAbsence($this, $expediente, $this->specialistProfessional, now()->subDay());
    $summary = app(AlertasPreventivasService::class)->alertaPaciente(
        $this->specialist,
        $patient->codigo,
        areaId: $this->area->id,
    );
    $alert = AlertaPreventiva::query()->where('paciente_id', $patient->codigo)->firstOrFail();

    // Assert
    expect($summary['activa'])->toBeTrue()
        ->and($summary['total'])->toBe(1)
        ->and($summary['umbral'])->toBe(1)
        ->and($summary['modo_calculo'])->toBe('frecuencia')
        ->and((float) $alert->valor_calculado)->toBe((float) $summary['total'])
        ->and((float) $alert->umbral)->toBe((float) $summary['umbral']);
});

test('HU16-E06 should_pin_threshold_and_version_from_the_version_effective_at_evaluation', function (): void {
    // Arrange
    $now = now();
    $versionOne = hu16CreateVersion($this->rubrica, $this->professional, 1, $now->copy()->subDays(2), hu16Variables([
        'crisis_psicologica' => ['umbral' => '1.00'],
    ]));
    [$patient, $expediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);

    // Act
    $firstConsultation = hu16RegisterConsultation($this, $expediente, ['crisis_psicologica' => true]);
    $versionTwo = hu16CreateVersion($this->rubrica, $this->professional, 2, $now->copy()->addDay(), hu16Variables([
        'crisis_psicologica' => ['umbral' => '5.00'],
    ]));
    Carbon::setTestNow($now->copy()->addDays(2));
    $secondConsultation = hu16RegisterConsultation($this, $expediente, ['crisis_psicologica' => true]);

    // Assert
    expect(AlertaPreventiva::query()
        ->where('paciente_id', $patient->codigo)
        ->where('evaluacion_riesgo_id', EvaluacionRiesgo::query()->where('evento_origen_id', $firstConsultation->id)->value('id'))
        ->value('rubrica_version_id'))->toBe($versionOne->id)
        ->and(AlertaPreventiva::query()->where('paciente_id', $patient->codigo)->where('senal', 'crisis_psicologica')->count())->toBe(1)
        ->and(EvaluacionRiesgo::query()->where('evento_origen_id', $secondConsultation->id)->value('rubrica_version_id'))->toBe($versionTwo->id);
});

test('HU16-E07 should_omit_evaluation_without_a_current_rubric_version', function (): void {
    // Arrange
    [$patient, $expediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);

    // Act
    $consulta = hu16RegisterConsultation($this, $expediente, ['crisis_psicologica' => true]);
    $summary = app(AlertasPreventivasService::class)->alertaPaciente(
        $this->specialist,
        $patient->codigo,
        areaId: $this->area->id,
    );

    // Assert
    expect(AlertaPreventiva::query()->where('paciente_id', $patient->codigo)->count())->toBe(0)
        ->and(EvaluacionRiesgo::query()->where('evento_origen_id', $consulta->id)->where('estado_evaluacion', 'omitida')->count())->toBe(1)
        ->and($summary['umbral'])->toBeNull()
        ->and($summary['ventana_dias'])->toBeNull();
});

test('HU16-E08 should_persist_alert_contract_fields_with_area_encrypted_value', function (): void {
    // Arrange
    hu16CreateVersion($this->rubrica, $this->professional, variables: hu16Variables([
        'crisis_psicologica' => ['umbral' => '1.00'],
    ]));
    [$patient, $expediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);

    // Act
    hu16RegisterConsultation($this, $expediente, ['crisis_psicologica' => true]);
    $alert = AlertaPreventiva::query()->where('paciente_id', $patient->codigo)->firstOrFail();
    $rawValue = DB::table('alertas_preventivas')->where('id', $alert->id)->value('valor_calculado');

    // Assert
    expect($alert->paciente_id)->toBe($patient->codigo)
        ->and($alert->senal)->toBe('crisis_psicologica')
        ->and((float) $alert->valor_calculado)->toBe(1.0)
        ->and((float) $alert->umbral)->toBe(1.0)
        ->and($alert->rubrica_version_id)->not->toBeNull()
        ->and($alert->generada_en)->not->toBeNull()
        ->and($rawValue)->not->toBe('1.00')
        ->and(base64_decode((string) $rawValue, true))->not->toBeFalse();
});

test('HU16-E09 should_notify_only_same_area_clinical_recipients_without_phi', function (): void {
    // Arrange
    hu16CreateVersion($this->rubrica, $this->professional, variables: hu16Variables([
        'crisis_psicologica' => ['umbral' => '1.00'],
    ]));
    $otherArea = Area::factory()->create();
    $otherCoordinator = Especialista::factory()->withRole('area_coordinator')->withProfesional($otherArea)->create();
    $otherSpecialist = Especialista::factory()->withRole('specialist')->withProfesional($otherArea)->create();
    $referent = Especialista::factory()->withRole('psychosocial_referent')->withProfesional($this->area)->create();
    $sysadmin = Especialista::factory()->withRole('sysadmin')->withProfesional($this->area)->create();
    [, $expediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);

    // Act
    hu16RegisterConsultation($this, $expediente, ['crisis_psicologica' => true]);
    $recipientIds = DB::table('notifications')->pluck('notifiable_id')->map(static fn ($id): string => (string) $id)->all();
    $payloads = DB::table('notifications')->pluck('data')->map(static function (mixed $payload): array {
        return is_array($payload) ? $payload : json_decode((string) $payload, true, flags: JSON_THROW_ON_ERROR);
    })->all();
    $serializedPayloads = json_encode($payloads, JSON_THROW_ON_ERROR);

    // Assert
    expect($recipientIds)->toContain((string) $this->coordinator->id, (string) $this->specialist->id)
        ->not->toContain((string) $otherCoordinator->id, (string) $otherSpecialist->id, (string) $referent->id, (string) $sysadmin->id)
        ->and($serializedPayloads)->not->toContain('Observación clínica reservada HU16')
        ->and($serializedPayloads)->not->toContain($expediente->paciente->carnet)
        ->and($payloads[0])->toHaveKeys(['alert_id', 'senal', 'label']);
});

test('HU16-E10 should_deduplicate_reprocessing_and_allow_reoccurrence_after_close', function (): void {
    // Arrange
    hu16CreateVersion($this->rubrica, $this->professional, variables: hu16Variables([
        'crisis_psicologica' => ['umbral' => '1.00'],
    ]));
    [$patient, $expediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);

    // Act
    $firstConsultation = hu16RegisterConsultation($this, $expediente, ['crisis_psicologica' => true]);
    Artisan::call('alertas:reevaluar', ['--paciente' => $patient->codigo]);
    hu16RegisterConsultation($this, $expediente, ['crisis_psicologica' => true]);
    $firstAlert = AlertaPreventiva::query()->where('paciente_id', $patient->codigo)->firstOrFail();
    DB::table('alertas_preventivas')->where('id', $firstAlert->id)->update(['estado' => 'cerrada']);
    hu16RegisterConsultation($this, $expediente, ['crisis_psicologica' => true]);

    // Assert
    expect(EvaluacionRiesgo::query()->where('evento_origen_id', $firstConsultation->id)->count())->toBe(1)
        ->and(AlertaPreventiva::query()->where('paciente_id', $patient->codigo)->where('estado', 'activa')->count())->toBe(1)
        ->and(AlertaPreventiva::query()->where('paciente_id', $patient->codigo)->where('estado', 'cerrada')->count())->toBe(1);
});

test('HU16-E11 should_isolate_signal_accumulation_between_patient_areas', function (): void {
    // Arrange
    hu16CreateVersion($this->rubrica, $this->professional, variables: hu16Variables([
        'ausencia_cita' => ['umbral' => '2.00'],
    ]));
    $secondArea = Area::factory()->create();
    $secondSpecialist = Especialista::factory()->withRole('specialist')->withProfesional($secondArea)->create();
    $secondProfessional = $secondSpecialist->profesional;
    $patient = Paciente::factory()->create(['creado_por_profesional_id' => $this->professional->id]);
    $firstExpediente = Expediente::factory()->create([
        'paciente_id' => $patient->codigo,
        'area_id' => $this->area->id,
        'estado' => Expediente::ESTADO_ABIERTO,
        'derivado_por_profesional_id' => $this->professional->id,
        'fecha_derivacion' => now()->subDays(10),
    ]);
    $secondExpediente = Expediente::factory()->create([
        'paciente_id' => $patient->codigo,
        'area_id' => $secondArea->id,
        'estado' => Expediente::ESTADO_ABIERTO,
        'derivado_por_profesional_id' => $secondProfessional->id,
        'fecha_derivacion' => now()->subDays(10),
    ]);

    // Act
    hu16RegisterAbsence($this, $firstExpediente, $this->professional, now()->subDays(2));
    hu16RegisterAbsence($this, $firstExpediente, $this->professional, now()->subDay());
    $this->actingAs($secondSpecialist);
    hu16RegisterAbsence($this, $secondExpediente, $secondProfessional, now()->subDay());
    $this->actingAs($this->specialist);

    // Assert
    expect(AlertaPreventiva::query()->where('paciente_id', $patient->codigo)->where('area_id', $this->area->id)->count())->toBe(1)
        ->and(AlertaPreventiva::query()->where('paciente_id', $patient->codigo)->where('area_id', $secondArea->id)->count())->toBe(0);
});

test('HU16-E12 should_authorize_alert_inbox_by_area_and_expose_only_allowlisted_fields', function (): void {
    // Arrange
    hu16CreateVersion($this->rubrica, $this->professional, variables: hu16Variables([
        'crisis_psicologica' => ['umbral' => '1.00'],
    ]));
    [, $expediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);
    hu16RegisterConsultation($this, $expediente, ['crisis_psicologica' => true]);
    $otherArea = Area::factory()->create();
    $otherCoordinator = Especialista::factory()->withRole('area_coordinator')->withProfesional($otherArea)->create();
    $referent = Especialista::factory()->withRole('psychosocial_referent')->withProfesional($this->area)->create();

    // Act
    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? '',
        'Accept' => 'application/json',
    ];
    $coordinatorResponse = $this->actingAs($this->coordinator)->withHeaders($headers)->get('/alertas');
    $otherAreaResponse = $this->actingAs($otherCoordinator)->withHeaders($headers)->get('/alertas');
    $referentResponse = $this->actingAs($referent)->withHeaders($headers)->get('/alertas');
    expect($coordinatorResponse->getStatusCode())->toBe(200);
    $page = json_decode($coordinatorResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
    $serializedProps = json_encode($page['props'], JSON_THROW_ON_ERROR);

    // Assert
    expect($coordinatorResponse->getStatusCode())->toBe(200)
        ->and($otherAreaResponse->getStatusCode())->toBeIn([200, 404])
        ->and($referentResponse->getStatusCode())->toBe(403)
        ->and($serializedProps)->not->toContain('detalle')
        ->and($serializedProps)->not->toContain('evento_origen_id')
        ->and($serializedProps)->not->toContain('evaluacion_riesgo_id');
});

test('HU16-E13 should_preserve_consultation_and_appointment_when_evaluation_fails_and_retry', function (): void {
    // Arrange
    hu16CreateVersion($this->rubrica, $this->professional, variables: hu16Variables([
        'crisis_psicologica' => ['umbral' => '1.00'],
        'ausencia_cita' => ['umbral' => '1.00'],
    ]));
    [$patient, $expediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);
    $failingResolver = Mockery::mock(RubricaResolver::class);
    $failingResolver->shouldReceive('vigente')->andThrow(new RuntimeException('fallo con PHI privada de prueba'));
    app()->instance(RubricaResolver::class, $failingResolver);
    $logHandler = new TestHandler;
    Log::channel()->getLogger()->pushHandler($logHandler);

    // Act
    $consulta = hu16RegisterConsultation(
        $this,
        $expediente,
        ['crisis_psicologica' => true],
        evaluate: false,
    );
    $cita = hu16RegisterAbsence($this, $expediente, $this->specialistProfessional, evaluate: false);
    app(EvaluarRiesgoTrasConsulta::class)->handle(new ConsultaSenalesRiesgoRegistradas($consulta));
    app(EvaluarRiesgoTrasAusencia::class)->handle(new CitaAusenciaRegistrada($cita));
    app()->instance(RubricaResolver::class, new RubricaResolver);
    $commandStatus = Artisan::call('alertas:reevaluar', ['--paciente' => $patient->codigo]);
    $reportedMessages = collect($logHandler->getRecords())->map(static fn ($record): string => (string) $record->message);

    // Assert
    expect(Consulta::query()->whereKey($consulta->id)->exists())->toBeTrue()
        ->and(Cita::query()->whereKey($cita->id)->where('estado', EstadoCita::Ausente->value)->exists())->toBeTrue()
        ->and($commandStatus)->toBe(0)
        ->and(AlertaPreventiva::query()->where('paciente_id', $patient->codigo)->count())->toBeGreaterThan(0)
        ->and($reportedMessages->implode(' '))->not->toContain('fallo con PHI privada de prueba');
});

test('HU16-E13 regression should_retry_an_older_positive_consultation_after_a_latest_neutral_consultation', function (): void {
    // Arrange
    Event::fake([ConsultaSenalesRiesgoRegistradas::class]);
    hu16CreateVersion($this->rubrica, $this->professional, variables: hu16Variables([
        'crisis_psicologica' => ['umbral' => '1.00'],
    ]));
    [$patient, $expediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);
    $positiveConsultation = hu16RegisterConsultation($this, $expediente, [
        'crisis_psicologica' => true,
        'fecha_consulta' => now()->subDays(2)->toDateString(),
    ], evaluate: false);
    $neutralConsultation = hu16RegisterConsultation($this, $expediente, [
        'fecha_consulta' => now()->subDay()->toDateString(),
    ], evaluate: false);

    // Act
    $attemptedEvents = app(EvaluadorRiesgoPreventivo::class)->reevaluar($patient->codigo);

    // Assert
    expect($attemptedEvents)->toBe(1)
        ->and(EvaluacionRiesgo::query()->where('evento_origen_id', $positiveConsultation->id)->count())->toBe(1)
        ->and(EvaluacionRiesgo::query()->where('evento_origen_id', $neutralConsultation->id)->count())->toBe(0)
        ->and(AlertaPreventiva::query()
            ->where('paciente_id', $patient->codigo)
            ->where('area_id', $this->area->id)
            ->where('senal', 'crisis_psicologica')
            ->exists())->toBeTrue();
});

test('HU16-E14 should_audit_alert_creation_and_inbox_read_without_plaintext_phi', function (): void {
    // Arrange
    hu16CreateVersion($this->rubrica, $this->professional, variables: hu16Variables([
        'crisis_psicologica' => ['umbral' => '1.00'],
    ]));
    [, $expediente] = hu16CreatePatientAndExpediente($this->area, $this->specialistProfessional);

    // Act
    hu16RegisterConsultation($this, $expediente, ['crisis_psicologica' => true]);
    $alert = AlertaPreventiva::query()->firstOrFail();
    $this->actingAs($this->coordinator)
        ->withHeaders(['X-Inertia' => 'true', 'Accept' => 'application/json'])
        ->get('/alertas');
    $audits = Audit::query()->where('auditable_type', AlertaPreventiva::class)
        ->where('auditable_id', $alert->id)
        ->get();
    $readAudit = Audit::query()
        ->where('user_id', $this->coordinator->id)
        ->where('event', 'accessed')
        ->where('tags', 'clinical_access')
        ->get()
        ->first(fn (Audit $audit): bool => ($audit->new_values['action'] ?? null) === 'read_alert_inbox');
    $auditText = $audits->toJson().' '.($readAudit?->toJson() ?? '');

    // Assert
    expect($audits->pluck('event')->all())->toContain('created')
        ->and($audits->pluck('user_id')->filter()->all())->toContain($this->specialist->id)
        ->and($readAudit)->not->toBeNull()
        ->and($auditText)->not->toContain('Observación clínica reservada HU16')
        ->and($auditText)->not->toContain($expediente->paciente->carnet);
});
