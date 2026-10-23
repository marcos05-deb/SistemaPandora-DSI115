<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_admin')->create('rubricas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('codigo', 80)->unique();
            $table->string('nombre', 180);
            $table->text('descripcion')->nullable();
            $table->timestampsTz();
        });

        DB::connection('pgsql_admin')->table('rubricas')->insert([
            'id' => (string) Str::uuid(),
            'codigo' => 'riesgo_preventivo',
            'nombre' => 'Rúbrica de riesgo preventivo',
            'descripcion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::connection('pgsql_admin')->create('rubrica_versiones', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('rubrica_id')->constrained('rubricas')->restrictOnDelete();
            $table->unsignedInteger('numero_version');
            $table->timestampTz('vigente_desde');
            $table->decimal('umbral_puntaje_global', 8, 2)->nullable();
            $table->text('justificacion_cambio')->nullable();
            $table->date('validada_en')->nullable();
            $table->string('referencia_validacion', 255)->nullable();
            $table->boolean('es_demo')->default(false);
            $table->foreignUuid('creada_por_profesional_id')->constrained('profesionales')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['rubrica_id', 'numero_version']);
            $table->unique(['rubrica_id', 'vigente_desde']);
        });

        Schema::connection('pgsql_admin')->create('rubrica_variables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('rubrica_version_id')->constrained('rubrica_versiones')->restrictOnDelete();
            $table->string('tipo_senal', 40);
            $table->string('clave_indicador', 120)->nullable();
            $table->string('modo_calculo', 30);
            $table->unsignedInteger('ventana_dias')->nullable();
            $table->decimal('ponderacion', 6, 2);
            $table->decimal('umbral', 8, 2);
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::connection('pgsql_admin')->create('evaluaciones_riesgo', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('paciente_id')->constrained('pacientes', 'codigo')->restrictOnDelete();
            $table->foreignId('area_id')->constrained('areas')->restrictOnDelete();
            $table->foreignUuid('rubrica_version_id')->constrained('rubrica_versiones')->restrictOnDelete();
            $table->string('disparador', 120);
            $table->decimal('puntaje', 8, 2);
            $table->text('detalle');
            $table->string('evento_origen_tipo', 120);
            $table->uuid('evento_origen_id');
            $table->timestampTz('evaluada_en');
            $table->unique(['evento_origen_tipo', 'evento_origen_id', 'rubrica_version_id']);
            $table->index(['area_id', 'evaluada_en']);
        });

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            ALTER TABLE rubrica_versiones
            ADD CONSTRAINT rubrica_versiones_validacion_check
            CHECK (
                (es_demo AND validada_en IS NULL AND referencia_validacion IS NULL)
                OR
                (NOT es_demo AND validada_en IS NOT NULL AND referencia_validacion IS NOT NULL AND length(trim(referencia_validacion)) > 0)
            )
        SQL);

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            ALTER TABLE rubrica_versiones
            ADD CONSTRAINT rubrica_versiones_valores_check
            CHECK (numero_version > 0 AND (umbral_puntaje_global IS NULL OR umbral_puntaje_global > 0))
        SQL);

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            ALTER TABLE rubrica_variables
            ADD CONSTRAINT rubrica_variables_valores_check
            CHECK (
                tipo_senal IN ('crisis_psicologica', 'ausencia_cita', 'tendencia_suicida')
                AND modo_calculo IN ('frecuencia', 'acumulacion', 'puntaje')
                AND ponderacion > 0
                AND umbral > 0
                AND (ventana_dias IS NULL OR ventana_dias > 0)
                AND (
                    (modo_calculo = 'frecuencia' AND ventana_dias IS NOT NULL)
                    OR (modo_calculo <> 'frecuencia')
                )
                AND (
                    (tipo_senal = 'tendencia_suicida' AND clave_indicador IS NOT NULL AND length(trim(clave_indicador)) > 0 AND modo_calculo = 'puntaje')
                    OR
                    (tipo_senal <> 'tendencia_suicida' AND clave_indicador IS NULL)
                )
            )
        SQL);

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            CREATE UNIQUE INDEX rubrica_variables_tuple_key_not_null_unique
            ON rubrica_variables (rubrica_version_id, tipo_senal, clave_indicador, modo_calculo)
            WHERE clave_indicador IS NOT NULL
        SQL);

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            CREATE UNIQUE INDEX rubrica_variables_tuple_key_null_unique
            ON rubrica_variables (rubrica_version_id, tipo_senal, modo_calculo)
            WHERE clave_indicador IS NULL
        SQL);

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.lock_rubrica_for_versioning(rubrica_uuid uuid)
            RETURNS void
            LANGUAGE plpgsql
            SECURITY DEFINER
            SET search_path = pg_catalog, public
            AS $$
            BEGIN
                PERFORM 1 FROM public.rubricas WHERE id = rubrica_uuid FOR UPDATE;
                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Rúbrica no encontrada';
                END IF;
            END;
            $$
        SQL);
        DB::connection('pgsql_admin')->statement(
            'REVOKE ALL ON FUNCTION public.lock_rubrica_for_versioning(uuid) FROM PUBLIC'
        );

        $this->restrictApplicationPrivileges();
    }

    public function down(): void
    {
        $this->assertNoRiskDataWouldBeLost();

        DB::connection('pgsql_admin')->statement('DROP FUNCTION IF EXISTS public.lock_rubrica_for_versioning(uuid)');
        Schema::connection('pgsql_admin')->dropIfExists('evaluaciones_riesgo');
        Schema::connection('pgsql_admin')->dropIfExists('rubrica_variables');
        Schema::connection('pgsql_admin')->dropIfExists('rubrica_versiones');
        Schema::connection('pgsql_admin')->dropIfExists('rubricas');
    }

    private function assertNoRiskDataWouldBeLost(): void
    {
        $connection = DB::connection('pgsql_admin');
        $schema = Schema::connection('pgsql_admin');

        foreach (['rubrica_versiones', 'rubrica_variables', 'evaluaciones_riesgo', 'alertas_preventivas'] as $table) {
            if ($schema->hasTable($table) && $connection->table($table)->exists()) {
                throw new RuntimeException("HU-15 no puede revertirse mientras {$table} contenga datos.");
            }
        }

        // The migration-owned canonical rubric is safe to remove when it has no dependent data.
        if ($schema->hasTable('rubricas') && $connection->table('rubricas')->where(function ($query): void {
            $query->where('codigo', '<>', 'riesgo_preventivo')
                ->orWhere('nombre', '<>', 'Rúbrica de riesgo preventivo')
                ->orWhereNotNull('descripcion');
        })->exists()) {
            throw new RuntimeException('HU-15 no puede revertirse mientras existan rúbricas con datos propios.');
        }
    }

    private function restrictApplicationPrivileges(): void
    {
        $appUser = config('database.connections.pgsql.username');
        $adminUser = config('database.connections.pgsql_admin.username');

        if (! is_string($appUser) || trim($appUser) === '' || ! is_string($adminUser) || trim($adminUser) === '') {
            throw new RuntimeException('Se requieren roles pgsql y pgsql_admin distintos para proteger la rúbrica.');
        }

        if ($appUser === $adminUser) {
            throw new RuntimeException('Los roles pgsql y pgsql_admin deben ser distintos para proteger la rúbrica.');
        }

        $quotedUser = DB::connection('pgsql_admin')
            ->selectOne('SELECT quote_ident(?) AS role_name', [$appUser])
            ->role_name;

        foreach (['rubricas', 'rubrica_versiones', 'rubrica_variables', 'evaluaciones_riesgo'] as $table) {
            DB::connection('pgsql_admin')->statement(
                "REVOKE UPDATE, DELETE, TRUNCATE ON TABLE {$table} FROM {$quotedUser}"
            );
            DB::connection('pgsql_admin')->statement(
                "GRANT SELECT, INSERT ON TABLE {$table} TO {$quotedUser}"
            );
        }

        DB::connection('pgsql_admin')->statement(
            "GRANT EXECUTE ON FUNCTION public.lock_rubrica_for_versioning(uuid) TO {$quotedUser}"
        );
    }
};
