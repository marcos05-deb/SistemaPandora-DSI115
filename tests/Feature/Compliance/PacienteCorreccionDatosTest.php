<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Models\Area;
use App\Models\ContactoPaciente;
use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\PacienteCorreccionAuditoria;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;

#[Group('hipaa')]
#[Group('owasp-v4')]
class PacienteCorreccionDatosTest extends ComplianceTestCase
{
    private Area $area;

    private Especialista $referenteCreador;

    private Paciente $paciente;

    private string $motivo = 'Corrección solicitada porque el carnet fue digitado incorrectamente durante el registro inicial.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([\App\Http\Middleware\RequirePasswordChange::class]);

        $this->app->instance(\App\Services\Crypto\EncryptionContextService::class, new class extends \App\Services\Crypto\EncryptionContextService
        {
            public function getKey(): string
            {
                return str_repeat('A', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
            }
        });

        $this->area = Area::factory()->create(['nombre' => 'Psicología']);

        Role::firstOrCreate(['slug' => 'psychosocial_referent'], ['nombre' => 'Referente Psicosocial', 'nivel' => 20]);
        Role::firstOrCreate(['slug' => 'specialist'], ['nombre' => 'Especialista', 'nivel' => 30]);
        Role::firstOrCreate(['slug' => 'area_coordinator'], ['nombre' => 'Coordinador', 'nivel' => 40]);
        Role::firstOrCreate(['slug' => 'sysadmin'], ['nombre' => 'Sysadmin', 'nivel' => 100]);

        $this->referenteCreador = Especialista::factory()
            ->withProfesional($this->area)
            ->withRole('psychosocial_referent')
            ->create();

        $this->paciente = Paciente::factory()->create([
            'creado_por_profesional_id' => $this->referenteCreador->profesional->id,
            'carnet' => 'QA26010',
            'nombre_completo' => 'Paciente Original',
            'direccion' => 'Direccion original',
            'sexo' => 'M',
            'estado_civil' => 'Soltero',
            'profesion_ocupacion' => 'Estudiante',
        ]);

        ContactoPaciente::create([
            'paciente_id' => $this->paciente->codigo,
            'nombre_completo' => 'Padre Original',
            'parentesco' => 'Padre',
            'telefono_personal' => '70000001',
            'direccion' => 'Dir padre',
            'es_responsable' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'carnet' => $this->paciente->carnet,
            'nombre_completo' => $this->paciente->nombre_completo,
            'direccion' => $this->paciente->direccion,
            'carrera_id' => $this->paciente->carrera_id,
            'sexo' => $this->paciente->sexo,
            'estado_civil' => $this->paciente->estado_civil,
            'fecha_nacimiento' => substr((string) $this->paciente->fecha_nacimiento, 0, 10),
            'profesion_ocupacion' => $this->paciente->profesion_ocupacion,
            'referido_por' => $this->paciente->referido_por,
            'llevado_por' => $this->paciente->llevado_por,
            'padre_nombre' => 'Padre Original',
            'padre_telefono' => '70000001',
            'madre_nombre' => null,
            'madre_telefono' => null,
            'responsable_parentesco' => 'Padre',
            'responsable_nombre' => null,
            'responsable_telefono' => '70000001',
            'responsable_direccion' => 'Dir padre',
            'motivo_correccion' => $this->motivo,
        ], $overrides);
    }

    public function test_referente_creador_puede_editar_datos_generales(): void
    {
        $this->actingAs($this->referenteCreador);

        $response = $this->patch('/pacientes/'.$this->paciente->codigo, $this->payload([
            'nombre_completo' => 'Paciente Corregido',
            'direccion' => 'Nueva direccion',
        ]));

        $response->assertRedirect(route('pacientes.show', $this->paciente->carnet));

        $this->paciente->refresh();
        $this->assertSame('Paciente Corregido', $this->paciente->nombre_completo);
        $this->assertSame('Corrección de datos generales', $this->paciente->ultima_accion);

        $this->assertDatabaseHas('paciente_correcciones_auditoria', [
            'paciente_id' => $this->paciente->codigo,
            'tipo_evento' => PacienteCorreccionAuditoria::TIPO_ACTUALIZACION,
            'nivel_evento' => PacienteCorreccionAuditoria::NIVEL_NORMAL,
            'usuario_id' => $this->referenteCreador->id,
        ]);
    }

    public function test_otro_referente_recibe_403(): void
    {
        $otro = Especialista::factory()->withProfesional($this->area)->withRole('psychosocial_referent')->create();
        $this->actingAs($otro);

        $this->patch('/pacientes/'.$this->paciente->codigo, $this->payload([
            'nombre_completo' => 'Hack',
        ]))->assertStatus(403)->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 403)
        );
    }

    public function test_especialista_recibe_403(): void
    {
        $esp = Especialista::factory()->withProfesional($this->area)->withRole('specialist')->create();
        $this->actingAs($esp);

        $this->patch('/pacientes/'.$this->paciente->codigo, $this->payload([
            'nombre_completo' => 'Hack',
        ]))->assertStatus(403);
    }

    public function test_coordinador_recibe_403(): void
    {
        $coord = Especialista::factory()->withProfesional($this->area)->withRole('area_coordinator')->create();
        $this->actingAs($coord);

        $this->patch('/pacientes/'.$this->paciente->codigo, $this->payload([
            'nombre_completo' => 'Hack',
        ]))->assertStatus(403);
    }

    public function test_administrador_recibe_403_al_intentar_editar(): void
    {
        $admin = Especialista::factory()->withRole('sysadmin')->create();
        $this->actingAs($admin);

        $this->patch('/pacientes/'.$this->paciente->codigo, $this->payload([
            'nombre_completo' => 'Hack',
        ]))->assertStatus(403);
    }

    public function test_carnet_duplicado_es_rechazado(): void
    {
        Paciente::factory()->create([
            'creado_por_profesional_id' => $this->referenteCreador->profesional->id,
            'carnet' => 'ZZ99999',
        ]);

        $this->actingAs($this->referenteCreador);

        $this->patch('/pacientes/'.$this->paciente->codigo, $this->payload([
            'carnet' => 'ZZ99999',
        ]))->assertSessionHasErrors('carnet');
    }

    public function test_formato_invalido_de_carnet_es_rechazado(): void
    {
        $this->actingAs($this->referenteCreador);

        $this->patch('/pacientes/'.$this->paciente->codigo, $this->payload([
            'carnet' => 'ABC123',
            'nombre_completo' => 'Cambio con carnet invalido',
        ]))->assertSessionHasErrors('carnet');
    }

    public function test_motivo_vacio_o_corto_es_rechazado(): void
    {
        $this->actingAs($this->referenteCreador);

        $this->patch('/pacientes/'.$this->paciente->codigo, $this->payload([
            'nombre_completo' => 'Otro nombre',
            'motivo_correccion' => 'corto',
        ]))->assertSessionHasErrors('motivo_correccion');
    }

    public function test_uuid_no_cambia_y_relaciones_se_conservan(): void
    {
        $expediente = Expediente::factory()->create([
            'paciente_id' => $this->paciente->codigo,
            'area_id' => $this->area->id,
            'estado' => 'en_atencion',
        ]);
        $uuid = $this->paciente->codigo;
        $contactoId = $this->paciente->contactos()->first()->id;

        $this->actingAs($this->referenteCreador);

        $this->patch('/pacientes/'.$uuid, $this->payload([
            'carnet' => 'QA26099',
            'nombre_completo' => 'Paciente Con Nuevo Carnet',
        ]))->assertRedirect(route('pacientes.show', 'QA26099'));

        $this->paciente->refresh();
        $this->assertSame($uuid, $this->paciente->codigo);
        $this->assertSame('QA26099', $this->paciente->carnet);
        $this->assertDatabaseHas('expedientes', ['id' => $expediente->id, 'paciente_id' => $uuid]);
        $this->assertDatabaseHas('contactos_paciente', ['id' => $contactoId, 'paciente_id' => $uuid]);
        $this->assertSame(1, Expediente::withoutGlobalScopes()->where('paciente_id', $uuid)->count());
    }

    public function test_campos_cifrados_continuan_cifrados_en_bd(): void
    {
        $this->actingAs($this->referenteCreador);

        $this->patch('/pacientes/'.$this->paciente->codigo, $this->payload([
            'nombre_completo' => 'Nombre Secreto Cifrado',
            'direccion' => 'Direccion Secreta',
        ]))->assertRedirect();

        $raw = DB::table('pacientes')->where('codigo', $this->paciente->codigo)->first();
        $this->assertNotSame('Nombre Secreto Cifrado', $raw->nombre_completo);
        $this->assertNotSame('Direccion Secreta', $raw->direccion);
        $this->assertStringNotContainsString('Nombre Secreto', (string) $raw->nombre_completo);
    }

    public function test_auditoria_incluye_autor_motivo_y_campos_cifrados_protegidos(): void
    {
        $this->actingAs($this->referenteCreador);

        $this->patch('/pacientes/'.$this->paciente->codigo, $this->payload([
            'nombre_completo' => 'Nombre Auditado',
        ]));

        $audit = PacienteCorreccionAuditoria::query()->where('paciente_id', $this->paciente->codigo)->first();
        $this->assertNotNull($audit);
        $this->assertSame($this->referenteCreador->id, $audit->usuario_id);
        $this->assertSame($this->motivo, $audit->motivo);
        $this->assertContains('nombre_completo', $audit->campos_modificados);
        $this->assertSame('[CIFRADO]', $audit->valores_nuevos['nombre_completo']);
        $this->assertSame('[CIFRADO]', $audit->valores_anteriores['nombre_completo']);
    }

    public function test_cambio_de_carnet_genera_evento_sensible_y_aviso(): void
    {
        $this->actingAs($this->referenteCreador);

        $this->patch('/pacientes/'.$this->paciente->codigo, $this->payload([
            'carnet' => 'QA26111',
        ]));

        $audit = PacienteCorreccionAuditoria::query()->where('paciente_id', $this->paciente->codigo)->first();
        $this->assertSame(PacienteCorreccionAuditoria::TIPO_CORRECCION_CARNET, $audit->tipo_evento);
        $this->assertSame(PacienteCorreccionAuditoria::NIVEL_SENSIBLE, $audit->nivel_evento);
        $this->assertTrue($audit->aviso_sensible);
        $this->assertFalse($audit->aviso_revisado);
    }

    public function test_admin_puede_consultar_y_marcar_aviso_revisado(): void
    {
        $this->actingAs($this->referenteCreador);
        $this->patch('/pacientes/'.$this->paciente->codigo, $this->payload(['carnet' => 'QA26222']));
        $audit = PacienteCorreccionAuditoria::query()->first();

        $admin = Especialista::factory()->withRole('sysadmin')->create();
        $this->actingAs($admin);

        $this->get('/admin/pacientes')->assertOk();
        $detalle = $this->get('/admin/pacientes/correcciones/'.$audit->id)->assertOk();
        $detalle->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Pacientes/CorreccionShow')
            ->where('correccion.paciente_id', $this->paciente->codigo)
            ->where('correccion.carnet_anterior', '[CIFRADO]')
            ->where('correccion.carnet_nuevo', '[CIFRADO]')
            ->where('correccion.valores_anteriores.carnet', '[CIFRADO]')
            ->where('correccion.valores_nuevos.carnet', '[CIFRADO]')
        );
        $detalle->assertDontSee('QA26010');
        $detalle->assertDontSee('QA26222');

        $this->post('/admin/pacientes/correcciones/'.$audit->id.'/revisar')
            ->assertRedirect();

        $audit->refresh();
        $this->assertTrue($audit->aviso_revisado);
        $this->assertSame($admin->id, $audit->revisado_por_usuario_id);
        $this->assertNotNull($audit->revisado_en);
    }

    public function test_admin_no_puede_modificar_ni_eliminar_auditoria(): void
    {
        $this->actingAs($this->referenteCreador);
        $this->patch('/pacientes/'.$this->paciente->codigo, $this->payload(['nombre_completo' => 'X']));
        $audit = PacienteCorreccionAuditoria::query()->first();

        $this->expectException(\RuntimeException::class);
        $audit->update(['motivo' => 'intento de alteracion']);
    }

    public function test_admin_no_puede_eliminar_auditoria(): void
    {
        $this->actingAs($this->referenteCreador);
        $this->patch('/pacientes/'.$this->paciente->codigo, $this->payload(['nombre_completo' => 'Y']));
        $audit = PacienteCorreccionAuditoria::query()->first();

        $this->expectException(\RuntimeException::class);
        $audit->delete();
    }

    public function test_no_existe_ruta_para_eliminar_pacientes(): void
    {
        $hasDelete = collect(Route::getRoutes())->contains(function ($route) {
            return in_array('DELETE', $route->methods(), true)
                && str_contains($route->uri(), 'pacientes')
                && ! str_contains($route->uri(), 'admin/users');
        });

        $this->assertFalse($hasDelete);

        $this->actingAs($this->referenteCreador);
        $this->delete('/pacientes/'.$this->paciente->codigo)->assertStatus(405);
    }

    public function test_falla_durante_actualizacion_revierte_datos_y_auditoria(): void
    {
        PacienteCorreccionAuditoria::creating(function () {
            throw new \RuntimeException('Falla forzada de auditoría');
        });

        $this->actingAs($this->referenteCreador);
        $nombreAntes = $this->paciente->nombre_completo;

        $this->withoutExceptionHandling();

        try {
            $this->patch('/pacientes/'.$this->paciente->codigo, $this->payload([
                'nombre_completo' => 'No Debe Persistir',
            ]));
            $this->fail('Se esperaba RuntimeException por falla de auditoría');
        } catch (\RuntimeException $e) {
            $this->assertSame('Falla forzada de auditoría', $e->getMessage());
        }

        $this->paciente->refresh();
        $this->assertSame($nombreAntes, $this->paciente->nombre_completo);
        $this->assertSame(0, PacienteCorreccionAuditoria::query()->count());
    }

    public function test_sin_cambios_reales_no_genera_auditoria(): void
    {
        $this->actingAs($this->referenteCreador);

        $this->patch('/pacientes/'.$this->paciente->codigo, $this->payload())
            ->assertSessionHasErrors('motivo_correccion');

        $this->assertSame(0, PacienteCorreccionAuditoria::query()->count());
    }
}
