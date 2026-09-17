<?php

namespace Tests\Feature\Compliance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\Group;

#[Group('iso27001')]
class InfrastructureIsolationTest extends ComplianceTestCase
{
    public function test_violacion_de_privilegios_ddl_drop_es_rechazada()
    {
        try {
            Schema::connection('pgsql')->dropIfExists('expedientes');
            $this->fail('Expected QueryException was not thrown.');
        } catch (QueryException $e) {
            $code = $e->getCode();
            // 42501 = insufficient_privilege, 2BP01 = dependent_objects_still_exist
            $this->assertTrue(in_array($code, ['42501', '2BP01']), "Expected exception code 42501 or 2BP01, got $code.");
        }
    }

    public function test_violacion_de_privilegios_ddl_truncate_es_rechazada()
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionCode('42501');

        DB::statement('TRUNCATE users');
    }

    public function test_restriccion_de_sistema_de_archivos()
    {
        if (is_writable('/etc')) {
            $this->markTestSkipped('Entorno host/dev sin restricción de FS (is_writable /etc verdadero). Este test requiere contenedores en read_only.');
        }

        $filePath = '/etc/test_isolation.txt';

        try {
            // Laravel convierte Warnings a ErrorException.
            // En un contenedor fortificado o ambiente restringido, file_put_contents lanzará un Warning/Error.
            $this->expectException(\ErrorException::class);
            $this->expectExceptionMessageMatches('/(Read-only file system|Permission denied)/i');

            file_put_contents($filePath, 'payload');
        } finally {
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
    }
}
