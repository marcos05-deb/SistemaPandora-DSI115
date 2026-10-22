<?php

declare(strict_types=1);

use App\Models\Area;
use App\Models\Carrera;
use App\Models\Especialista;
use App\Models\EvaluacionRiesgo;
use App\Models\Facultad;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Rubrica;
use App\Models\RubricaVersion;
use App\Services\AreaEncryptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config([
        'app.area_key_secret' => str_repeat('hu17-migration-key-', 2),
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

test('HU17 migration backfills documented encrypted outcomes for old demo terminal alerts', function (): void {
    // Arrange
    $migration = hu17MigrationInstance();
    $migration->down();
    $alert = hu17MigrationCreateLegacyTerminalAlert(true, 'cerrada');

    // Act
    $migration->up();
    $rawAlert = DB::connection('pgsql_admin')->table('alertas_preventivas')->where('id', $alert['id'])->first();
    $decryptedResult = app(AreaEncryptionService::class)->decrypt($rawAlert->resultado_cierre, $alert['area_id']);
    $rollbackBlocked = false;
    try {
        $migration->down();
    } catch (RuntimeException) {
        $rollbackBlocked = true;
    }

    // Assert
    expect(Schema::connection('pgsql_admin')->hasTable('seguimientos_preventivos'))->toBeTrue()
        ->and(Schema::connection('pgsql_admin')->hasColumn('alertas_preventivas', 'resultado_cierre'))->toBeTrue()
        ->and($decryptedResult)->toContain('DEMO:')
        ->and($rawAlert->cerrada_por_profesional_id)->toBe($alert['professional_id'])
        ->and($rollbackBlocked)->toBeTrue()
        ->and(DB::connection('pgsql_admin')->table('alertas_preventivas')->where('id', $alert['id'])->value('estado'))->toBe('cerrada');
});

test('HU17 migration refuses undocumented real terminal alerts before changing schema', function (): void {
    // Arrange
    $migration = hu17MigrationInstance();
    $migration->down();
    hu17MigrationCreateLegacyTerminalAlert(false, 'descartada');

    // Act
    $migrationRejected = false;
    try {
        $migration->up();
    } catch (RuntimeException) {
        $migrationRejected = true;
    }

    // Assert
    expect($migrationRejected)->toBeTrue()
        ->and(Schema::connection('pgsql_admin')->hasTable('seguimientos_preventivos'))->toBeFalse()
        ->and(Schema::connection('pgsql_admin')->hasColumn('alertas_preventivas', 'justificacion_descarte'))->toBeFalse()
        ->and(DB::connection('pgsql_admin')->table('alertas_preventivas')->where('estado', 'descartada')->exists())->toBeTrue();
});

function hu17MigrationInstance(): object
{
    return require database_path('migrations/2026_10_21_140000_add_hu17_preventive_followups.php');
}

/** @return array{id: string, area_id: int, professional_id: string} */
function hu17MigrationCreateLegacyTerminalAlert(bool $demo, string $state): array
{
    $area = Area::factory()->create();
    $specialist = Especialista::factory()
        ->withRole('specialist')
        ->withProfesional($area)
        ->create();
    $professional = Profesional::query()->where('user_id', $specialist->id)->firstOrFail();
    $faculty = Facultad::factory()->create();
    $career = Carrera::factory()->create(['facultad_id' => $faculty->id]);
    $patient = Paciente::factory()->create([
        'carrera_id' => $career->id,
        'creado_por_profesional_id' => $professional->id,
    ]);
    $rubrica = Rubrica::query()->where('codigo', Rubrica::CODIGO_RIESGO_PREVENTIVO)->firstOrFail();
    $version = RubricaVersion::query()->create([
        'rubrica_id' => $rubrica->id,
        'numero_version' => 1,
        'vigente_desde' => now()->subDay(),
        'umbral_puntaje_global' => null,
        'justificacion_cambio' => null,
        'validada_en' => $demo ? null : now()->subDay()->toDateString(),
        'referencia_validacion' => $demo ? null : 'HU17 migration test validation',
        'es_demo' => $demo,
        'creada_por_profesional_id' => $professional->id,
    ]);
    $evaluation = EvaluacionRiesgo::query()->create([
        'paciente_id' => $patient->codigo,
        'area_id' => $area->id,
        'rubrica_version_id' => $version->id,
        'disparador' => 'crisis_psicologica',
        'puntaje' => '1.00',
        'detalle' => '{}',
        'estado_evaluacion' => 'evaluada',
        'evento_origen_tipo' => 'MigrationTest',
        'evento_origen_id' => (string) Str::uuid(),
        'evaluada_en' => now(),
    ]);
    $id = (string) Str::uuid();

    DB::connection('pgsql_admin')->table('alertas_preventivas')->insert([
        'id' => $id,
        'paciente_id' => $patient->codigo,
        'area_id' => $area->id,
        'evaluacion_riesgo_id' => $evaluation->id,
        'rubrica_version_id' => $version->id,
        'senal' => 'crisis_psicologica',
        'valor_calculado' => app(AreaEncryptionService::class)->encrypt('1', $area->id),
        'umbral' => '1.00',
        'estado' => $state,
        'responsable_profesional_id' => $professional->id,
        'generada_en' => now()->subDay(),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    return ['id' => $id, 'area_id' => (int) $area->id, 'professional_id' => (string) $professional->id];
}
