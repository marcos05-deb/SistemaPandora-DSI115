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

    private string $resultadoFinal = 'Alta clínica con remisión parcial de síntomas de ansiedad.';

    private string $motivoCierre = 'El paciente ha sido dado de alta exitosamente tras finalizar las sesiones.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\App\Http\Middleware\RequirePasswordChange::class]);
        \config(['audit.console' => true]);

        $area = Area::factory()->create();

        $this->coordinador = Especialista::factory()->create([
            'password' => $this->testPassword,
        ]);
        $this->coordinador->roles()->attach(\App\Models\Role::firstOrCreate(
            ['slug' => 'area_coordinator'],
            ['nombre' => 'Coordinador de Área']
        ));

        Profesional::factory()->create([
            'user_id' => $this->coordinador->id,
            'area_id' => $area->id,
        ]);

        $this->especialista = Especialista::factory()->create([
            'password' => $this->testPassword,
        ]);
        $this->especialista->roles()->attach(\App\Models\Role::firstOrCreate(
            ['slug' => 'specialist'],
            ['nombre' => 'Especialista']
        ));

        Profesional::factory()->create([
            'user_id' => $this->especialista->id,
            'area_id' => $area->id,
        ]);

        session()->put('_sym_key', 'test_key');

        $paciente = Paciente::factory()->create();
        $this->expediente = Expediente::factory()->create([
            'paciente_id' => $paciente->codigo,
            'area_id' => $area->id,
            'estado' => 'abierto',
            'motivo_consulta' => 'Motivo inicial',
        ]);
    }

    public function test_cierre_exitoso_por_especialista_con_resultado_final(): void
    {
        $this->post('/login', [
            'email' => $this->especialista->email,
            'password' => $this->testPassword,
        ]);

        $response = $this->post(route('expedientes.cerrar', $this->expediente->id), [
            'resultado_final' => $this->resultadoFinal,
            'motivo_cierre' => $this->motivoCierre,
            'confirmacion_irreversible' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->expediente->refresh();
        $this->assertEquals('cerrado', $this->expediente->estado);
        $this->assertEquals($this->resultadoFinal, $this->expediente->resultado_final);
        $this->assertEquals($this->motivoCierre, $this->expediente->motivo_cierre);
        $this->assertNotNull($this->expediente->fecha_cierre);
        $this->assertEquals($this->especialista->profesional->id, $this->expediente->cerrado_por_profesional_id);

        $raw = DB::table('expedientes')->where('id', $this->expediente->id)->value('resultado_final');
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('remisión parcial', (string) $raw);

        $auditLog = DB::table('audits')
            ->where('auditable_type', Expediente::class)
            ->where('auditable_id', $this->expediente->id)
            ->where('event', 'updated')
            ->latest()
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertStringContainsString('cerrado', $auditLog->new_values);
    }

    public function test_cierre_exitoso_por_coordinador_de_area(): void
    {
        $this->post('/login', [
            'email' => $this->coordinador->email,
            'password' => $this->testPassword,
        ]);

        $response = $this->post(route('expedientes.cerrar', $this->expediente->id), [
            'resultado_final' => $this->resultadoFinal,
            'motivo_cierre' => $this->motivoCierre,
            'confirmacion_irreversible' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertEquals('cerrado', $this->expediente->fresh()->estado);
    }

    public function test_rechazo_sin_resultado_final(): void
    {
        $this->post('/login', [
            'email' => $this->especialista->email,
            'password' => $this->testPassword,
        ]);

        $response = $this->post(route('expedientes.cerrar', $this->expediente->id), [
            'motivo_cierre' => $this->motivoCierre,
            'confirmacion_irreversible' => true,
        ]);

        $response->assertSessionHasErrors('resultado_final');
        $this->assertEquals('abierto', $this->expediente->fresh()->estado);
    }

    public function test_rechazo_por_doble_cierre_retorna_422(): void
    {
        $this->expediente->update(['estado' => 'cerrado']);

        $this->post('/login', [
            'email' => $this->coordinador->email,
            'password' => $this->testPassword,
        ]);

        $response = $this->post(route('expedientes.cerrar', $this->expediente->id), [
            'resultado_final' => $this->resultadoFinal,
            'motivo_cierre' => 'Intento de doble cierre',
            'confirmacion_irreversible' => true,
        ]);

        $response->assertStatus(422);
    }
}
