<?php

namespace Tests\Feature\Compliance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class ComplianceTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Define the database connection used for migrating fresh.
     * We force 'pgsql_admin' so migrations run with elevated privileges,
     * while the tests themselves run with the restricted 'pgsql' connection.
     *
     * @return array
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Aseguramos que la base de datos de tests esté configurada
        $this->artisan('migrate:fresh', [
            '--database' => 'pgsql_admin',
            '--seed' => true,
        ]);
        
        // Inyectamos una llave de encriptación falsa en la sesión para que las factorías de Modelos
        // que utilizan EncryptedFieldCast puedan funcionar sin pasar por el login real en cada test.
        $fakeKey = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        session(['_sym_key' => $fakeKey]);
        $this->withSession(['_sym_key' => $fakeKey]);
        $this->withSession(['_sym_key' => $fakeKey]);
        
        // Ensure dependent tables have at least one record to avoid FK violations
        \Illuminate\Support\Facades\DB::connection('pgsql_admin')->table('facultades')->insertOrIgnore(['id' => 1, 'nombre' => 'Facultad Base']);
        \Illuminate\Support\Facades\DB::connection('pgsql_admin')->table('carreras')->insertOrIgnore(['id' => 1, 'nombre' => 'Carrera Base', 'facultad_id' => 1]);
    }

    protected function migrateFreshUsing()
    {
        return [
            '--drop-views' => $this->shouldDropViews(),
            '--drop-types' => $this->shouldDropTypes(),
            '--seed' => $this->shouldSeed(),
            '--database' => 'pgsql_admin',
        ];
    }
}
