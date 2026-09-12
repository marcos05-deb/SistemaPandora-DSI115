<?php

declare(strict_types=1);

namespace Tests\Feature\Expediente;

use App\Models\Area;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CerrarExpedienteTest extends TestCase
{
    private Especialista $coordinador;
    private Especialista $especialista;
    private Expediente $expediente;
    private string $testPassword = 'Password123!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\App\Http\Middleware\RequirePasswordChange::class]);
        \config(['audit.console' => true]);

        // 1. Create Area
        $area = Area::factory()->create();

        // 2. Create Coordinador
        $this->coordinador = Especialista::factory()->create([
            'password' => $this->testPassword,
        ]);
        $this->coordinador->roles()->attach(\App\Models\Role::firstOrCreate(['slug' => 'area_coordinator'], ['nombre' => 'Coordinador de Área']));
        
        $profesionalCoordinador = Profesional::factory()->create([
            'user_id' => $this->coordinador->id,
            'area_id' => $area->id,
        ]);
        $this->coordinador->setRelation('profesional', $profesionalCoordinador);

        // 3. Create Especialista regular
        $this->especialista = Especialista::factory()->create([
            'password' => $this->testPassword,
        ]);
        $this->especialista->roles()->attach(\App\Models\Role::firstOrCreate(['slug' => 'specialist'], ['nombre' => 'Especialista']));
        
        $profesionalEspecialista = Profesional::factory()->create([
            'user_id' => $this->especialista->id,
            'area_id' => $area->id,
        ]);
        $this->especialista->setRelation('profesional', $profesionalEspecialista);

        // 4. Create Paciente & Expediente (Open)
        session()->put('_sym_key', 'test_key');
        
        $paciente = Paciente::factory()->create();
        $this->expediente = Expediente::factory()->create([
            'paciente_id' => $paciente->codigo,
            'area_id' => $area->id,
            'estado' => 'abierto',
            'motivo_consulta' => 'Motivo inicial',
        ]);
    }

    public function test_cierre_exitoso_por_coordinador_de_area_registra_en_audit_log()
    {
        // Act: Autenticamos al coordinador mediante la ruta de login para tener sym_key
        $this->post('/login', [
            'email' => $this->coordinador->email,
            'password' => $this->testPassword,
        ]);

        $motivoCierre = 'El paciente ha sido dado de alta exitosamente tras finalizar las sesiones.';

        $response = $this->post(route('expedientes.cerrar', $this->expediente->id), [
            'motivo_cierre' => $motivoCierre,
            'confirmacion_irreversible' => true,
        ]);

        // Assert: Redirección sin errores
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Assert: DB actualizada
        $this->expediente->refresh();
        $this->assertEquals('cerrado', $this->expediente->estado);
        $this->assertEquals($motivoCierre, $this->expediente->motivo_cierre);
        $this->assertNotNull($this->expediente->fecha_cierre);
        $this->assertEquals($this->coordinador->profesional->id, $this->expediente->cerrado_por_profesional_id);

        // Assert: Audit log existe (Auditable trait)
        $auditLog = DB::table('audits')
            ->where('auditable_type', Expediente::class)
            ->where('auditable_id', $this->expediente->id)
            ->where('event', 'updated')
            ->latest()
            ->first();

        $this->assertNotNull($auditLog, 'El evento de cierre no se registró en el audit log.');
        $this->assertStringContainsString('cerrado', $auditLog->new_values);
    }

    public function test_rechazo_por_rol_especialista_intentando_cerrar_retorna_403()
    {
        // Act: Login con especialista regular
        $this->post('/login', [
            'email' => $this->especialista->email,
            'password' => $this->testPassword,
        ]);

        $response = $this->post(route('expedientes.cerrar', $this->expediente->id), [
            'motivo_cierre' => 'Intento de cierre',
            'confirmacion_irreversible' => true,
        ]);

        // Assert: 403 Forbidden
        $response->assertStatus(403);

        // Assert: DB sin cambios
        $this->expediente->refresh();
        $this->assertEquals('abierto', $this->expediente->estado);
    }

    public function test_rechazo_por_doble_cierre_retorna_422()
    {
        // Arrange: El expediente ya está cerrado
        $this->expediente->update(['estado' => 'cerrado']);

        // Act: Login con coordinador
        $this->post('/login', [
            'email' => $this->coordinador->email,
            'password' => $this->testPassword,
        ]);

        $response = $this->post(route('expedientes.cerrar', $this->expediente->id), [
            'motivo_cierre' => 'Intento de doble cierre',
            'confirmacion_irreversible' => true,
        ]);

        // Assert: 422 Unprocessable Entity
        $response->assertStatus(422);
    }
}
