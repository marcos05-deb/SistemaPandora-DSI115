<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * RP-04: inmutabilidad real de auditorías para el rol de aplicación.
     */
    public function up(): void
    {
        $appUser = config('database.connections.pgsql.username');
        $adminUser = config('database.connections.pgsql_admin.username');

        if (! $appUser || $appUser === $adminUser) {
            return;
        }

        DB::connection('pgsql_admin')->statement(
            "REVOKE UPDATE, DELETE ON TABLE audits FROM {$appUser}"
        );
        DB::connection('pgsql_admin')->statement(
            "GRANT SELECT, INSERT ON TABLE audits TO {$appUser}"
        );
    }

    public function down(): void
    {
        $appUser = config('database.connections.pgsql.username');
        $adminUser = config('database.connections.pgsql_admin.username');

        if (! $appUser || $appUser === $adminUser) {
            return;
        }

        DB::connection('pgsql_admin')->statement(
            "GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE audits TO {$appUser}"
        );
    }
};
