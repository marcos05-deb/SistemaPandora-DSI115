<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Models\Area;
use App\Models\ContactoPaciente;
use App\Models\Especialista;
use App\Models\Paciente;
use App\Models\Role;
use PHPUnit\Framework\Attributes\Group;

#[Group('owasp-v11')]
class ValidacionFechasTest extends ComplianceTestCase
{
    private Especialista $referente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\App\Http\Middleware\RequirePasswordChange::class]);

        $this->app->instance(\App\Services\Crypto\EncryptionContextService::class, new class extends \App\Services\Crypto\EncryptionContextService
        {
            public function getKey(): string
            {
                return str_repeat('C', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
            }
        });

        Role::firstOrCreate(['slug' => 'psychosocial_referent'], ['nombre' => 'Referente Psicosocial', 'nivel' => 20]);
        Role::firstOrCreate(['slug' => 'sysadmin'], ['nombre' => 'Sysadmin', 'nivel' => 100]);

        $area = Area::factory()->create(['nombre' => 'Psicología']);
        $this->referente = Especialista::factory()
            ->withProfesional($area)
            ->withRole('psychosocial_referent')
            ->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadRegistro(array $overrides = []): array
    {
        $base = Paciente::factory()->make()->toArray();

        return array_merge($base, [
            'fecha_nacimiento' => '2000-05-10',
            'fecha_primera_consulta' => null,
            'responsable_parentesco' => 'Otro',
            'responsable_nombre' => 'Responsable Prueba',
            'responsable_telefono' => '77777777',
            'responsable_direccion' => 'Direccion responsable',
        ], $overrides);
    }

    public function test_registro_rechaza_fecha_de_nacimiento_futura(): void
    {
        $this->actingAs($this->referente);

        $this->post('/pacientes', $this->payloadRegistro([
            'fecha_nacimiento' => now()->addDay()->toDateString(),
        ]))->assertSessionHasErrors('fecha_nacimiento');
    }

    public function test_registro_rechaza_fecha_de_nacimiento_anterior_a_1900(): void
    {
        $this->actingAs($this->referente);

        $this->post('/pacientes', $this->payloadRegistro([
            'fecha_nacimiento' => '1899-12-31',
        ]))->assertSessionHasErrors('fecha_nacimiento');
    }

    public function test_registro_rechaza_primera_consulta_futura(): void
    {
        $this->actingAs($this->referente);

        $this->post('/pacientes', $this->payloadRegistro([
            'fecha_primera_consulta' => now()->addDay()->toDateString(),
        ]))->assertSessionHasErrors('fecha_primera_consulta');
    }

    public function test_registro_rechaza_primera_consulta_anterior_al_nacimiento(): void
    {
        $this->actingAs($this->referente);

        $this->post('/pacientes', $this->payloadRegistro([
            'fecha_nacimiento' => '2000-05-10',
            'fecha_primera_consulta' => '2000-05-09',
        ]))->assertSessionHasErrors('fecha_primera_consulta');
    }

    public function test_registro_acepta_fechas_iguales_a_hoy(): void
    {
        $this->actingAs($this->referente);
        $hoy = now()->toDateString();

        $this->post('/pacientes', $this->payloadRegistro([
            'carnet' => 'FH00001',
            'fecha_nacimiento' => $hoy,
            'fecha_primera_consulta' => $hoy,
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pacientes', ['carnet' => 'FH00001']);
    }

    public function test_correccion_rechaza_nacimiento_futuro_o_posterior_a_primera_consulta(): void
    {
        $paciente = Paciente::factory()->create([
            'creado_por_profesional_id' => $this->referente->profesional->id,
            'carnet' => 'FH00002',
            'fecha_nacimiento' => '2000-01-01',
            'fecha_primera_consulta' => '2020-01-01',
        ]);
        ContactoPaciente::create([
            'paciente_id' => $paciente->codigo,
            'nombre_completo' => 'Padre',
            'parentesco' => 'Padre',
            'telefono_personal' => '70000001',
            'direccion' => 'Dir',
            'es_responsable' => true,
        ]);

        $payload = [
            'carnet' => $paciente->carnet,
            'nombre_completo' => $paciente->nombre_completo,
            'direccion' => $paciente->direccion,
            'carrera_id' => $paciente->carrera_id,
            'sexo' => $paciente->sexo,
            'estado_civil' => $paciente->estado_civil,
            'padre_nombre' => 'Padre',
            'padre_telefono' => '70000001',
            'responsable_parentesco' => 'Padre',
            'responsable_telefono' => '70000001',
            'responsable_direccion' => 'Dir',
            'motivo_correccion' => 'Corrección de fecha de nacimiento por error de digitación.',
        ];

        $this->actingAs($this->referente);

        $this->patch('/pacientes/'.$paciente->codigo, array_merge($payload, [
            'fecha_nacimiento' => now()->addDay()->toDateString(),
        ]))->assertSessionHasErrors('fecha_nacimiento');

        $this->patch('/pacientes/'.$paciente->codigo, array_merge($payload, [
            'fecha_nacimiento' => '2021-01-01',
        ]))->assertSessionHasErrors('fecha_nacimiento');

        $paciente->refresh();
        $this->assertSame('2000-01-01', substr((string) $paciente->fecha_nacimiento, 0, 10));
    }

    public function test_filtro_admin_rechaza_fechas_futuras(): void
    {
        $admin = Especialista::factory()->withRole('sysadmin')->create();
        $this->actingAs($admin);

        $this->get('/admin/pacientes?fecha_desde='.now()->addDay()->toDateString())
            ->assertSessionHasErrors('fecha_desde');

        $this->get('/admin/pacientes?fecha_desde='.now()->subDays(3)->toDateString())
            ->assertOk();
    }
}
