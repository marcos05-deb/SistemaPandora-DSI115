<?php

namespace Tests\Feature\Compliance;

use App\Models\Area;
use App\Models\Especialista;
use App\Models\Paciente;
use App\Models\ContactoPaciente;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Support\Str;

#[Group('owasp-v11')]
class BusinessLogicTest extends ComplianceTestCase
{
    private $referent;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->app->instance(\App\Services\Crypto\EncryptionContextService::class, new class extends \App\Services\Crypto\EncryptionContextService {
            public function getKey(): string {
                return str_repeat('B', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
            }
        });

        $area = Area::factory()->create();
        $this->referent = Especialista::factory()->withProfesional($area)->withRole('psychosocial_referent')->create();
    }

    public function test_atomicidad_de_registro_de_pacientes()
    {
        $this->actingAs($this->referent);

        $initialCount = Paciente::count();

        // 1. Generamos un payload estrictamente válido que pasa el FormRequest
        $pacienteData = Paciente::factory()->make()->toArray();
        $payload = array_merge($pacienteData, [
            'padre_nombre' => 'Padre Falso',
            'padre_telefono' => '11111111',
            'responsable_parentesco' => 'Padre',
            'responsable_telefono' => '11111111',
            'responsable_direccion' => 'Dir Falsa',
        ]);

        // 2. Mockeamos un evento de Eloquent para provocar una falla DESPUÉS de que el paciente
        // principal haya sido insertado, pero ANTES de que termine el DB::transaction
        ContactoPaciente::saving(function () {
            throw new \Exception('Falla simulada de base de datos durante transacción');
        });

        // 3. Ejecutamos el request
        try {
            $response = $this->post('/pacientes', $payload);
            if ($response->status() === 302 && $response->headers->get('Location') !== route('dashboard')) {
                dump(session('errors')->getBag('default')->getMessages());
            }
            $response->assertStatus(500); // Dado que arrojamos un \Exception explícito sin handle, retornará 500
        } catch (\Exception $e) {
            $this->assertEquals('Falla simulada de base de datos durante transacción', $e->getMessage());
        }

        // 4. Criterio de Éxito: El rollback activo eliminó el paciente parcialmente creado.
        $finalCount = Paciente::count();
        $this->assertEquals($initialCount, $finalCount, 'La transacción no se revirtió. Quedó un paciente huérfano.');
        
        $this->assertDatabaseMissing('pacientes', [
            'carnet' => $payload['carnet']
        ]);
    }

    public function test_indice_ciego_generacion_y_persistencia()
    {
        $this->actingAs($this->referent);

        $pacienteData = Paciente::factory()->make(['carnet' => 'BC99999'])->toArray();
        $payload = array_merge($pacienteData, [
            'responsable_parentesco' => 'Otro',
            'responsable_nombre' => 'Test',
            'responsable_telefono' => '88888888',
            'responsable_direccion' => 'N/A',
        ]);

        $response = $this->post('/pacientes', $payload);
        if ($response->status() === 302 && $response->headers->get('Location') !== route('dashboard')) {
            dump(session('errors')->getBag('default')->getMessages());
        }
        $response->assertStatus(302); // Redirección de éxito
        
        $this->assertDatabaseHas('pacientes', ['carnet' => 'BC99999']);
    }

    public function test_indice_ciego_aislamiento_criptografico()
    {
        $this->actingAs($this->referent);

        $paciente = Paciente::factory()->create([
            'carnet' => 'ZZ11111',
            'nombre_completo' => 'Nombre Sensible',
            'creado_por_profesional_id' => $this->referent->profesional->id,
        ]);

        // Asegurarnos de que el carnet no está encriptado (Blind Index exacto) pero el nombre sí
        $rawData = DB::selectOne('SELECT carnet, nombre_completo FROM pacientes WHERE codigo = ?', [$paciente->codigo]);
        
        $this->assertEquals('ZZ11111', $rawData->carnet);
        $this->assertNotEquals('Nombre Sensible', $rawData->nombre_completo);
    }

    public function test_indice_ciego_lectura_desde_endpoint()
    {
        $this->actingAs($this->referent);

        Paciente::factory()->create([
            'carnet' => 'XY22222',
            'creado_por_profesional_id' => $this->referent->profesional->id,
        ]);

        $response = $this->get('/pacientes?carnet=XY22222');
        $response->assertStatus(200);

        // Validar estructura de Inertia
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('results.carnet', 'XY22222')
            ->where('results.found', true)
        );
    }
}
