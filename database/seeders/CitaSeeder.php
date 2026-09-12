<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cita;
use App\Models\Consulta;
use App\Models\Expediente;
use App\Models\Profesional;
use Carbon\Carbon;

class CitaSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Obtener Expedientes
        $expedientes = Expediente::all();
        if ($expedientes->count() < 3) {
            $this->command->warn('No hay suficientes expedientes activos. Asegúrese de correr ExpedienteSeeder primero.');
            return;
        }

        // 2. Obtener Profesionales para probar aislamiento
        // Coordinador (Psicología)
        $coord = Profesional::whereHas('especialista', fn($q) => $q->where('email', 'coordinador@pandora.com'))->first();
        // Especialista (Psicología)
        $espPsi1 = Profesional::whereHas('especialista', fn($q) => $q->where('email', 'especialista-psi@pandora.com'))->first();
        // Especialista de otra área (Medicina General)
        $espMed = Profesional::whereHas('especialista', fn($q) => $q->where('email', 'especialista@pandora.com'))->first();

        if (!$coord || !$espPsi1 || !$espMed) {
            $this->command->warn('Faltan profesionales en la base de datos.');
            return;
        }

        // Obtener expedientes que coincidan con las áreas
        $expPsi = Expediente::where('area_id', $coord->area_id)->get();
        $expMed = Expediente::where('area_id', $espMed->area_id)->get();

        if ($expPsi->count() < 2 || $expMed->count() < 2) {
            $this->command->warn('No hay suficientes expedientes en las áreas específicas.');
            return;
        }

        // Citas para Especialista 1 (Psicología)
        $this->crearCita(
            $expPsi[0], $espPsi1, 'programada', Carbon::tomorrow()->setHour(9)->setMinute(0), 'Consulta inicial psicología'
        );
        $this->crearCita(
            $expPsi[1], $espPsi1, 'asistida', Carbon::yesterday()->setHour(10)->setMinute(0), 'Seguimiento', true
        );
        $this->crearCita(
            $expPsi[0], $espPsi1, 'ausente', Carbon::yesterday()->setHour(14)->setMinute(0), 'Terapia cognitivo conductual', true
        );

        // Citas para Coordinador (atendiendo pacientes de Psicología)
        $citaCancelada = $this->crearCita(
            $expPsi[1], $coord, 'cancelada', Carbon::tomorrow()->setHour(11)->setMinute(0), 'Evaluación psicológica'
        );
        $citaCancelada->motivo_cancelacion = 'Paciente reportó enfermedad';
        $citaCancelada->save();

        $citaOriginal = $this->crearCita(
            $expPsi[0], $coord, 'reprogramada', Carbon::today()->setHour(15)->setMinute(0), 'Sesión familiar'
        );
        $citaNueva = $this->crearCita(
            $expPsi[0], $coord, 'programada', Carbon::tomorrow()->setHour(15)->setMinute(0), 'Sesión familiar'
        );
        $citaNueva->cita_origen_id = $citaOriginal->id;
        $citaNueva->save();

        // Citas para Especialista de otra área (Medicina General)
        // Esto demostrará que el coordinador de psicología NO puede ver estas citas.
        $this->crearCita(
            $expMed[0], $espMed, 'programada', Carbon::today()->setHour(8)->setMinute(0), 'Chequeo general'
        );
        $this->crearCita(
            $expMed[1], $espMed, 'asistida', Carbon::yesterday()->setHour(9)->setMinute(0), 'Control de presión', true, true
        );
    }

    private function crearCita($expediente, $profesional, $estado, $fecha, $motivo, $registrarAsistencia = false, $crearConsulta = false)
    {
        $cita = new Cita();
        $cita->expediente_id = $expediente->id;
        $cita->profesional_id = $profesional->id;
        $cita->area_id = $profesional->area_id;
        $cita->fecha_hora = $fecha;
        $cita->motivo = $motivo;
        $cita->estado = $estado;
        $cita->save();

        if ($registrarAsistencia) {
            $cita->fecha_registro_asistencia = $fecha->copy()->addMinutes(30);
            $cita->registrado_por_profesional_id = $profesional->id;
            $cita->save();
        }

        if ($crearConsulta && $estado === 'asistida') {
            Consulta::create([
                'expediente_id' => $expediente->id,
                'profesional_id' => $profesional->id,
                'fecha_consulta' => $fecha,
                'motivo_consulta' => $motivo,
                'notas_clinicas' => 'El paciente se presentó a la cita sin complicaciones mayores. Se brindaron recomendaciones generales.',
                'diagnostico' => 'Salud en general estable. Ninguna afección aguda.',
            ]);
        }

        return $cita;
    }
}
