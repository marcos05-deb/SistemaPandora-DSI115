<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cita;
use App\Models\Consulta;
use App\Models\EvaluacionRiesgo;
use App\Models\Profesional;
use App\Models\Rubrica;
use App\Models\RubricaVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

class RubricaDemoSeeder extends Seeder
{
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

    /** @param array{total: int, ventana_dias: int} $detalle */
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
    ): void {
        $exists = EvaluacionRiesgo::query()
            ->where('evento_origen_tipo', $eventoOrigenTipo)
            ->where('evento_origen_id', $eventoOrigenId)
            ->where('rubrica_version_id', $version->id)
            ->exists();

        if ($exists) {
            return;
        }

        $evaluacion = new EvaluacionRiesgo();
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
    }
}
