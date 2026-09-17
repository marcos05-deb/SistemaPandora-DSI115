<?php

namespace Tests\Feature\Compliance;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\Group;

#[Group('iso27001')]
class PrivilegesTest extends ComplianceTestCase
{
    public function test_confirma_que_el_usuario_de_aplicacion_tiene_privilegios_dml_minimos()
    {
        $connection = DB::connection('pgsql');
        $dbUser = config('database.connections.pgsql.username');
        
        $this->assertNotEquals($dbUser, config('database.connections.pgsql_admin.username'));

        // 1. SELECT debe funcionar
        $this->assertNotNull($connection->select('SELECT 1'));

        // 2. INSERT debe funcionar
        $areaId = $connection->table('areas')->insertGetId(['nombre' => 'Test Privs', 'requiere_aprobacion_estricta' => false]);
        $this->assertGreaterThan(0, $areaId);

        // 3. UPDATE debe funcionar
        $updated = $connection->table('areas')->where('id', $areaId)->update(['nombre' => 'Test Updated']);
        $this->assertEquals(1, $updated);

        // 4. DELETE debe funcionar
        $deleted = $connection->table('areas')->where('id', $areaId)->delete();
        $this->assertEquals(1, $deleted);
    }

    public function test_falla_al_intentar_truncate_por_falta_de_privilegios()
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionCode('42501');

        DB::connection('pgsql')->statement('TRUNCATE TABLE areas CASCADE');
    }

    public function test_falla_al_intentar_alter_table_por_falta_de_privilegios()
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionCode('42501');

        DB::connection('pgsql')->statement('ALTER TABLE areas ADD COLUMN test_col VARCHAR');
    }

    public function test_falla_al_intentar_drop_table_por_falta_de_privilegios()
    {
        try {
            DB::connection('pgsql')->statement('DROP TABLE users');
            $this->fail('Expected QueryException was not thrown on DROP TABLE.');
        } catch (QueryException $e) {
            $code = $e->getCode();
            $this->assertTrue(in_array($code, ['42501', '2BP01']), "Expected 42501 or 2BP01, got $code");
        }
    }
}
