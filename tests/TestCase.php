<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // OwenIt desactiva auditoría en consola por defecto; PHPUnit corre en CLI.
        config(['audit.console' => true]);

        $this->withoutVite();
    }

    protected function migrateFreshUsing()
    {
        return [
            '--drop-views' => true,
            '--drop-types' => true,
            '--database' => 'pgsql_admin',
        ];
    }

    /**
     * migrate:fresh / wipe pueden intentar TRUNCATE de audits; el trigger HU-21 lo bloquea.
     */
    /**
     * Sin `: void` — debe coincidir con RefreshDatabase del framework (Laravel 11).
     */
    protected function beforeRefreshingDatabase()
    {
        try {
            DB::connection('pgsql_admin')->unprepared(
                "SELECT set_config('app.bypass_audits_immutability', 'on', false)"
            );
        } catch (Throwable) {
            // BD aún sin migrar o sin la función.
        }
    }

    protected function afterRefreshingDatabase()
    {
        try {
            DB::connection('pgsql_admin')->unprepared(
                "SELECT set_config('app.bypass_audits_immutability', 'off', false)"
            );
        } catch (Throwable) {
            // ignore
        }
    }
}
