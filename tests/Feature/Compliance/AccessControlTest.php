<?php

namespace Tests\Feature\Compliance;

use App\Models\Area;
use App\Models\Especialista;
use App\Models\Role;
use App\Models\Paciente;
use App\Models\Expediente;
use PHPUnit\Framework\Attributes\Group;

#[Group('hipaa')]
#[Group('owasp-v4')]
class AccessControlTest extends ComplianceTestCase
{
    private $areaPsico;
    private $areaOdon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(\App\Services\Crypto\EncryptionContextService::class, new class extends \App\Services\Crypto\EncryptionContextService {
            public function getKey(): string {
                return str_repeat('A', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
            }
        });

        $this->areaPsico = Area::factory()->create(['nombre' => 'Psicología', 'requiere_aprobacion_estricta' => false]);
        $this->areaOdon = Area::factory()->create(['nombre' => 'Odontología', 'requiere_aprobacion_estricta' => false]);
    }

    public function test_segmentacion_transversal_areascope()
    {
        $odonSpecialist = Especialista::factory()->withProfesional($this->areaOdon)->withRole('specialist')->create();
        $psicoSpecialist = Especialista::factory()->withProfesional($this->areaPsico)->withRole('specialist')->create();

        $pacienteOdon = Paciente::factory()->create(['creado_por_profesional_id' => $odonSpecialist->profesional->id]);
        $expOdon = Expediente::factory()->create(['paciente_id' => $pacienteOdon->codigo, 'area_id' => $this->areaOdon->id]);

        $pacientePsico = Paciente::factory()->create(['creado_por_profesional_id' => $psicoSpecialist->profesional->id]);
        $expPsico = Expediente::factory()->create(['paciente_id' => $pacientePsico->codigo, 'area_id' => $this->areaPsico->id]);

        // Cobertura Espejo (Positiva)
        $this->actingAs($odonSpecialist);
        $responsePositiva = $this->get('/expedientes/' . $expOdon->id);
        if ($responsePositiva->status() === 500) {
            dump($responsePositiva->exception ?? 'Unknown 500 error in Positiva');
        }
        $this->assertTrue(in_array($responsePositiva->status(), [200, 302, 404])); // Según la implementación de view

        // Cobertura Negativa (Fuga Cero y 403)
        $responseNegativa = $this->get('/expedientes/' . $expPsico->id);
        if ($responseNegativa->status() === 500) {
            dump($responseNegativa->exception ?? 'Unknown 500 error in Negativa');
        }
        $this->assertTrue(in_array($responseNegativa->status(), [403, 404]));
        
        if ($responseNegativa->status() === 403) {
            $responseNegativa->assertDontSee($pacientePsico->carnet);
            $responseNegativa->assertDontSee($pacientePsico->codigo);
        }
    }

    public function test_idor_en_nivel_psicosocial()
    {
        $refA = Especialista::factory()->withProfesional($this->areaPsico)->withRole('psychosocial_referent')->create();
        $refB = Especialista::factory()->withProfesional($this->areaPsico)->withRole('psychosocial_referent')->create();

        $pacienteB = Paciente::factory()->create(['creado_por_profesional_id' => $refB->profesional->id]);

        // Cobertura Espejo (Referente B puede ver, editar a su paciente)
        $this->actingAs($refB);
        $this->get('/pacientes/' . $pacienteB->carnet)->assertStatus(200);

        // Cobertura Negativa (Referente A no puede ver al paciente de B)
        $this->actingAs($refA);
        
        $responseGet = $this->get('/pacientes/' . $pacienteB->carnet);
        $responseGet->assertStatus(403);
        $responseGet->assertDontSee($pacienteB->carnet);

        // Vectores de mutación IDOR
        $responsePut = $this->put('/pacientes/' . $pacienteB->carnet, ['nombre_completo' => 'Hack']);
        $this->assertTrue(in_array($responsePut->status(), [403, 404, 405]));

        $responseDelete = $this->delete('/pacientes/' . $pacienteB->carnet);
        $this->assertTrue(in_array($responseDelete->status(), [403, 404, 405]));
    }

    public function test_cierre_operativo_administrativo()
    {
        $admin = Especialista::factory()->withRole('sysadmin')->create();

        $this->actingAs($admin);
        
        // Sysadmin intenta acceder a ruta clínica
        $response = $this->get('/pacientes');
        $response->assertStatus(403);
    }

    public function test_monopolio_de_registro_clinico()
    {
        $specialist = Especialista::factory()->withProfesional($this->areaOdon)->withRole('specialist')->create();
        $referent = Especialista::factory()->withProfesional($this->areaPsico)->withRole('psychosocial_referent')->create();

        $pacienteData = Paciente::factory()->make()->toArray();
        $pacienteData['responsable_parentesco'] = 'Otro';
        $pacienteData['responsable_nombre'] = 'Resp';
        $pacienteData['responsable_telefono'] = '77777777';
        $pacienteData['responsable_direccion'] = 'Dir';

        // Cobertura Espejo (Referente puede crear)
        $this->actingAs($referent);
        $this->post('/pacientes', $pacienteData)->assertStatus(302); // Redirige al éxito

        // Cobertura Negativa (Especialista no puede crear)
        $this->actingAs($specialist);
        
        $pacienteData['carnet'] = 'ZZ99999';
        $response = $this->post('/pacientes', $pacienteData);
        $response->assertStatus(403);
    }

    public function test_unicidad_jerarquica_area_coordinator()
    {
        $admin = Especialista::factory()->withRole('sysadmin')->create();
        $coord1 = Especialista::factory()->withProfesional($this->areaPsico)->withRole('area_coordinator')->create();
        $spec2 = Especialista::factory()->withProfesional($this->areaPsico)->create();

        $this->actingAs($admin);

        // Cobertura Espejo: Se puede asignar coordinador a Odontología (vacío)
        $spec3 = Especialista::factory()->create();
        $roleCoordinator = Role::where('slug', 'area_coordinator')->first();
        
        $responseExito = $this->put("/admin/users/{$spec3->id}", [
            'name' => 'Spec3',
            'email' => 'spec3@test.com',
            'role_id' => $roleCoordinator->id,
            'area_id' => $this->areaOdon->id,
            'especialidad' => 'Odontología',
            'numero_registro' => '777'
        ]);
        $responseExito->assertStatus(302);
        $responseExito->assertSessionHasNoErrors();

        // Intenta ascender a spec2 como coordinador en Psicología (donde ya hay uno)
        $responseFallo = $this->put("/admin/users/{$spec2->id}", [
            'name' => 'Spec2',
            'email' => 'spec2@test.com',
            'role_id' => $roleCoordinator->id,
            'area_id' => $this->areaPsico->id,
            'especialidad' => 'Psicología',
            'numero_registro' => '666'
        ]);

        $responseFallo->assertStatus(302);
        $responseFallo->assertSessionHasErrors(['area_id']);
    }
}
