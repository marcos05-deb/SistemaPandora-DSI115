<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Jira HU-08: diagnóstico u observación — al menos uno, no ambos obligatorios.
     * DDL vía pgsql_admin por aislamiento de privilegios.
     */
    public function up(): void
    {
        DB::connection('pgsql_admin')->statement(
            'ALTER TABLE consultas ALTER COLUMN notas_clinicas DROP NOT NULL'
        );
        DB::connection('pgsql_admin')->statement(
            'ALTER TABLE consultas ALTER COLUMN diagnostico DROP NOT NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('pgsql_admin')->statement(
            'ALTER TABLE consultas ALTER COLUMN notas_clinicas SET NOT NULL'
        );
        DB::connection('pgsql_admin')->statement(
            'ALTER TABLE consultas ALTER COLUMN diagnostico SET NOT NULL'
        );
    }
};
