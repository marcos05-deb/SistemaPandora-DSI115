<?php

namespace Tests\Feature\Compliance;

use PHPUnit\Framework\Attributes\Group;

#[Group('hipaa')]
class AuditLoggingTest extends ComplianceTestCase
{
    public function test_registro_de_auditoria_por_cada_acceso_a_phi()
    {
        $this->markTestIncomplete('Audit logging pendiente para Fase 3 (HIPAA § 164.312(b))');
    }

    public function test_inmutabilidad_de_los_registros_de_auditoria()
    {
        $this->markTestIncomplete('Audit logging pendiente para Fase 3 (HIPAA § 164.312(b))');
    }
}
