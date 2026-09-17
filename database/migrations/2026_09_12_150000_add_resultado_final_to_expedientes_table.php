<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * HU-10: resultado clínico final obligatorio al cerrar el expediente.
     */
    public function up(): void
    {
        DB::connection('pgsql_admin')->statement(<<<'SQL'
            ALTER TABLE expedientes
                ADD COLUMN IF NOT EXISTS resultado_final TEXT NULL
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql_admin')->statement(<<<'SQL'
            ALTER TABLE expedientes
                DROP COLUMN IF EXISTS resultado_final
        SQL);
    }
};
