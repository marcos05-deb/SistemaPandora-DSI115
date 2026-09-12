<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * HU-07: autorizaciones temporales de referente sobre un paciente.
     */
    public function up(): void
    {
        DB::connection('pgsql_admin')->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS autorizaciones_paciente (
                id UUID PRIMARY KEY,
                paciente_id UUID NOT NULL REFERENCES pacientes(codigo) ON DELETE CASCADE,
                profesional_id UUID NOT NULL REFERENCES profesionales(id) ON DELETE CASCADE,
                otorgada_por_profesional_id UUID NULL REFERENCES profesionales(id) ON DELETE SET NULL,
                vigente_desde TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                vigente_hasta TIMESTAMPTZ NOT NULL,
                created_at TIMESTAMPTZ NULL,
                updated_at TIMESTAMPTZ NULL
            )
        SQL);

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_autorizaciones_paciente_vigente
                ON autorizaciones_paciente (paciente_id, profesional_id, vigente_hasta)
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql_admin')->statement('DROP TABLE IF EXISTS autorizaciones_paciente');
    }
};
