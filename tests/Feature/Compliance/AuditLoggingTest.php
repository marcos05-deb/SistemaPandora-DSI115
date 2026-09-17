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

    public function test_no_se_pueden_actualizar_auditorias_via_sql_directo(): void
    {
        $audit = app(ClinicalAccessAuditor::class)
            ->record($this->especialista, $this->expediente, 'view_expediente');

        DB::connection('pgsql')->statement('SAVEPOINT audit_update_guard');

        try {
            DB::connection('pgsql')->table('audits')
                ->where('id', $audit->id)
                ->update(['tags' => 'tampered_sql']);
            $this->fail('Debía fallar el UPDATE directo sobre audits');
        } catch (\Illuminate\Database\QueryException $e) {
            DB::connection('pgsql')->statement('ROLLBACK TO SAVEPOINT audit_update_guard');
            $this->assertTrue(
                str_contains(strtolower($e->getMessage()), 'permission')
                || str_contains($e->getMessage(), '42501'),
                'Se esperaba error de permisos al actualizar audits'
            );
        }

        $this->assertSame('clinical_access', $audit->fresh()->tags);
    }

    public function test_no_se_pueden_eliminar_auditorias_via_sql_directo(): void
    {
        $audit = app(ClinicalAccessAuditor::class)
            ->record($this->especialista, $this->expediente, 'view_expediente');

        DB::connection('pgsql')->statement('SAVEPOINT audit_delete_guard');

        try {
            DB::connection('pgsql')->table('audits')
                ->where('id', $audit->id)
                ->delete();
            $this->fail('Debía fallar el DELETE directo sobre audits');
        } catch (\Illuminate\Database\QueryException $e) {
            DB::connection('pgsql')->statement('ROLLBACK TO SAVEPOINT audit_delete_guard');
            $this->assertTrue(
                str_contains(strtolower($e->getMessage()), 'permission')
                || str_contains($e->getMessage(), '42501'),
                'Se esperaba error de permisos al eliminar audits'
            );
        }

        $this->assertDatabaseHas('audits', ['id' => $audit->id]);
    }

    public function test_visualizar_varios_expedientes_audita_cada_acceso(): void
    {
        $segundaArea = Area::factory()->create();
        Profesional::factory()->create([
            'user_id' => $this->especialista->id,
            'area_id' => $segundaArea->id,
        ]);

        $segundo = Expediente::factory()->create([
            'paciente_id' => $this->paciente->codigo,
            'area_id' => $segundaArea->id,
            'estado' => 'abierto',
        ]);

        $this->actingAs($this->especialista)
            ->withSession(['_sym_key' => session('_sym_key')])
            ->get(route('pacientes.show', $this->paciente->carnet))
            ->assertOk();

        $access = Audit::query()
            ->where('event', 'accessed')
            ->where('tags', 'clinical_access')
            ->where('user_id', $this->especialista->id)
            ->where('auditable_type', Expediente::class)
            ->get();

        $this->assertTrue($access->contains(fn (Audit $a) => (string) $a->auditable_id === (string) $this->expediente->id));
        $this->assertTrue($access->contains(fn (Audit $a) => (string) $a->auditable_id === (string) $segundo->id));
    }

    public function test_consultar_historial_genera_evento_de_acceso(): void
    {
        $this->actingAs($this->especialista)
            ->withSession(['_sym_key' => session('_sym_key')])
            ->get(route('pacientes.historial', $this->paciente))
            ->assertOk();

        $this->assertTrue(
            Audit::query()
                ->where('event', 'accessed')
                ->where('tags', 'clinical_access')
                ->where('user_id', $this->especialista->id)
                ->where('auditable_type', Paciente::class)
                ->get()
                ->contains(fn (Audit $a) => ($a->new_values['action'] ?? null) === 'view_historial')
        );
    }

    public function test_solicitud_no_autorizada_no_registra_acceso_como_visto(): void
    {
        $otraArea = Area::factory()->create();
        $otro = Especialista::factory()->create([
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $role = Role::where('slug', 'specialist')->firstOrFail();
        $otro->roles()->attach($role->id);
        Profesional::factory()->create([
            'user_id' => $otro->id,
            'area_id' => $otraArea->id,
        ]);

        $antes = Audit::query()->where('tags', 'clinical_access')->count();

        $this->actingAs($otro)
            ->withSession(['_sym_key' => session('_sym_key')])
            ->get(route('pacientes.show', $this->paciente->carnet))
            ->assertForbidden();

        $this->assertSame($antes, Audit::query()->where('tags', 'clinical_access')->count());
    }
}
