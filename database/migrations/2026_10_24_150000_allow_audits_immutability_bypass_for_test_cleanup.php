<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HU-21 follow-up: permite cleanup de pruebas con set_config('app.bypass_audits_immutability','on').
 * El rol de aplicación sigue sin DELETE/UPDATE/TRUNCATE; solo el bypass + privilegio DML del admin limpia.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql_admin')->unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_audits_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF current_setting('app.bypass_audits_immutability', true) = 'on' THEN
        IF TG_OP = 'DELETE' THEN
            RETURN OLD;
        END IF;

        RETURN NEW;
    END IF;

    RAISE EXCEPTION 'audits es append-only: UPDATE/DELETE/TRUNCATE no permitidos'
        USING ERRCODE = '42501';
END;
$$;
SQL);
    }

    public function down(): void
    {
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
    }
};
