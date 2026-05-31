<?php

namespace Tests\Feature\HU03;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Especialista;
use App\Models\Role;
use App\Models\Area;
use App\Models\Expediente;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AreaScopeTest extends TestCase
{
    use RefreshDatabase;

    private $areaPsico;
    private $areaMed;
    private $sysadmin;
    private $especialistaPsico;
    private $especialistaMulti;
    private $expPsico;
    private $expMed;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Crear áreas
        $this->areaPsico = Area::forceCreate(['id' => 1, 'nombre' => 'Psicología', 'requiere_aprobacion_estricta' => false]);
        $this->areaMed = Area::forceCreate(['id' => 2, 'nombre' => 'Medicina', 'requiere_aprobacion_estricta' => false]);

        // 2. Crear roles
        $roleSysadmin = Role::forceCreate(['nombre' => 'Administrador', 'slug' => 'sysadmin', 'nivel' => 100]);
        $roleEspecialista = Role::forceCreate(['nombre' => 'Especialista', 'slug' => 'specialist', 'nivel' => 10]);

        // 3. Crear Especialistas
        $this->sysadmin = Especialista::forceCreate([
            'email' => 'admin@test.com',
            'password' => 'secret',
        ]);
        $this->sysadmin->roles()->attach($roleSysadmin);

        $this->especialistaPsico = Especialista::forceCreate([
            'email' => 'psico@test.com',
            'password' => 'secret',
        ]);
        $this->especialistaPsico->roles()->attach($roleEspecialista);
        DB::table('profesionales')->insert([
            'id' => Str::uuid(),
            'user_id' => $this->especialistaPsico->id,
            'area_id' => $this->areaPsico->id,
            'especialidad' => 'Psicología',
            'numero_registro' => '123'
        ]);

        $this->especialistaMulti = Especialista::forceCreate([
            'email' => 'multi@test.com',
            'password' => 'secret',
        ]);
        $this->especialistaMulti->roles()->attach($roleEspecialista);
        DB::table('profesionales')->insert([
            'id' => Str::uuid(),
            'user_id' => $this->especialistaMulti->id,
            'area_id' => $this->areaPsico->id,
            'especialidad' => 'Psicología',
            'numero_registro' => '124'
        ]);
        DB::table('profesionales')->insert([
            'id' => Str::uuid(),
            'user_id' => $this->especialistaMulti->id,
            'area_id' => $this->areaMed->id,
            'especialidad' => 'Medicina',
            'numero_registro' => '125'
        ]);

        // Desactivamos restricciones temporales
        DB::statement('SET session_replication_role = replica;');

        $pacienteId = Str::uuid();
        DB::table('pacientes')->insert([
            'codigo' => $pacienteId,
            'carnet' => 'TEST-01',
            'carrera_id' => 1,
            'creado_por_profesional_id' => Str::uuid(),
            'sexo' => 'M',
            'estado_civil' => 'Soltero',
            'fecha_nacimiento' => '1990-01-01'
        ]);

        $this->expPsico = Expediente::forceCreate([
            'paciente_id' => $pacienteId,
            'area_id' => $this->areaPsico->id,
        ]);

        $this->expMed = Expediente::forceCreate([
            'paciente_id' => $pacienteId,
            'area_id' => $this->areaMed->id,
        ]);

        DB::statement('SET session_replication_role = DEFAULT;');

        // Configurar una ruta de prueba con el middleware
        Route::middleware(['web', 'enforce_area_scope'])->group(function () {
            Route::get('/test-expedientes/{expediente}', function (Expediente $expediente) {
                return response()->json(['id' => $expediente->id]);
            });
        });
    }

    public function test_sysadmin_cannot_access_clinical_routes()
    {
        $this->actingAs($this->sysadmin)
             ->get('/test-expedientes/' . $this->expPsico->id)
             ->assertStatus(403);
    }

    public function test_specialist_can_access_own_area_expediente()
    {
        $this->actingAs($this->especialistaPsico)
             ->get('/test-expedientes/' . $this->expPsico->id)
             ->assertStatus(200)
             ->assertJson(['id' => $this->expPsico->id]);
    }

    public function test_specialist_cannot_access_other_area_expediente()
    {
        $this->actingAs($this->especialistaPsico)
             ->get('/test-expedientes/' . $this->expMed->id)
             ->assertStatus(404);
    }

    public function test_multi_area_specialist_can_access_both()
    {
        $this->actingAs($this->especialistaMulti);

        $this->get('/test-expedientes/' . $this->expPsico->id)->assertStatus(200);
        $this->get('/test-expedientes/' . $this->expMed->id)->assertStatus(200);
    }

    public function test_global_scope_filters_queries()
    {
        $this->actingAs($this->especialistaPsico);

        $expedientes = Expediente::all();
        $this->assertCount(1, $expedientes);
        $this->assertEquals($this->areaPsico->id, $expedientes->first()->area_id);
    }
}
