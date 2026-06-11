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
        $this->withoutExceptionHandling();
        try {
            $response = $this->post('/pacientes', $payload);
            $this->fail('Se esperaba una excepción de atomicidad y la ruta retornó exitosamente.');
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

    public function test_mass_assignment_es_ignorado()
    {
        $this->actingAs($this->referent);

        $pacienteData = Paciente::factory()->make(['carnet' => 'MM77777'])->toArray();
        $payload = array_merge($pacienteData, [
            'responsable_parentesco' => 'Otro',
            'responsable_nombre' => 'Resp',
            'responsable_telefono' => '12341234',
            'responsable_direccion' => 'Dir',
            'is_admin' => true, // Intento de mass assignment de variable ficticia o protegida
        ]);

        $response = $this->post('/pacientes', $payload);
        $response->assertStatus(302);

        // Validar que el paciente existe
        $paciente = Paciente::where('carnet', 'MM77777')->first();
        $this->assertNotNull($paciente);
        
        // Atributo protegido inexistente no crashea ni inyecta
        $this->assertFalse(isset($paciente->is_admin));
    }

    public function test_inyeccion_sql_rechazada_por_form_request()
    {
        $this->actingAs($this->referent);

        // Proveemos un carnet válido para evitar que falle por la validación de carnet
        $pacienteData = Paciente::factory()->make(['carnet' => 'XX12345'])->toArray();
        $payload = array_merge($pacienteData, [
            'responsable_parentesco' => 'Otro',
            'responsable_nombre' => "' OR 1=1 --", // Vector SQLi (Aceptado como string por el request, pero blindado por PDO en Eloquent)
            'responsable_telefono' => "1234' OR '1'='1", // Vector SQLi en telefono
            'responsable_direccion' => "1234",
        ]);

        $response = $this->post('/pacientes', $payload);
        
        // Debería ser rechazado por las validaciones (regex de telefono)
        // Retorna 302 con errores en sesión (comportamiento web estándar en Laravel)
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['responsable_telefono']);
    }
}
