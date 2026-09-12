<?php

namespace Tests\Feature\Compliance;

use App\Models\Area;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Role;
use App\Services\ClinicalAccessAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use OwenIt\Auditing\Models\Audit;
use PHPUnit\Framework\Attributes\Group;

#[Group('hipaa')]
class AuditLoggingTest extends ComplianceTestCase
{
    private Especialista $especialista;

    private Paciente $paciente;

    private Expediente $expediente;

    protected function setUp(): void
    {
        parent::setUp();

        $area = Area::factory()->create();
        $role = Role::firstOrCreate(
            ['slug' => 'specialist'],
            ['nombre' => 'Especialista', 'is_active' => true]
        );

        $this->especialista = Especialista::factory()->create([
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->especialista->roles()->attach($role->id);
        Profesional::factory()->create([
            'user_id' => $this->especialista->id,
            'area_id' => $area->id,
        ]);

        $this->paciente = Paciente::factory()->create();
        $this->expediente = Expediente::factory()->create([
            'paciente_id' => $this->paciente->codigo,
            'area_id' => $area->id,
            'estado' => 'abierto',
            'motivo_consulta' => 'Motivo clínico confidencial de auditoría',
        ]);
    }

    public function test_registro_de_auditoria_por_cada_acceso_a_phi(): void
    {
        $this->actingAs($this->especialista)
            ->withSession(['_sym_key' => session('_sym_key')])
            ->get(route('pacientes.show', $this->paciente->carnet))
            ->assertOk();

        $access = Audit::query()
            ->where('event', 'accessed')
            ->where('tags', 'clinical_access')
            ->where('user_id', $this->especialista->id)
            ->get();

        $this->assertGreaterThanOrEqual(1, $access->count());
        $this->assertTrue(
            $access->contains(fn (Audit $a) => $a->auditable_type === Paciente::class
                && (string) $a->auditable_id === (string) $this->paciente->codigo)
        );
    }

    public function test_inmutabilidad_de_los_registros_de_auditoria(): void
    {
        $audit = app(ClinicalAccessAuditor::class)
            ->record($this->especialista, $this->expediente, 'view_expediente');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Los registros de auditoría son inmutables.');
        $audit->update(['tags' => 'tampered']);
    }

    public function test_no_se_pueden_eliminar_registros_de_auditoria(): void
    {
        $audit = app(ClinicalAccessAuditor::class)
            ->record($this->especialista, $this->expediente, 'view_expediente');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Los registros de auditoría son inmutables.');
        $audit->delete();
    }

    public function test_actualizacion_de_expediente_conserva_autor_motivo_y_valores_enmascarados(): void
    {
        $this->actingAs($this->especialista)
            ->withSession(['_sym_key' => session('_sym_key')])
            ->patch(route('expedientes.update', $this->expediente->id), [
                'motivo_consulta' => 'Motivo actualizado confidencial',
                'motivo_cambio' => 'Corrección clínica documentada para auditoría.',
            ])
            ->assertRedirect();

        $audit = $this->expediente->audits()->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertEquals($this->especialista->id, $audit->user_id);
        $this->assertEquals(
            'Corrección clínica documentada para auditoría.',
            $audit->new_values['motivo_cambio'] ?? null
        );
        $this->assertArrayHasKey('motivo_consulta', $audit->old_values ?? []);
        $this->assertEquals('[CIFRADO]', $audit->new_values['motivo_consulta'] ?? null);

        $raw = DB::table('audits')->where('id', $audit->id)->value('new_values');
        $this->assertStringNotContainsString('Motivo actualizado confidencial', (string) $raw);
    }
}
