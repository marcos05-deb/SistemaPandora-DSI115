<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-21: índices para bitácora, REVOKE TRUNCATE y trigger de inmutabilidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_admin')->table('audits', function (Blueprint $table): void {
            if (! $this->indexExists('audits_created_at_index')) {
                $table->index('created_at', 'audits_created_at_index');
            }
            if (! $this->indexExists('audits_event_index')) {
                $table->index('event', 'audits_event_index');
            }
            if (! $this->indexExists('audits_user_id_created_at_index')) {
                $table->index(['user_id', 'created_at'], 'audits_user_id_created_at_index');
            }
        });

        $appUser = config('database.connections.pgsql.username');
        $adminUser = config('database.connections.pgsql_admin.username');

        if ($appUser && $appUser !== $adminUser) {
            DB::connection('pgsql_admin')->statement(
                "REVOKE UPDATE, DELETE, TRUNCATE ON TABLE audits FROM {$appUser}"
            );
            DB::connection('pgsql_admin')->statement(
                "GRANT SELECT, INSERT ON TABLE audits TO {$appUser}"
            );
        }

        DB::connection('pgsql_admin')->unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_audits_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'audits es append-only: UPDATE/DELETE/TRUNCATE no permitidos'
        USING ERRCODE = '42501';
END;
$$;
SQL);

        DB::connection('pgsql_admin')->unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS trg_audits_immutable_row ON audits;
CREATE TRIGGER trg_audits_immutable_row
    BEFORE UPDATE OR DELETE ON audits
    FOR EACH ROW
    EXECUTE FUNCTION prevent_audits_mutation();
SQL);

        DB::connection('pgsql_admin')->unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS trg_audits_immutable_truncate ON audits;
CREATE TRIGGER trg_audits_immutable_truncate
    BEFORE TRUNCATE ON audits
    FOR EACH STATEMENT
    EXECUTE FUNCTION prevent_audits_mutation();
SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql_admin')->unprepared('DROP TRIGGER IF EXISTS trg_audits_immutable_truncate ON audits');
        DB::connection('pgsql_admin')->unprepared('DROP TRIGGER IF EXISTS trg_audits_immutable_row ON audits');
        DB::connection('pgsql_admin')->unprepared('DROP FUNCTION IF EXISTS prevent_audits_mutation()');

        Schema::connection('pgsql_admin')->table('audits', function (Blueprint $table): void {
            if ($this->indexExists('audits_user_id_created_at_index')) {
                $table->dropIndex('audits_user_id_created_at_index');
            }
            if ($this->indexExists('audits_event_index')) {
                $table->dropIndex('audits_event_index');
            }
            if ($this->indexExists('audits_created_at_index')) {
                $table->dropIndex('audits_created_at_index');
            }
        });

        $appUser = config('database.connections.pgsql.username');
        $adminUser = config('database.connections.pgsql_admin.username');

        if ($appUser && $appUser !== $adminUser) {
            DB::connection('pgsql_admin')->statement(
                "GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE audits TO {$appUser}"
            );
        }
    }

    private function indexExists(string $name): bool
    {
        $row = DB::connection('pgsql_admin')->selectOne(
            'SELECT 1 AS ok FROM pg_indexes WHERE schemaname = current_schema() AND indexname = ?',
            [$name]
        );

        return $row !== null;
    }
};
