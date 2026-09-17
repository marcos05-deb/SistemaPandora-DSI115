<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * NH-03: proyección consultable de ausencias para estadísticas preventivas.
     */
    public function up(): void
    {
        DB::connection('pgsql_admin')->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS estadisticas_preventivas_ausencias (
                id UUID PRIMARY KEY,
                cita_id UUID NOT NULL UNIQUE REFERENCES citas(id) ON DELETE CASCADE,
                paciente_id UUID NOT NULL REFERENCES pacientes(codigo) ON DELETE CASCADE,
                profesional_id UUID NOT NULL REFERENCES profesionales(id) ON DELETE CASCADE,
                area_id BIGINT NOT NULL REFERENCES areas(id) ON DELETE CASCADE,
                fecha_registro TIMESTAMPTZ NOT NULL,
                created_at TIMESTAMPTZ NULL,
                updated_at TIMESTAMPTZ NULL
            )
        SQL);

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_estadisticas_ausencias_paciente
                ON estadisticas_preventivas_ausencias (paciente_id, fecha_registro)
        SQL);

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_estadisticas_ausencias_area
                ON estadisticas_preventivas_ausencias (area_id, fecha_registro)
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql_admin')->statement('DROP TABLE IF EXISTS estadisticas_preventivas_ausencias');
    }
};
