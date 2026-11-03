<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * HU-08: referencia a una institución externa registrada en la consulta.
     * Los datos clínicos van cifrados por área (TEXT); solo el indicador es plano.
     */
    public function up(): void
    {
        DB::connection('pgsql_admin')->statement(
            'ALTER TABLE consultas
                ADD COLUMN IF NOT EXISTS referencia_externa BOOLEAN NOT NULL DEFAULT FALSE,
                ADD COLUMN IF NOT EXISTS institucion_destino TEXT NULL,
                ADD COLUMN IF NOT EXISTS motivo_referencia TEXT NULL,
                ADD COLUMN IF NOT EXISTS tipo_referencia TEXT NULL,
                ADD COLUMN IF NOT EXISTS prioridad_referencia TEXT NULL'
        );
    }

    public function down(): void
    {
        DB::connection('pgsql_admin')->statement(
            'ALTER TABLE consultas
                DROP COLUMN IF EXISTS referencia_externa,
                DROP COLUMN IF EXISTS institucion_destino,
                DROP COLUMN IF EXISTS motivo_referencia,
                DROP COLUMN IF EXISTS tipo_referencia,
                DROP COLUMN IF EXISTS prioridad_referencia'
        );
    }
};
