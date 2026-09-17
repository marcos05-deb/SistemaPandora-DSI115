<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Garantiza a nivel de BD un solo expediente activo por paciente y área.
     * Activo = abierto | en_atencion; no aplica a cerrados ni soft-deleted.
     * Usa pgsql_admin porque CREATE INDEX exige ownership de la tabla.
     */
    public function up(): void
    {
        DB::connection('pgsql_admin')->statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS idx_expedientes_activo_unico
            ON expedientes (paciente_id, area_id)
            WHERE estado IN ('abierto', 'en_atencion')
              AND deleted_at IS NULL
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('pgsql_admin')->statement('DROP INDEX IF EXISTS idx_expedientes_activo_unico');
    }
};
