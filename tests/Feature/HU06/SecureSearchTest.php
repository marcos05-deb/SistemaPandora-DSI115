<?php

namespace Tests\Feature\HU06;

use App\Models\Area;
use App\Models\Especialista;
use App\Models\Paciente;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecureSearchTest extends TestCase
{
    use RefreshDatabase;

    private Area $areaPsico;

    private Area $areaMed;

    private Especialista $especialistaPsico;

    private Especialista $especialistaMed;

    private Especialista $referent;

    private Paciente $pacienteEnPsico;

    private Paciente $pacienteEnMed;

    protected function setUp(): void
    {
        parent::setUp();

        // Match the authenticated session shape used by LoginController and the real field casts.
        $this->withSession(['_sym_key' => base64_encode(str_repeat('s', SODIUM_CRYPTO_SECRETBOX_KEYBYTES))]);

        // Areas
        $this->areaPsico = Area::forceCreate(['id' => 1, 'nombre' => 'Psicologia', 'requiere_aprobacion_estricta' => false]);
        $this->areaMed = Area::forceCreate(['id' => 2, 'nombre' => 'Medicina', 'requiere_aprobacion_estricta' => false]);

        // Roles
        $roleEspecialista = Role::forceCreate(['nombre' => 'Especialista', 'slug' => 'specialist', 'nivel' => 10]);
        $roleReferent = Role::forceCreate(['nombre' => 'Referente Psicosocial', 'slug' => 'psychosocial_referent', 'nivel' => 20]);

        // Facultad primero, luego carrera (FK order)
        DB::table('facultades')->insert([
            'id' => 1,
            'nombre' => 'Ciencias Sociales',
        ]);
        DB::table('carreras')->insert([
            'id' => 1,
            'nombre' => 'Psicologia',
            'facultad_id' => 1,
        ]);

        // Especialista Psico
        $this->especialistaPsico = Especialista::forceCreate([
            'name' => 'Especialista Psico',
            'email' => 'psico@test.com',
            'password' => 'secret',
        ]);
        $this->especialistaPsico->roles()->attach($roleEspecialista);
        DB::table('profesionales')->insert([
            'id' => Str::uuid(),
            'user_id' => $this->especialistaPsico->id,
            'area_id' => $this->areaPsico->id,
            'especialidad' => 'Psicologia',
            'numero_registro' => 'PSI-001',
        ]);

        // Especialista Med
        $this->especialistaMed = Especialista::forceCreate([
            'name' => 'Especialista Med',
            'email' => 'med@test.com',
            'password' => 'secret',
        ]);
        $this->especialistaMed->roles()->attach($roleEspecialista);
        DB::table('profesionales')->insert([
            'id' => Str::uuid(),
            'user_id' => $this->especialistaMed->id,
            'area_id' => $this->areaMed->id,
            'especialidad' => 'Medicina',
            'numero_registro' => 'MED-001',
        ]);

        // Referente
        $this->referent = Especialista::forceCreate([
            'name' => 'Referente',
            'email' => 'ref@test.com',
            'password' => 'secret',
        ]);
        $this->referent->roles()->attach($roleReferent);
        $referentProfId = Str::uuid();
        DB::table('profesionales')->insert([
            'id' => $referentProfId,
            'user_id' => $this->referent->id,
            'area_id' => $this->areaPsico->id,
            'especialidad' => 'Trabajo Social',
            'numero_registro' => 'TS-001',
        ]);

        $uuidPsico = (string) Str::uuid();
        $uuidMed = (string) Str::uuid();

        $this->pacienteEnPsico = Paciente::forceCreate([
            'codigo' => $uuidPsico,
            'carnet' => 'AB12345',
            'carrera_id' => 1,
            'creado_por_profesional_id' => $referentProfId,
            'sexo' => 'M',
            'estado_civil' => 'Soltero',
            'fecha_nacimiento' => '2000-01-01',
        ]);

        $this->pacienteEnMed = Paciente::forceCreate([
            'codigo' => $uuidMed,
            'carnet' => 'XY67890',
            'carrera_id' => 1,
            'creado_por_profesional_id' => $referentProfId,
            'sexo' => 'F',
            'estado_civil' => 'Soltero',
            'fecha_nacimiento' => '2001-01-01',
        ]);

        DB::table('expedientes')->insert([
            'id' => Str::uuid(),
            'paciente_id' => $uuidPsico,
            'area_id' => $this->areaPsico->id,
        ]);

        DB::table('expedientes')->insert([
            'id' => Str::uuid(),
            'paciente_id' => $uuidMed,
            'area_id' => $this->areaMed->id,
        ]);

    }

    protected function migrateFreshUsing()
    {
        return [
            '--drop-views' => true,
            '--drop-types' => true,
            '--database' => 'pgsql_admin',
        ];
    }

    public function test_secure_search_page_renders_for_specialist(): void
    {
        $this->actingAs($this->especialistaPsico)
            ->get('/busqueda-segura')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('SecureSearch/Index'));
    }

    public function test_specialist_finds_patient_in_own_area_by_uuid(): void
    {
        $this->actingAs($this->especialistaPsico)
            ->post('/busqueda-segura', [
                'code' => $this->pacienteEnPsico->codigo,
            ])
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->where('results.found', true)
                ->where('results.code', $this->pacienteEnPsico->codigo)
            );
    }

    public function test_specialist_cannot_find_patient_outside_area(): void
    {
        $this->actingAs($this->especialistaPsico)
            ->post('/busqueda-segura', [
                'code' => $this->pacienteEnMed->codigo,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('code');
    }

    public function test_specialist_cannot_find_nonexistent_uuid(): void
    {
        $uuid = Str::uuid();

        $this->actingAs($this->especialistaPsico)
            ->post('/busqueda-segura', [
                'code' => $uuid,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('code');
    }

    public function test_invalid_uuid_format_returns_validation_error(): void
    {
        $this->actingAs($this->especialistaPsico)
            ->post('/busqueda-segura', [
                'code' => 'not-a-valid-uuid',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('code');
    }

    public function test_specialist_can_view_patient_details_in_own_area(): void
    {
        expect(DB::table('pacientes')->where('codigo', $this->pacienteEnPsico->codigo)->value('fecha_nacimiento'))
            ->not->toBe('2000-01-01');

        $this->actingAs($this->especialistaPsico)
            ->get('/pacientes/'.$this->pacienteEnPsico->carnet)
            ->assertStatus(200);
    }

    public function test_specialist_cannot_view_patient_details_outside_area(): void
    {
        $this->actingAs($this->especialistaPsico)
            ->get('/pacientes/'.$this->pacienteEnMed->carnet)
            ->assertStatus(403);
    }

    public function test_referent_can_find_patients_by_uuid(): void
    {
        DB::table('pacientes')->where('carnet', 'AB12345')->update([
            'creado_por_profesional_id' => DB::table('profesionales')->where('user_id', $this->referent->id)->value('id'),
        ]);

        $this->actingAs($this->referent)
            ->post('/busqueda-segura', [
                'code' => $this->pacienteEnPsico->codigo,
            ])
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->where('results.found', true)
            );
    }
}
