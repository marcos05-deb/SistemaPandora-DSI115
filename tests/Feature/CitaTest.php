<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Cita;
use App\Models\Consulta;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Role;
use App\Models\Especialista;
use App\Models\Profesional;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CitaTest extends TestCase
{
    private $specialist;
    private $otherSpecialist;
    private $expediente;

    protected function setUp(): void
    {
        parent::setUp();
        
        session(['_sym_key' => base64_encode(random_bytes(32))]);

        // Setup base data
        $role = Role::create(['nombre' => 'Specialist', 'slug' => 'specialist', 'descripcion' => 'Especialista']);
        
        $area = Area::create(['nombre' => 'Psicología', 'descripcion' => 'Área de Psicología']);
        $otherArea = Area::create(['nombre' => 'Fisioterapia', 'descripcion' => 'Área de Fisioterapia']);

        // Specialist 1
        $this->specialist = Especialista::factory()->create();
        $this->specialist->roles()->attach($role);
        DB::table('profesionales')->insert(['user_id' => $this->specialist->id, 'area_id' => $area->id, 'especialidad' => 'Psicología Clínica', 'numero_registro' => '12345', 'created_at' => now(), 'updated_at' => now()]);
        $prof1Id = DB::table('profesionales')->where('user_id', $this->specialist->id)->value('id');

        // Specialist 2 (same area)
        $this->otherSpecialist = Especialista::factory()->create();
        $this->otherSpecialist->roles()->attach($role);
        DB::table('profesionales')->insert(['user_id' => $this->otherSpecialist->id, 'area_id' => $area->id, 'especialidad' => 'Psicología Clínica', 'numero_registro' => '67890', 'created_at' => now(), 'updated_at' => now()]);

        // Paciente and Expediente
        $paciente = Paciente::factory()->create(['creado_por_profesional_id' => $prof1Id]);
        $this->expediente = Expediente::create([
            'paciente_id' => $paciente->codigo,
            'area_id' => $area->id,
            'estado' => 'abierto',
            'fecha_derivacion' => now(),
        ]);
    }

    public function test_specialist_can_schedule_cita_in_their_area()
    {
        $consulta = Consulta::create([
            'expediente_id' => $this->expediente->id,
            'profesional_id' => $this->specialist->profesional->id,
            'motivo_consulta' => 'Motivo base',
            'notas_clinicas' => 'Notas base',
            'diagnostico' => 'Dx base',
            'plan_atencion' => 'Plan de atención de prueba',
            'tecnica_utilizada' => 'Entrevista',
            'fecha_consulta' => now(),
        ]);

        $fechaHora = now()->addDays(2)->format('Y-m-d H:i:s');
        
        $response = $this->actingAs($this->specialist)->post("/expedientes/{$this->expediente->id}/citas", [
            'consulta_id' => $consulta->id,
            'fecha_hora' => $fechaHora,
            'motivo' => 'Seguimiento clínico',
            'acordada_con_paciente' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('citas', [
            'expediente_id' => $this->expediente->id,
            'consulta_id' => $consulta->id,
            'profesional_id' => $this->specialist->profesional->id,
            'area_id' => $this->expediente->area_id,
            'estado' => 'programada',
        ]);

        $cita = Cita::first();
        $this->assertNotNull($cita->motivo);
        $this->assertEquals('Seguimiento clínico', $cita->motivo);
    }

    public function test_race_condition_conflict_prevention()
    {
        $consulta = Consulta::create([
            'expediente_id' => $this->expediente->id,
            'profesional_id' => $this->specialist->profesional->id,
            'motivo_consulta' => 'Motivo base',
            'notas_clinicas' => 'Notas base',
            'diagnostico' => 'Dx base',
            'plan_atencion' => 'Plan de atención de prueba',
            'tecnica_utilizada' => 'Entrevista',
            'fecha_consulta' => now(),
        ]);

        $fechaHora = now()->addDays(2)->format('Y-m-d H:i:00');
        
        Cita::create([
            'expediente_id' => $this->expediente->id,
            'consulta_id' => $consulta->id,
            'profesional_id' => $this->specialist->profesional->id,
            'area_id' => $this->expediente->area_id,
            'fecha_hora' => $fechaHora,
            'motivo' => 'First try',
            'estado' => 'programada'
        ]);

        $response = $this->actingAs($this->specialist)->post("/expedientes/{$this->expediente->id}/citas", [
            'consulta_id' => $consulta->id,
            'fecha_hora' => $fechaHora,
            'motivo' => 'Race condition attempt',
            'acordada_con_paciente' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('fecha_hora');

        $count = Cita::where('profesional_id', $this->specialist->profesional->id)
            ->where('fecha_hora', $fechaHora)
            ->count();
            
        $this->assertEquals(1, $count);
    }
    
    public function test_cannot_schedule_cita_for_closed_expediente()
    {
        $consulta = Consulta::create([
            'expediente_id' => $this->expediente->id,
            'profesional_id' => $this->specialist->profesional->id,
            'motivo_consulta' => 'Motivo base',
            'notas_clinicas' => 'Notas base',
            'diagnostico' => 'Dx base',
            'plan_atencion' => 'Plan de atención de prueba',
            'tecnica_utilizada' => 'Entrevista',
            'fecha_consulta' => now(),
        ]);

        $this->expediente->update(['estado' => 'cerrado']);
        
        $fechaHora = now()->addDays(2)->format('Y-m-d H:i:s');
        
        $response = $this->actingAs($this->specialist)->post("/expedientes/{$this->expediente->id}/citas", [
            'consulta_id' => $consulta->id,
            'fecha_hora' => $fechaHora,
            'motivo' => 'Intento en expediente cerrado',
            'acordada_con_paciente' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('expediente');
    }
}
