<?php

declare(strict_types=1);

use App\Models\Area;
use App\Models\Carrera;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Facultad;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Rubrica;
use App\Models\RubricaVersion;
use App\Notifications\AlertaPreventivaCreada;
use App\Services\AreaEncryptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config([
        'app.area_key_secret' => str_repeat('rollback-area-secret-', 2),
        'database.default' => 'pgsql_admin',
    ]);
    $this->withSession(['_sym_key' => str_repeat('a', 32)]);

    DB::connection('pgsql_admin')->beginTransaction();
});

afterEach(function (): void {
    $connection = DB::connection('pgsql_admin');
    while ($connection->transactionLevel() > 0) {
        $connection->rollBack();
    }

    config(['database.default' => 'pgsql']);
});

test('HU16 and HU15 migrations can roll back with only neutral consultation defaults', function (): void {
    $context = hu16RollbackCreateContext();
    hu16RollbackCreateConsultation($context, 'No', []);

    hu16RollbackRunMigration('2026_10_21_130000_add_hu16_preventive_alerts.php');

    expect(Schema::connection('pgsql_admin')->hasTable('alertas_preventivas'))->toBeFalse()
        ->and(Schema::connection('pgsql_admin')->hasTable('notifications'))->toBeFalse()
        ->and(Schema::connection('pgsql_admin')->hasColumn('consultas', 'crisis_psicologica'))->toBeFalse()
        ->and(Schema::connection('pgsql_admin')->hasColumn('evaluaciones_riesgo', 'estado_evaluacion'))->toBeFalse();

    hu16RollbackRunMigration('2026_10_21_120000_create_versioned_risk_rubric_tables.php');

    foreach (['rubricas', 'rubrica_versiones', 'rubrica_variables', 'evaluaciones_riesgo'] as $table) {
        expect(Schema::connection('pgsql_admin')->hasTable($table))->toBeFalse();
    }

    expect(hu16RollbackRubricaLockFunctionExists())->toBeFalse();
});

test('HU16 rollback refuses alerts before changing schema', function (): void {
    $context = hu16RollbackCreateContext();
    $version = hu16RollbackCreateVersion($context);
    $evaluationId = hu16RollbackCreateEvaluation($context, $version, Consulta::class);
    hu16RollbackCreateAlert($context, $version, $evaluationId);

    expect(fn () => hu16RollbackRunMigration('2026_10_21_130000_add_hu16_preventive_alerts.php'))
        ->toThrow(RuntimeException::class);

    hu16RollbackExpectHu16SchemaIntact();
    expect(DB::connection('pgsql_admin')->table('alertas_preventivas')->count())->toBe(1);
});

test('HU16 rollback refuses any notifications before changing schema', function (): void {
    DB::connection('pgsql_admin')->table('notifications')->insert([
        [
            'id' => (string) Str::uuid(),
            'type' => AlertaPreventivaCreada::class,
            'notifiable_type' => Especialista::class,
            'notifiable_id' => 1,
            'data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\UnrelatedNotification',
            'notifiable_type' => Especialista::class,
            'notifiable_id' => 1,
            'data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    expect(fn () => hu16RollbackRunMigration('2026_10_21_130000_add_hu16_preventive_alerts.php'))
        ->toThrow(RuntimeException::class);

    hu16RollbackExpectHu16SchemaIntact();
    expect(DB::connection('pgsql_admin')->table('notifications')->count())->toBe(2);
});

test('HU16 rollback refuses event evaluations before changing schema', function (): void {
    $context = hu16RollbackCreateContext();
    $version = hu16RollbackCreateVersion($context);
    hu16RollbackCreateEvaluation($context, $version, Consulta::class);

    expect(fn () => hu16RollbackRunMigration('2026_10_21_130000_add_hu16_preventive_alerts.php'))
        ->toThrow(RuntimeException::class);

    hu16RollbackExpectHu16SchemaIntact();
    expect(DB::connection('pgsql_admin')->table('evaluaciones_riesgo')->count())->toBe(1);
});

test('HU16 rollback refuses omitted evaluations before changing schema', function (): void {
    $context = hu16RollbackCreateContext();
    hu16RollbackCreateEvaluation($context, null, 'Legacy\\RiskEvent', 'omitida');

    expect(fn () => hu16RollbackRunMigration('2026_10_21_130000_add_hu16_preventive_alerts.php'))
        ->toThrow(RuntimeException::class);

    hu16RollbackExpectHu16SchemaIntact();
    expect(DB::connection('pgsql_admin')->table('evaluaciones_riesgo')->count())->toBe(1);
});

test('HU16 rollback refuses non-neutral consultation signals before changing schema', function (): void {
    $context = hu16RollbackCreateContext();
    hu16RollbackCreateConsultation($context, 'Sí', ['ideas_suicidas']);

    expect(fn () => hu16RollbackRunMigration('2026_10_21_130000_add_hu16_preventive_alerts.php'))
        ->toThrow(RuntimeException::class);

    hu16RollbackExpectHu16SchemaIntact();
    expect(DB::connection('pgsql_admin')->table('consultas')->count())->toBe(1);
});

test('HU15 rollback refuses rubric and HU16 data before changing schema', function (): void {
    $context = hu16RollbackCreateContext();
    $version = hu16RollbackCreateVersion($context);
    $version->variables()->create([
        'tipo_senal' => 'crisis_psicologica',
        'clave_indicador' => null,
        'modo_calculo' => 'frecuencia',
        'ventana_dias' => 30,
        'ponderacion' => '1.00',
        'umbral' => '1.00',
    ]);
    $evaluationId = hu16RollbackCreateEvaluation($context, $version, Consulta::class);
    hu16RollbackCreateAlert($context, $version, $evaluationId);

    expect(fn () => hu16RollbackRunMigration('2026_10_21_120000_create_versioned_risk_rubric_tables.php'))
        ->toThrow(RuntimeException::class);

    foreach (['rubricas', 'rubrica_versiones', 'rubrica_variables', 'evaluaciones_riesgo', 'alertas_preventivas'] as $table) {
        expect(Schema::connection('pgsql_admin')->hasTable($table))->toBeTrue();
    }

    expect(hu16RollbackRubricaLockFunctionExists())->toBeTrue()
        ->and(DB::connection('pgsql_admin')->table('alertas_preventivas')->count())->toBe(1);
});

test('HU15 rollback refuses custom rubric data before changing schema', function (): void {
    DB::connection('pgsql_admin')->table('rubricas')->insert([
        'id' => (string) Str::uuid(),
        'codigo' => 'custom_rollback_test',
        'nombre' => 'Custom rollback test rubric',
        'descripcion' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => hu16RollbackRunMigration('2026_10_21_120000_create_versioned_risk_rubric_tables.php'))
        ->toThrow(RuntimeException::class);

    foreach (['rubricas', 'rubrica_versiones', 'rubrica_variables', 'evaluaciones_riesgo'] as $table) {
        expect(Schema::connection('pgsql_admin')->hasTable($table))->toBeTrue();
    }

    expect(DB::connection('pgsql_admin')->table('rubricas')->where('codigo', 'custom_rollback_test')->exists())->toBeTrue()
        ->and(hu16RollbackRubricaLockFunctionExists())->toBeTrue();
});

function hu16RollbackCreateContext(): array
{
    $area = Area::factory()->create();
    $specialist = Especialista::factory()
        ->withRole('area_coordinator')
        ->withProfesional($area)
        ->create();
    $professional = Profesional::query()->where('user_id', $specialist->id)->firstOrFail();
    $faculty = Facultad::factory()->create();
    $career = Carrera::factory()->create(['facultad_id' => $faculty->id]);
    $patient = Paciente::factory()->create([
        'carrera_id' => $career->id,
        'creado_por_profesional_id' => $professional->id,
    ]);
    $expediente = Expediente::factory()->create([
        'paciente_id' => $patient->codigo,
        'area_id' => $area->id,
    ]);

    return compact('area', 'professional', 'patient', 'expediente');
}

function hu16RollbackCreateVersion(array $context): RubricaVersion
{
    $rubrica = Rubrica::query()->where('codigo', Rubrica::CODIGO_RIESGO_PREVENTIVO)->firstOrFail();

    return RubricaVersion::query()->create([
        'rubrica_id' => $rubrica->id,
        'numero_version' => 1,
        'vigente_desde' => now()->subDay(),
        'umbral_puntaje_global' => null,
        'justificacion_cambio' => null,
        'validada_en' => now()->subDay()->toDateString(),
        'referencia_validacion' => 'Migration rollback test',
        'es_demo' => false,
        'creada_por_profesional_id' => $context['professional']->id,
    ]);
}

function hu16RollbackCreateEvaluation(
    array $context,
    ?RubricaVersion $version,
    string $eventType,
    string $state = 'evaluada',
): string {
    $id = (string) Str::uuid();
    $encryption = app(AreaEncryptionService::class);

    DB::connection('pgsql_admin')->table('evaluaciones_riesgo')->insert([
        'id' => $id,
        'paciente_id' => $context['patient']->codigo,
        'area_id' => $context['area']->id,
        'rubrica_version_id' => $version?->id,
        'disparador' => $state === 'omitida' ? 'sin_rubrica_vigente' : 'crisis_psicologica',
        'puntaje' => $state === 'omitida' ? null : '1.00',
        'detalle' => $state === 'omitida' ? null : $encryption->encrypt('{}', $context['area']->id),
        'estado_evaluacion' => $state,
        'evento_origen_tipo' => $eventType,
        'evento_origen_id' => (string) Str::uuid(),
        'evaluada_en' => now(),
    ]);

    return $id;
}

function hu16RollbackCreateAlert(array $context, RubricaVersion $version, string $evaluationId): void
{
    $encryption = app(AreaEncryptionService::class);

    DB::connection('pgsql_admin')->table('alertas_preventivas')->insert([
        'id' => (string) Str::uuid(),
        'paciente_id' => $context['patient']->codigo,
        'area_id' => $context['area']->id,
        'evaluacion_riesgo_id' => $evaluationId,
        'rubrica_version_id' => $version->id,
        'senal' => 'crisis_psicologica',
        'valor_calculado' => $encryption->encrypt('1', $context['area']->id),
        'umbral' => '1.00',
        'estado' => 'activa',
        'responsable_profesional_id' => $context['professional']->id,
        'generada_en' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function hu16RollbackCreateConsultation(array $context, string $crisis, array $indicators): Consulta
{
    return Consulta::query()->create([
        'expediente_id' => $context['expediente']->id,
        'profesional_id' => $context['professional']->id,
        'motivo_consulta' => 'Migration rollback test',
        'notas_clinicas' => 'Migration rollback test',
        'diagnostico' => null,
        'fecha_consulta' => now(),
        'crisis_psicologica' => $crisis,
        'indicadores_tendencia_suicida' => $indicators,
    ]);
}

function hu16RollbackRunMigration(string $migration): void
{
    $instance = require database_path('migrations/'.$migration);
    $instance->down();
}

function hu16RollbackExpectHu16SchemaIntact(): void
{
    $schema = Schema::connection('pgsql_admin');

    expect($schema->hasTable('alertas_preventivas'))->toBeTrue()
        ->and($schema->hasTable('notifications'))->toBeTrue()
        ->and($schema->hasColumn('consultas', 'crisis_psicologica'))->toBeTrue()
        ->and($schema->hasColumn('consultas', 'indicadores_tendencia_suicida'))->toBeTrue()
        ->and($schema->hasColumn('evaluaciones_riesgo', 'estado_evaluacion'))->toBeTrue();
}

function hu16RollbackRubricaLockFunctionExists(): bool
{
    return DB::connection('pgsql_admin')->table('pg_proc')
        ->where('proname', 'lock_rubrica_for_versioning')
        ->exists();
}
