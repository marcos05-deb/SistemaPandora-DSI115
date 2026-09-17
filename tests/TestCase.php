<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
}
