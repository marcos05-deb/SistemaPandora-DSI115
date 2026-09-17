<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Plan de atención por consulta (HU-08 / PAN-15).
     * Nullable para filas históricas; obligatorio en nuevas vía FormRequest.
     */
    public function up(): void
    {
        DB::connection('pgsql_admin')->statement(
            'ALTER TABLE consultas ADD COLUMN IF NOT EXISTS plan_atencion TEXT NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('pgsql_admin')->statement(
            'ALTER TABLE consultas DROP COLUMN IF EXISTS plan_atencion'
        );
    }
};
