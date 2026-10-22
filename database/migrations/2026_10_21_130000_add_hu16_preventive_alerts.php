<?php

declare(strict_types=1);

use App\Models\Cita;
use App\Models\Consulta;
use App\Services\AreaEncryptionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('pgsql_admin');

        Schema::connection('pgsql_admin')->table('consultas', function (Blueprint $table): void {
            $table->text('crisis_psicologica')->nullable();
            $table->text('indicadores_tendencia_suicida')->nullable();
        });

        if ($connection->table('consultas')->exists()) {
            $encryption = app(AreaEncryptionService::class);
            $connection->table('consultas as consulta')
                ->join('expedientes as expediente', 'expediente.id', '=', 'consulta.expediente_id')
                ->select(['consulta.id as consulta_id', 'expediente.area_id'])
                ->orderBy('consulta.id')
                ->chunk(500, function ($consultas) use ($connection, $encryption): void {
                    foreach ($consultas as $consulta) {
                        $connection->table('consultas')
                            ->where('id', $consulta->consulta_id)
                            ->update([
                                'crisis_psicologica' => $encryption->encrypt('No', $consulta->area_id),
                                'indicadores_tendencia_suicida' => $encryption->encrypt('[]', $consulta->area_id),
                            ]);
                    }
                });
        }

        $connection->statement('ALTER TABLE consultas ALTER COLUMN crisis_psicologica SET NOT NULL');
        $connection->statement('ALTER TABLE consultas ALTER COLUMN indicadores_tendencia_suicida SET NOT NULL');

        $connection->statement(<<<'SQL'
            ALTER TABLE evaluaciones_riesgo
            DROP CONSTRAINT IF EXISTS evaluaciones_riesgo_evento_origen_tipo_evento_origen_id_rubrica_version_id_unique
        SQL);
        $connection->statement('ALTER TABLE evaluaciones_riesgo ALTER COLUMN rubrica_version_id DROP NOT NULL');
        $connection->statement('ALTER TABLE evaluaciones_riesgo ALTER COLUMN puntaje DROP NOT NULL');
        $connection->statement('ALTER TABLE evaluaciones_riesgo ALTER COLUMN detalle DROP NOT NULL');
        $connection->statement("ALTER TABLE evaluaciones_riesgo ADD COLUMN estado_evaluacion VARCHAR(20) NOT NULL DEFAULT 'evaluada'");
        $connection->statement(<<<'SQL'
            ALTER TABLE evaluaciones_riesgo
            ADD CONSTRAINT evaluaciones_riesgo_estado_check
            CHECK (estado_evaluacion IN ('evaluada', 'omitida'))
        SQL);
        $connection->statement(<<<'SQL'
            ALTER TABLE evaluaciones_riesgo
            ADD CONSTRAINT evaluaciones_riesgo_datos_estado_check
            CHECK (
                (estado_evaluacion = 'omitida' AND rubrica_version_id IS NULL AND puntaje IS NULL AND detalle IS NULL)
                OR
                (estado_evaluacion = 'evaluada' AND rubrica_version_id IS NOT NULL AND puntaje IS NOT NULL AND detalle IS NOT NULL)
            )
        SQL);
        $connection->statement(<<<'SQL'
            CREATE UNIQUE INDEX evaluaciones_riesgo_evento_version_unique
            ON evaluaciones_riesgo (evento_origen_tipo, evento_origen_id, rubrica_version_id)
            WHERE estado_evaluacion = 'evaluada'
        SQL);
        $connection->statement(<<<'SQL'
            CREATE UNIQUE INDEX evaluaciones_riesgo_evento_omitida_unique
            ON evaluaciones_riesgo (evento_origen_tipo, evento_origen_id, area_id)
            WHERE estado_evaluacion = 'omitida'
        SQL);

        Schema::connection('pgsql_admin')->create('alertas_preventivas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('paciente_id')->constrained('pacientes', 'codigo')->restrictOnDelete();
            $table->foreignId('area_id')->constrained('areas')->restrictOnDelete();
            $table->foreignUuid('evaluacion_riesgo_id')->constrained('evaluaciones_riesgo')->restrictOnDelete();
            $table->foreignUuid('rubrica_version_id')->constrained('rubrica_versiones')->restrictOnDelete();
            $table->string('senal', 40);
            $table->text('valor_calculado');
            $table->decimal('umbral', 8, 2);
            $table->string('estado', 24)->default('activa');
            $table->foreignUuid('responsable_profesional_id')->constrained('profesionales')->restrictOnDelete();
            $table->timestampTz('generada_en');
            $table->timestampsTz();
            $table->unique(['evaluacion_riesgo_id', 'senal'], 'alertas_evaluacion_senal_unique');
            $table->index(['area_id', 'generada_en']);
        });
        $connection->statement(<<<'SQL'
            ALTER TABLE alertas_preventivas
            ADD CONSTRAINT alertas_preventivas_datos_check
            CHECK (senal IN ('crisis_psicologica', 'ausencia_cita', 'tendencia_suicida') AND umbral > 0 AND estado IN ('activa', 'en_seguimiento', 'cerrada', 'descartada'))
        SQL);
        $connection->statement(<<<'SQL'
            CREATE UNIQUE INDEX alertas_preventivas_abiertas_unicas
            ON alertas_preventivas (paciente_id, area_id, senal)
            WHERE estado IN ('activa', 'en_seguimiento')
        SQL);

        Schema::connection('pgsql_admin')->create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        $this->restrictApplicationPrivileges();
    }

    public function down(): void
    {
        $connection = DB::connection('pgsql_admin');
        $this->assertNoHu16DataWouldBeLost($connection);

        Schema::connection('pgsql_admin')->dropIfExists('notifications');
        Schema::connection('pgsql_admin')->dropIfExists('alertas_preventivas');
        $connection->statement('DROP INDEX IF EXISTS evaluaciones_riesgo_evento_version_unique');
        $connection->statement('DROP INDEX IF EXISTS evaluaciones_riesgo_evento_omitida_unique');
        $connection->statement('ALTER TABLE evaluaciones_riesgo DROP CONSTRAINT IF EXISTS evaluaciones_riesgo_estado_check');
        $connection->statement('ALTER TABLE evaluaciones_riesgo DROP CONSTRAINT IF EXISTS evaluaciones_riesgo_datos_estado_check');
        $connection->statement('ALTER TABLE evaluaciones_riesgo DROP COLUMN estado_evaluacion');
        $connection->statement('ALTER TABLE evaluaciones_riesgo ALTER COLUMN rubrica_version_id SET NOT NULL');
        $connection->statement('ALTER TABLE evaluaciones_riesgo ALTER COLUMN puntaje SET NOT NULL');
        $connection->statement('ALTER TABLE evaluaciones_riesgo ALTER COLUMN detalle SET NOT NULL');
        $connection->statement(<<<'SQL'
            ALTER TABLE evaluaciones_riesgo
            ADD CONSTRAINT evaluaciones_riesgo_evento_origen_tipo_evento_origen_id_rubrica_version_id_unique
            UNIQUE (evento_origen_tipo, evento_origen_id, rubrica_version_id)
        SQL);
        Schema::connection('pgsql_admin')->table('consultas', function (Blueprint $table): void {
            $table->dropColumn(['crisis_psicologica', 'indicadores_tendencia_suicida']);
        });
    }

    private function assertNoHu16DataWouldBeLost($connection): void
    {
        $schema = Schema::connection('pgsql_admin');

        if ($schema->hasTable('alertas_preventivas') && $connection->table('alertas_preventivas')->exists()) {
            throw new RuntimeException('HU-16 no puede revertirse mientras existan alertas preventivas.');
        }

        if ($schema->hasTable('notifications') && $connection->table('notifications')->exists()) {
            throw new RuntimeException('HU-16 no puede revertirse mientras existan notificaciones.');
        }

        if ($schema->hasTable('evaluaciones_riesgo')) {
            $hasHu16EvaluationCriterion = false;
            $evaluations = $connection->table('evaluaciones_riesgo');

            if ($schema->hasColumn('evaluaciones_riesgo', 'evento_origen_tipo')) {
                $evaluations->whereIn('evento_origen_tipo', [Consulta::class, Cita::class]);
                $hasHu16EvaluationCriterion = true;
            }

            if ($schema->hasColumn('evaluaciones_riesgo', 'estado_evaluacion')) {
                if ($hasHu16EvaluationCriterion) {
                    $evaluations->orWhere('estado_evaluacion', 'omitida');
                } else {
                    $evaluations->where('estado_evaluacion', 'omitida');
                }
                $hasHu16EvaluationCriterion = true;
            }

            if ($hasHu16EvaluationCriterion && $evaluations->exists()) {
                throw new RuntimeException('HU-16 no puede revertirse mientras existan evaluaciones de eventos HU-16.');
            }
        }

        if ($this->hasNonNeutralConsultationSignals($connection, $schema)) {
            throw new RuntimeException('HU-16 no puede revertirse mientras existan señales de riesgo en consultas.');
        }
    }

    private function hasNonNeutralConsultationSignals($connection, $schema): bool
    {
        if (! $schema->hasTable('consultas')
            || ! $schema->hasColumn('consultas', 'crisis_psicologica')
            || ! $schema->hasColumn('consultas', 'indicadores_tendencia_suicida')) {
            return false;
        }

        if (! $connection->table('consultas')->exists()) {
            return false;
        }

        if (! $schema->hasTable('expedientes')) {
            return true;
        }

        $encryption = app(AreaEncryptionService::class);
        $hasNonNeutralSignals = false;
        $connection->table('consultas as consulta')
            ->join('expedientes as expediente', 'expediente.id', '=', 'consulta.expediente_id')
            ->select([
                'consulta.id',
                'consulta.crisis_psicologica',
                'consulta.indicadores_tendencia_suicida',
                'expediente.area_id',
            ])
            ->orderBy('consulta.id')
            ->chunk(500, function ($consultas) use ($encryption, &$hasNonNeutralSignals): bool {
                foreach ($consultas as $consulta) {
                    try {
                        $crisis = $encryption->decrypt((string) $consulta->crisis_psicologica, $consulta->area_id);
                        $indicators = json_decode(
                            $encryption->decrypt((string) $consulta->indicadores_tendencia_suicida, $consulta->area_id),
                            false,
                            512,
                            JSON_THROW_ON_ERROR,
                        );
                    } catch (Throwable) {
                        $hasNonNeutralSignals = true;

                        return false;
                    }

                    if (mb_strtolower(trim($crisis)) !== 'no' || $indicators !== []) {
                        $hasNonNeutralSignals = true;

                        return false;
                    }
                }

                return true;
            });

        return $hasNonNeutralSignals;
    }

    private function restrictApplicationPrivileges(): void
    {
        $appUser = config('database.connections.pgsql.username');
        $adminUser = config('database.connections.pgsql_admin.username');
        if (! is_string($appUser) || trim($appUser) === '' || ! is_string($adminUser) || trim($adminUser) === '' || $appUser === $adminUser) {
            throw new RuntimeException('Se requieren roles pgsql y pgsql_admin distintos para proteger las alertas.');
        }

        $quotedUser = DB::connection('pgsql_admin')->selectOne('SELECT quote_ident(?) AS role_name', [$appUser])->role_name;
        foreach (['alertas_preventivas', 'notifications'] as $table) {
            DB::connection('pgsql_admin')->statement("REVOKE ALL ON TABLE {$table} FROM PUBLIC, {$quotedUser}");
            DB::connection('pgsql_admin')->statement("GRANT SELECT, INSERT, UPDATE ON TABLE {$table} TO {$quotedUser}");
        }
    }
};
