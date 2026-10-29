<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AlertaPreventiva;
use App\Models\Area;
use App\Models\Carrera;
use App\Models\Cita;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\EstadisticaPreventivaAusencia;
use App\Models\EvaluacionRiesgo;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Rubrica;
use App\Models\RubricaVersion;
use App\Models\SeguimientoPreventivo;
use App\Notifications\AlertaPreventivaCreada;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RubricaDemoSeeder extends Seeder
{
    private const DEMO_AUSENCIA_1_ID = '00000000-0000-4000-8000-000000000016';

    private const DEMO_AUSENCIA_2_ID = '00000000-0000-4000-8000-000000000017';

    private const DEMO_CONSULTA_SENALES_ID = '00000000-0000-4000-8000-000000000018';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $profesional = Profesional::query()->oldest('created_at')->firstOrFail();
        $rubrica = Rubrica::query()
            ->where('codigo', Rubrica::CODIGO_RIESGO_PREVENTIVO)
            ->firstOrFail();

        if ($rubrica->versiones()->where('es_demo', false)->exists()) {
            return;
        }

        $versions = $rubrica->versiones()
            ->where('es_demo', true)
            ->orderBy('numero_version')
            ->get();
        if ($versions->isEmpty()) {
            $this->crearVersionDemo($rubrica, $profesional, now()->subDays(60), 'DEMO: histórica', 1.00);
            $this->crearVersionDemo($rubrica, $profesional, now(), 'DEMO: vigente');
            $this->crearVersionDemo($rubrica, $profesional, now()->addDays(30), 'DEMO: futura');
        } else {
            $latest = $versions->last();
            $current = $versions->first(fn (RubricaVersion $version): bool => $version->justificacion_cambio === 'DEMO: vigente');
            if ($current === null && $latest->vigente_desde->isPast()) {
                $this->crearVersionDemo(
                    $rubrica,
                    $profesional,
                    now(),
                    'DEMO: vigente'
                );
            }

            $versions = $rubrica->versiones()
                ->where('es_demo', true)
                ->orderBy('numero_version')
                ->get();
            $hasFuture = $versions->contains(fn (RubricaVersion $version): bool => $version->vigente_desde->isFuture());
            if (! $hasFuture) {
                $latest = $versions->last();
                $futureDate = now()->addDays(30);
                if (! $futureDate->isAfter($latest->vigente_desde)) {
                    $futureDate = $latest->vigente_desde->copy()->addDays(30);
                }

                $this->crearVersionDemo($rubrica, $profesional, $futureDate, 'DEMO: futura');
            }
        }

        $historicalVersion = $rubrica->versiones()
            ->where('es_demo', true)
            ->where('numero_version', 1)
            ->firstOrFail();
        $currentVersion = $rubrica->versiones()
            ->where('es_demo', true)
            ->where('numero_version', 2)
            ->firstOrFail();
        $futureVersion = $rubrica->versiones()
            ->where('es_demo', true)
            ->where('numero_version', 3)
            ->firstOrFail();

        if (! $historicalVersion->vigente_desde->isPast()
            || $currentVersion->vigente_desde->isFuture()
            || ! $futureVersion->vigente_desde->isFuture()) {
            throw new RuntimeException('Las versiones DEMO B.7 deben ser histórica, vigente y futura en orden.');
        }

        $absence = Cita::query()
            ->where('estado', 'ausente')
            ->whereNotIn('id', [self::DEMO_AUSENCIA_1_ID, self::DEMO_AUSENCIA_2_ID])
            ->with('expediente')
            ->oldest('fecha_hora')
            ->firstOrFail();
        $absenceExpediente = $absence->expediente;

        if ($absenceExpediente === null) {
            throw new RuntimeException('La cita DEMO de ausencia no tiene expediente.');
        }

        $crisisConsulta = Consulta::query()
            ->with('expediente')
            ->whereHas('expediente', fn ($query) => $query
                ->where('area_id', $absence->area_id)
                ->where('paciente_id', '!=', $absenceExpediente->paciente_id))
            ->oldest('fecha_consulta')
            ->first();

        $crisisConsulta ??= Consulta::query()
            ->with('expediente')
            ->whereHas('expediente', fn ($query) => $query
                ->where('paciente_id', '!=', $absenceExpediente->paciente_id))
            ->oldest('fecha_consulta')
            ->firstOrFail();
        $crisisExpediente = $crisisConsulta->expediente;

        if ($crisisExpediente === null) {
            throw new RuntimeException('La consulta DEMO de crisis no tiene expediente.');
        }

        $this->crearEvaluacionDemo(
            $historicalVersion,
            $absenceExpediente->paciente_id,
            (int) $absence->area_id,
            'ausencia_cita',
            1.00,
            ['total' => 1, 'ventana_dias' => 30],
            Cita::class,
            $absence->id,
            now()
        );

        $this->crearEvaluacionDemo(
            $currentVersion,
            $crisisExpediente->paciente_id,
            (int) $crisisConsulta->expediente->area_id,
            'crisis_psicologica',
            1.00,
            ['total' => 1, 'ventana_dias' => 90],
            Consulta::class,
            $crisisConsulta->id,
            now()
        );

        $this->crearEscenariosDemoHu16($currentVersion, $absence);
        $this->crearEscenariosDemoHu17($historicalVersion, $currentVersion, $absence);
        $this->crearEscenariosDemoHu18();
    }

    private function crearEscenariosDemoHu18(): void
    {
        $psychology = Area::query()->where('nombre', 'Psicología')->firstOrFail();
        $socialWork = Area::query()->where('nombre', 'Trabajo Social')->firstOrFail();
        $psychologyProfessional = Profesional::query()
            ->whereHas('especialista', fn ($query) => $query->where('email', 'especialista-psi@pandora.com'))
            ->where('area_id', $psychology->id)
            ->firstOrFail();
        $socialProfessional = Profesional::query()
            ->whereHas('especialista', fn ($query) => $query->where('email', 'psicosocial@pandora.com'))
            ->where('area_id', $socialWork->id)
            ->firstOrFail();
        $career = Carrera::query()->firstOrFail();

        $manyPatient = $this->patientIndicatorDemo('HU18M001', $psychologyProfessional, $career->id);
        $manyFile = $this->activeIndicatorDemoFile($manyPatient, $psychology, $psychologyProfessional);
        $fewPatient = $this->patientIndicatorDemo('HU18L001', $socialProfessional, $career->id);
        $fewFile = $this->activeIndicatorDemoFile($fewPatient, $socialWork, $socialProfessional);
        $closedPatient = $this->patientIndicatorDemo('HU18C001', $socialProfessional, $career->id);
        $this->closedIndicatorDemoFile($closedPatient, $socialWork, $socialProfessional);

        for ($index = 1; $index <= 30; $index++) {
            $this->consultationIndicatorDemo($manyFile, $psychologyProfessional, $index, true);
            $this->appointmentIndicatorDemo($manyFile, $psychologyProfessional, $index, true);
        }

        $this->consultationIndicatorDemo($fewFile, $socialProfessional, 31, false);
        $this->appointmentIndicatorDemo($fewFile, $socialProfessional, 31, false);
        $this->appointmentIndicatorDemo($fewFile, $socialProfessional, 32, false);
    }

    private function patientIndicatorDemo(string $carnet, Profesional $professional, int $careerId): Paciente
    {
        return Paciente::query()->firstOrCreate(
            ['carnet' => $carnet],
            [
                'carrera_id' => $careerId,
                'creado_por_profesional_id' => $professional->id,
                'sexo' => 'F',
                'estado_civil' => 'Soltero',
                'nombre_completo' => 'Paciente DEMO '.$carnet,
                'direccion' => 'Dirección DEMO '.$carnet,
                'fecha_nacimiento' => '2000-01-01',
                'profesion_ocupacion' => 'Estudiante',
                'fecha_primera_consulta' => now()->subDays(30)->toDateString(),
                'motivo_consulta' => 'Motivo clínico sintético para pruebas agregadas.',
            ],
        );
    }

    private function activeIndicatorDemoFile(Paciente $patient, Area $area, Profesional $professional): Expediente
    {
        $expediente = Expediente::query()
            ->where('paciente_id', $patient->codigo)
            ->where('area_id', $area->id)
            ->first();

        if ($expediente !== null) {
            return $expediente;
        }

        return Expediente::query()->create([
            'paciente_id' => $patient->codigo,
            'area_id' => $area->id,
            'motivo_consulta' => 'Motivo sintético de expediente DEMO.',
            'notas_clinicas' => 'Nota sintética de expediente DEMO.',
            'diagnostico' => 'Diagnóstico sintético DEMO.',
            'estado' => Expediente::ESTADO_ABIERTO,
            'derivado_por_profesional_id' => $professional->id,
            'fecha_derivacion' => now()->subDays(20),
        ]);
    }

    private function closedIndicatorDemoFile(Paciente $patient, Area $area, Profesional $professional): void
    {
        if (Expediente::withTrashed()->where('paciente_id', $patient->codigo)->where('area_id', $area->id)->exists()) {
            return;
        }

        Expediente::query()->create([
            'paciente_id' => $patient->codigo,
            'area_id' => $area->id,
            'motivo_consulta' => 'Motivo sintético de expediente DEMO cerrado.',
            'notas_clinicas' => 'Nota sintética de expediente DEMO cerrado.',
            'diagnostico' => 'Diagnóstico sintético DEMO cerrado.',
            'estado' => Expediente::ESTADO_CERRADO,
            'derivado_por_profesional_id' => $professional->id,
            'fecha_derivacion' => now()->subDays(40),
            'motivo_cierre' => 'Cierre sintético para pruebas agregadas.',
            'resultado_final' => 'Caso DEMO completado.',
            'fecha_cierre' => now()->subDays(10),
            'cerrado_por_profesional_id' => $professional->id,
        ]);
    }

    private function consultationIndicatorDemo(Expediente $expediente, Profesional $professional, int $index, bool $many): void
    {
        $id = sprintf('00000000-0000-4000-8000-%012d', 100 + $index);
        if (Consulta::withTrashed()->whereKey($id)->exists()) {
            return;
        }

        $consulta = new Consulta;
        $consulta->id = $id;
        $consulta->expediente_id = $expediente->id;
        $consulta->setRelation('expediente', $expediente);
        $consulta->fill([
            'profesional_id' => $professional->id,
            'motivo_consulta' => 'Motivo de consulta sintético para indicadores.',
            'notas_clinicas' => 'Nota clínica sintética para indicadores.',
            'diagnostico' => 'Diagnóstico sintético para indicadores.',
            'plan_atencion' => 'Plan sintético de atención para datos DEMO.',
            'tecnica_utilizada' => 'Entrevista DEMO',
            'fecha_consulta' => now()->subDays($many ? ($index - 1) % 30 : 3)->setTime(9, $index % 60),
            'evaluacion_inicial' => [],
            'crisis_psicologica' => 'No',
            'indicadores_tendencia_suicida' => [],
        ]);
        $consulta->save();
    }

    private function appointmentIndicatorDemo(Expediente $expediente, Profesional $professional, int $index, bool $many): void
    {
        $id = sprintf('00000000-0000-4000-8000-%012d', 200 + $index);
        if (Cita::withTrashed()->whereKey($id)->exists()) {
            return;
        }

        $states = ['programada', 'asistida', 'ausente', 'cancelada', 'reprogramada'];
        $cita = new Cita;
        $cita->id = $id;
        $cita->expediente_id = $expediente->id;
        $cita->profesional_id = $professional->id;
        $cita->area_id = $expediente->area_id;
        $cita->fecha_hora = now()->subDays($many ? ($index - 1) % 30 : 3)->setTime(14, $index % 60);
        $cita->motivo = 'Cita sintética para indicadores DEMO.';
        $cita->estado = $many ? $states[($index - 1) % count($states)] : ($index === 31 ? 'asistida' : 'programada');

        if (in_array($cita->estado, ['asistida', 'ausente'], true)) {
            $cita->fecha_registro_asistencia = $cita->fecha_hora->copy()->addMinutes(30);
            $cita->registrado_por_profesional_id = $professional->id;
        } elseif ($cita->estado === 'cancelada') {
            $cita->motivo_cancelacion = 'Cancelación sintética para indicadores DEMO.';
            $cita->fecha_cancelacion = $cita->fecha_hora->copy()->addMinutes(30);
            $cita->cancelado_por_profesional_id = $professional->id;
        } elseif ($cita->estado === 'reprogramada') {
            $cita->motivo_reprogramacion = 'Reprogramación sintética para indicadores DEMO.';
            $cita->fecha_reprogramacion = $cita->fecha_hora->copy()->addMinutes(30);
            $cita->reprogramado_por_profesional_id = $professional->id;
        }

        $cita->save();
    }

    private function crearVersionDemo(
        Rubrica $rubrica,
        Profesional $profesional,
        Carbon $vigenteDesde,
        string $justificacion,
        float $umbralAusencias = 2.00
    ): RubricaVersion {
        $version = $rubrica->versiones()->create([
            'numero_version' => ((int) $rubrica->versiones()->max('numero_version')) + 1,
            'vigente_desde' => $vigenteDesde,
            'umbral_puntaje_global' => null,
            'justificacion_cambio' => $justificacion,
            'validada_en' => null,
            'referencia_validacion' => null,
            'es_demo' => true,
            'creada_por_profesional_id' => $profesional->id,
        ]);

        foreach ([
            [
                'tipo_senal' => 'ausencia_cita',
                'clave_indicador' => null,
                'modo_calculo' => 'frecuencia',
                'ventana_dias' => 30,
                'ponderacion' => 1.00,
                'umbral' => $umbralAusencias,
            ],
            [
                'tipo_senal' => 'crisis_psicologica',
                'clave_indicador' => null,
                'modo_calculo' => 'frecuencia',
                'ventana_dias' => 90,
                'ponderacion' => 1.00,
                'umbral' => 1.00,
            ],
            [
                'tipo_senal' => 'tendencia_suicida',
                'clave_indicador' => 'ideas_suicidas',
                'modo_calculo' => 'puntaje',
                'ventana_dias' => null,
                'ponderacion' => 1.00,
                'umbral' => 1.00,
            ],
        ] as $variable) {
            $version->variables()->create($variable);
        }

        return $version;
    }

    /** @param array<string, mixed> $detalle */
    private function crearEvaluacionDemo(
        RubricaVersion $version,
        string $pacienteId,
        int $areaId,
        string $disparador,
        float $puntaje,
        array $detalle,
        string $eventoOrigenTipo,
        string $eventoOrigenId,
        Carbon $evaluadaEn
    ): EvaluacionRiesgo {
        $existingEvaluation = EvaluacionRiesgo::query()
            ->where('evento_origen_tipo', $eventoOrigenTipo)
            ->where('evento_origen_id', $eventoOrigenId)
            ->where('rubrica_version_id', $version->id)
            ->first();

        if ($existingEvaluation !== null) {
            return $existingEvaluation;
        }

        $evaluacion = new EvaluacionRiesgo;
        $evaluacion->area_id = $areaId;
        $evaluacion->paciente_id = $pacienteId;
        $evaluacion->rubrica_version_id = $version->id;
        $evaluacion->disparador = $disparador;
        $evaluacion->puntaje = $puntaje;
        $evaluacion->detalle = json_encode(['demo' => true, ...$detalle], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $evaluacion->evento_origen_tipo = $eventoOrigenTipo;
        $evaluacion->evento_origen_id = $eventoOrigenId;
        $evaluacion->evaluada_en = $evaluadaEn;
        $evaluacion->save();

        return $evaluacion;
    }

    private function crearEscenariosDemoHu16(RubricaVersion $version, Cita $absence): void
    {
        $absenceExpediente = $absence->expediente;
        if ($absenceExpediente === null) {
            throw new RuntimeException('La cita DEMO de ausencia no tiene expediente.');
        }

        $coordinator = Profesional::query()
            ->whereHas('especialista', fn ($query) => $query->where('email', 'coordinador@pandora.com'))
            ->firstOrFail();
        $psychologySpecialist = Profesional::query()
            ->whereHas('especialista', fn ($query) => $query->where('email', 'especialista-psi@pandora.com'))
            ->firstOrFail();
        $medicineSpecialist = Profesional::query()
            ->whereHas('especialista', fn ($query) => $query->where('email', 'especialista@pandora.com'))
            ->firstOrFail();

        $this->registrarAusenciaDemo($absence);

        $expedienteDosAusencias = $this->expedienteDosAusenciasDemo($absenceExpediente);
        $primeraAusencia = $this->crearCitaAusenciaDemo(
            $expedienteDosAusencias,
            $psychologySpecialist,
            self::DEMO_AUSENCIA_1_ID,
            now()->subDays(3)->setTime(21, 0)
        );
        $segundaAusencia = $this->crearCitaAusenciaDemo(
            $expedienteDosAusencias,
            $psychologySpecialist,
            self::DEMO_AUSENCIA_2_ID,
            now()->subDays(2)->setTime(21, 0)
        );
        $this->registrarAusenciaDemo($primeraAusencia);
        $this->registrarAusenciaDemo($segundaAusencia);

        $ausenciaEvaluation = $this->crearEvaluacionDemo(
            $version,
            $expedienteDosAusencias->paciente_id,
            (int) $expedienteDosAusencias->area_id,
            'ausencia_cita',
            2.00,
            ['ausencia_cita' => [[
                'clave_indicador' => null,
                'valor' => 2.00,
                'umbral' => 2.00,
                'modo_calculo' => 'frecuencia',
                'ventana_dias' => 30,
                'ponderacion' => 1.00,
            ]]],
            Cita::class,
            $segundaAusencia->id,
            now()
        );
        $this->crearAlertaDemo($ausenciaEvaluation, 'ausencia_cita', '2', 2.00, 'activa', $psychologySpecialist);

        $demoConsulta = Consulta::query()->with('expediente')->find(self::DEMO_CONSULTA_SENALES_ID);
        $expedienteSinAusencias = $demoConsulta?->expediente
            ?? $this->expedienteSinAusenciasDemo((int) $medicineSpecialist->area_id);
        $demoConsulta = $this->crearConsultaSenalesDemo($demoConsulta, $expedienteSinAusencias, $medicineSpecialist);
        $consultaEvaluation = $this->crearEvaluacionDemo(
            $version,
            $expedienteSinAusencias->paciente_id,
            (int) $expedienteSinAusencias->area_id,
            'consulta_senales',
            1.00,
            [
                'crisis_psicologica' => [[
                    'clave_indicador' => null,
                    'valor' => 1.00,
                    'umbral' => 1.00,
                    'modo_calculo' => 'frecuencia',
                    'ventana_dias' => 90,
                    'ponderacion' => 1.00,
                ]],
                'tendencia_suicida' => [[
                    'clave_indicador' => 'ideas_suicidas',
                    'valor' => 1.00,
                    'umbral' => 1.00,
                    'modo_calculo' => 'puntaje',
                    'ventana_dias' => null,
                    'ponderacion' => 1.00,
                ]],
            ],
            Consulta::class,
            $demoConsulta->id,
            now()
        );
        $this->crearAlertaDemo($consultaEvaluation, 'crisis_psicologica', '1', 1.00, 'activa', $medicineSpecialist);
        $this->crearAlertaDemo($consultaEvaluation, 'tendencia_suicida', '1', 1.00, 'cerrada', $medicineSpecialist);

        if ((int) $coordinator->area_id !== (int) $absenceExpediente->area_id
            || (int) $medicineSpecialist->area_id === (int) $absenceExpediente->area_id) {
            throw new RuntimeException('Los escenarios DEMO HU-16 requieren profesionales en dos áreas distintas.');
        }
    }

    private function crearEscenariosDemoHu17(
        RubricaVersion $historicalVersion,
        RubricaVersion $currentVersion,
        Cita $absence
    ): void {
        $absenceExpediente = $absence->expediente;
        if ($absenceExpediente === null) {
            throw new RuntimeException('La cita DEMO de seguimiento no tiene expediente.');
        }

        $psychologySpecialist = Profesional::query()
            ->whereHas('especialista', fn ($query) => $query->where('email', 'especialista-psi@pandora.com'))
            ->where('area_id', $absenceExpediente->area_id)
            ->firstOrFail();
        $coordinator = Profesional::query()
            ->whereHas('especialista', fn ($query) => $query->where('email', 'coordinador@pandora.com'))
            ->where('area_id', $absenceExpediente->area_id)
            ->firstOrFail();
        $medicineSpecialist = Profesional::query()
            ->whereHas('especialista', fn ($query) => $query->where('email', 'especialista@pandora.com'))
            ->firstOrFail();
        $nutritionSpecialist = Profesional::query()
            ->whereHas('especialista', fn ($query) => $query->where('email', 'especialista-nutri@pandora.com'))
            ->firstOrFail();

        $absenceEvaluation = $this->crearEvaluacionDemo(
            $historicalVersion,
            $absenceExpediente->paciente_id,
            (int) $absenceExpediente->area_id,
            'ausencia_cita',
            1.00,
            ['total' => 1, 'ventana_dias' => 30],
            Cita::class,
            $absence->id,
            now()
        );
        $followupAlert = $this->crearAlertaDemo(
            $absenceEvaluation,
            'ausencia_cita',
            '1',
            1.00,
            'en_seguimiento',
            $psychologySpecialist
        );

        if ($followupAlert === null || $followupAlert->estado !== 'en_seguimiento') {
            throw new RuntimeException('La alerta DEMO HU-17 de seguimiento no está disponible.');
        }

        $followupCount = SeguimientoPreventivo::query()
            ->where('alerta_preventiva_id', $followupAlert->id)
            ->where('area_id', $followupAlert->area_id)
            ->count();
        if ($followupCount > 2) {
            throw new RuntimeException('La alerta DEMO HU-17 tiene más de dos seguimientos append-only.');
        }

        foreach (array_slice([
            ['valoracion' => 'DEMO: revisión preventiva inicial.', 'accion' => 'DEMO: orientación preventiva documentada.', 'created_days_ago' => 3, 'review_days_ago' => 2],
            ['valoracion' => 'DEMO: revisión preventiva de seguimiento.', 'accion' => 'DEMO: control preventivo pendiente.', 'created_days_ago' => 2, 'review_days_ago' => 1],
        ], $followupCount) as $followup) {
            $now = now();
            $entry = new SeguimientoPreventivo;
            $entry->alerta_preventiva_id = $followupAlert->id;
            $entry->area_id = $followupAlert->area_id;
            $entry->valoracion = $followup['valoracion'];
            $entry->accion_preventiva = $followup['accion'];
            $entry->responsable_profesional_id = $coordinator->id;
            $entry->proxima_revision = $now->copy()->subDays($followup['review_days_ago']);
            $entry->registrado_por_profesional_id = $coordinator->id;
            $entry->created_at = $now->copy()->subDays($followup['created_days_ago']);
            $entry->save();
        }

        $demoConsulta = Consulta::query()->with('expediente')->findOrFail(self::DEMO_CONSULTA_SENALES_ID);
        $medicineExpediente = $demoConsulta->expediente;
        if ($medicineExpediente === null) {
            throw new RuntimeException('La consulta DEMO de cierre no tiene expediente.');
        }

        $medicineEvaluation = $this->crearEvaluacionDemo(
            $currentVersion,
            $medicineExpediente->paciente_id,
            (int) $medicineExpediente->area_id,
            'consulta_senales',
            1.00,
            ['tendencia_suicida' => [['clave_indicador' => 'ideas_suicidas', 'valor' => 1.00]]],
            Consulta::class,
            $demoConsulta->id,
            now()
        );
        $closedAlert = $this->crearAlertaDemo(
            $medicineEvaluation,
            'tendencia_suicida',
            '1',
            1.00,
            'cerrada',
            $medicineSpecialist
        );
        if ($closedAlert === null || $closedAlert->estado !== 'cerrada') {
            throw new RuntimeException('La alerta DEMO HU-17 cerrada no está disponible.');
        }

        $closedAlert->resultado_cierre ??= 'DEMO: seguimiento completado y resultado documentado.';
        $closedAlert->cerrada_en ??= now()->subDay();
        $closedAlert->cerrada_por_profesional_id ??= $medicineSpecialist->id;
        if ($closedAlert->isDirty()) {
            $closedAlert->save();
        }

        $discardedEvaluation = $this->crearEvaluacionDemo(
            $historicalVersion,
            $medicineExpediente->paciente_id,
            (int) $medicineExpediente->area_id,
            'consulta_senales',
            1.00,
            ['tendencia_suicida' => [['clave_indicador' => 'ideas_suicidas', 'valor' => 1.00]]],
            Consulta::class,
            $demoConsulta->id,
            now()
        );
        $discardedAlert = $this->crearAlertaDemo(
            $discardedEvaluation,
            'tendencia_suicida',
            '1',
            1.00,
            'descartada',
            $medicineSpecialist
        );
        if ($discardedAlert === null || $discardedAlert->estado !== 'descartada') {
            throw new RuntimeException('La alerta DEMO HU-17 descartada no está disponible.');
        }

        $discardedAlert->justificacion_descarte ??= 'DEMO: señal revisada y descartada tras verificación preventiva.';
        $discardedAlert->descartada_en ??= now()->subDay();
        $discardedAlert->descartada_por_profesional_id ??= $medicineSpecialist->id;
        if ($discardedAlert->isDirty()) {
            $discardedAlert->save();
        }

        $nutritionConsulta = Consulta::query()
            ->with('expediente')
            ->whereHas('expediente', fn ($query) => $query->where('area_id', $nutritionSpecialist->area_id))
            ->oldest('fecha_consulta')
            ->firstOrFail();
        $nutritionExpediente = $nutritionConsulta->expediente;
        if ($nutritionExpediente === null) {
            throw new RuntimeException('La consulta DEMO de nutrición no tiene expediente.');
        }

        $nutritionEvaluation = $this->crearEvaluacionDemo(
            $currentVersion,
            $nutritionExpediente->paciente_id,
            (int) $nutritionExpediente->area_id,
            'crisis_psicologica',
            1.00,
            ['crisis_psicologica' => [['valor' => 1.00, 'umbral' => 1.00]]],
            Consulta::class,
            $nutritionConsulta->id,
            now()
        );
        $this->crearAlertaDemo($nutritionEvaluation, 'crisis_psicologica', '1', 1.00, 'activa', $nutritionSpecialist);
    }

    private function expedienteDosAusenciasDemo(Expediente $absenceExpediente): Expediente
    {
        $existingCita = Cita::query()
            ->whereIn('id', [self::DEMO_AUSENCIA_1_ID, self::DEMO_AUSENCIA_2_ID])
            ->with('expediente')
            ->first();

        if ($existingCita?->expediente !== null) {
            return $existingCita->expediente;
        }

        $desde = now()->subDays(30);

        return Expediente::query()
            ->where('area_id', $absenceExpediente->area_id)
            ->where('paciente_id', '!=', $absenceExpediente->paciente_id)
            ->whereDoesntHave('citas', fn ($query) => $query
                ->where('estado', 'ausente')
                ->where('fecha_hora', '>=', $desde))
            ->whereNotIn('paciente_id', EstadisticaPreventivaAusencia::query()
                ->select('paciente_id')
                ->where('area_id', $absenceExpediente->area_id)
                ->where('fecha_registro', '>=', $desde))
            ->orderBy('paciente_id')
            ->firstOrFail();
    }

    private function expedienteSinAusenciasDemo(int $areaId): Expediente
    {
        $desde = now()->subDays(30);

        return Expediente::query()
            ->where('area_id', $areaId)
            ->whereDoesntHave('citas', fn ($query) => $query
                ->where('estado', 'ausente')
                ->where('fecha_hora', '>=', $desde))
            ->whereNotIn('paciente_id', EstadisticaPreventivaAusencia::query()
                ->select('paciente_id')
                ->where('area_id', $areaId)
                ->where('fecha_registro', '>=', $desde))
            ->orderBy('paciente_id')
            ->firstOrFail();
    }

    private function crearCitaAusenciaDemo(
        Expediente $expediente,
        Profesional $profesional,
        string $citaId,
        Carbon $fecha
    ): Cita {
        $cita = Cita::query()->find($citaId);
        if ($cita !== null) {
            return $cita;
        }

        $cita = new Cita;
        $cita->id = $citaId;
        $cita->expediente_id = $expediente->id;
        $cita->profesional_id = $profesional->id;
        $cita->area_id = $expediente->area_id;
        $cita->fecha_hora = $fecha;
        $cita->motivo = 'DEMO: ausencia para evaluación preventiva';
        $cita->estado = 'ausente';
        $cita->fecha_registro_asistencia = $fecha->copy()->addMinutes(30);
        $cita->registrado_por_profesional_id = $profesional->id;
        $cita->save();

        return $cita;
    }

    private function registrarAusenciaDemo(Cita $cita): void
    {
        $cita->loadMissing('expediente');
        if ($cita->expediente === null) {
            throw new RuntimeException('La cita DEMO de ausencia no tiene expediente.');
        }

        EstadisticaPreventivaAusencia::query()->firstOrCreate(
            ['cita_id' => $cita->id],
            [
                'paciente_id' => $cita->expediente->paciente_id,
                'profesional_id' => $cita->profesional_id,
                'area_id' => $cita->area_id,
                'fecha_registro' => $cita->fecha_registro_asistencia ?? now(),
            ]
        );
    }

    private function crearConsultaSenalesDemo(
        ?Consulta $consulta,
        Expediente $expediente,
        Profesional $profesional
    ): Consulta {
        if ($consulta !== null) {
            return $consulta;
        }

        $consulta = new Consulta;
        $consulta->id = self::DEMO_CONSULTA_SENALES_ID;
        $consulta->expediente_id = $expediente->id;
        $consulta->profesional_id = $profesional->id;
        $consulta->motivo_consulta = 'DEMO: señales para evaluación preventiva';
        $consulta->notas_clinicas = 'Registro DEMO para validar señales preventivas.';
        $consulta->diagnostico = 'Datos DEMO.';
        $consulta->fecha_consulta = now();
        $consulta->crisis_psicologica = 'Sí';
        $consulta->indicadores_tendencia_suicida = ['ideas_suicidas'];
        $consulta->save();

        return $consulta;
    }

    private function crearAlertaDemo(
        EvaluacionRiesgo $evaluacion,
        string $senal,
        string $valor,
        float $umbral,
        string $estado,
        Profesional $responsable
    ): ?AlertaPreventiva {
        $alerta = AlertaPreventiva::query()
            ->where('evaluacion_riesgo_id', $evaluacion->id)
            ->where('senal', $senal)
            ->first();

        if ($alerta === null && in_array($estado, ['activa', 'en_seguimiento'], true)
            && AlertaPreventiva::query()
                ->where('paciente_id', $evaluacion->paciente_id)
                ->where('area_id', $evaluacion->area_id)
                ->where('senal', $senal)
                ->whereIn('estado', ['activa', 'en_seguimiento'])
                ->exists()) {
            return null;
        }

        if ($alerta === null) {
            $alerta = new AlertaPreventiva;
            $alerta->paciente_id = $evaluacion->paciente_id;
            $alerta->area_id = $evaluacion->area_id;
            $alerta->evaluacion_riesgo_id = $evaluacion->id;
            $alerta->rubrica_version_id = $evaluacion->rubrica_version_id;
            $alerta->senal = $senal;
            $alerta->valor_calculado = $valor;
            $alerta->umbral = $umbral;
            $alerta->estado = $estado;
            $alerta->responsable_profesional_id = $responsable->id;
            $alerta->generada_en = now();
            if ($estado === 'cerrada') {
                $alerta->resultado_cierre = 'DEMO: resultado documentado para el escenario de cierre.';
                $alerta->cerrada_en = now();
                $alerta->cerrada_por_profesional_id = $responsable->id;
            } elseif ($estado === 'descartada') {
                $alerta->justificacion_descarte = 'DEMO: justificación documentada para el escenario de descarte.';
                $alerta->descartada_en = now();
                $alerta->descartada_por_profesional_id = $responsable->id;
            }
            $alerta->save();
        }

        if (! in_array($alerta->estado, ['activa', 'en_seguimiento'], true)) {
            return $alerta;
        }

        $recipients = Especialista::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['area_coordinator', 'specialist']))
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('slug', ['sysadmin', 'psychosocial_referent']))
            ->whereHas('perfilesProfesionales', fn ($query) => $query->where('area_id', $alerta->area_id))
            ->get();

        foreach ($recipients as $recipient) {
            $alreadyNotified = DB::table('notifications')
                ->where('type', AlertaPreventivaCreada::class)
                ->where('notifiable_type', $recipient::class)
                ->where('notifiable_id', $recipient->getKey())
                ->whereRaw("data::jsonb ->> 'alert_id' = ?", [$alerta->id])
                ->exists();

            if (! $alreadyNotified) {
                $recipient->notify(new AlertaPreventivaCreada($alerta));
            }
        }

        return $alerta;
    }
}
