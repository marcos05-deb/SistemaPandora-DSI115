<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Http\Requests\StorePacienteRequest;
use App\Models\AlertaPreventiva;
use App\Models\Area;
use App\Models\Carrera;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\EvaluacionRiesgo;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Rubrica;
use App\Models\RubricaVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Datos DEMO locales para gráficas del panel: etiquetas de caso y tendencia suicida.
 * Solo corre en local/testing.
 */
class DashboardCasosDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $area = Area::query()->where('nombre', 'Psicología')->first()
            ?? Area::query()->orderBy('id')->firstOrFail();

        $coordinator = Especialista::query()
            ->where('email', 'coordinador@pandora.com')
            ->first();
        $profesional = $coordinator?->profesional
            ?? Profesional::query()->where('area_id', $area->id)->oldest('created_at')->firstOrFail();

        $carreraId = Carrera::query()->value('id');
        if ($carreraId === null) {
            return;
        }

        $rubrica = Rubrica::query()
            ->where('codigo', Rubrica::CODIGO_RIESGO_PREVENTIVO)
            ->firstOrFail();
        $version = $rubrica->versiones()
            ->where('es_demo', true)
            ->orderByDesc('numero_version')
            ->first()
            ?? $rubrica->versiones()->orderByDesc('numero_version')->firstOrFail();

        $perfiles = [
            ['carnet' => 'DG24001', 'etiquetas' => ['Problemas Académicos', 'Dificultades Socioeconómicas'], 'indicadores' => []],
            ['carnet' => 'DG24002', 'etiquetas' => ['Duelo'], 'indicadores' => []],
            ['carnet' => 'DG24003', 'etiquetas' => ['Violencia Familiar'], 'indicadores' => []],
            ['carnet' => 'DG24004', 'etiquetas' => ['Violencia Pareja', 'Conflictos Interpersonales'], 'indicadores' => []],
            ['carnet' => 'DG24005', 'etiquetas' => ['Problemas de Adaptación'], 'indicadores' => []],
            ['carnet' => 'DG24006', 'etiquetas' => ['Ideas Suicidas', 'Problemas Académicos'], 'indicadores' => ['ideas_suicidas']],
            ['carnet' => 'DG24007', 'etiquetas' => ['Ideas Suicidas'], 'indicadores' => ['ideas_suicidas', 'planificacion']],
            ['carnet' => 'DG24008', 'etiquetas' => ['Ideas Suicidas', 'Duelo'], 'indicadores' => ['ideas_suicidas', 'intentos_previos']],
            ['carnet' => 'DG24009', 'etiquetas' => ['Violencia Docente'], 'indicadores' => ['autolesion']],
            ['carnet' => 'DG24010', 'etiquetas' => ['Conflictos Interpersonales', 'Problemas Académicos'], 'indicadores' => []],
            ['carnet' => 'DG24011', 'etiquetas' => ['Dificultades Socioeconómicas'], 'indicadores' => []],
            ['carnet' => 'DG24012', 'etiquetas' => ['Ideas Suicidas', 'Violencia Familiar'], 'indicadores' => ['ideas_suicidas', 'planificacion', 'autolesion']],
        ];

        foreach ($perfiles as $index => $perfil) {
            $this->seedCaso(
                $perfil,
                $index,
                $area,
                $profesional,
                (int) $carreraId,
                $version
            );
        }
    }

    /**
     * @param  array{carnet: string, etiquetas: list<string>, indicadores: list<string>}  $perfil
     */
    private function seedCaso(
        array $perfil,
        int $index,
        Area $area,
        Profesional $profesional,
        int $carreraId,
        RubricaVersion $version
    ): void {
        foreach ($perfil['etiquetas'] as $tag) {
            if (! in_array($tag, StorePacienteRequest::ETIQUETAS_VALIDAS, true)) {
                return;
            }
        }

        $paciente = Paciente::query()->firstOrCreate(
            ['carnet' => $perfil['carnet']],
            [
                'carrera_id' => $carreraId,
                'creado_por_profesional_id' => $profesional->id,
                'sexo' => $index % 2 === 0 ? 'F' : 'M',
                'estado_civil' => 'Soltero',
                'nombre_completo' => 'Estudiante DEMO '.$perfil['carnet'],
                'direccion' => 'Campus DEMO '.$perfil['carnet'],
                'fecha_nacimiento' => '2002-06-15',
                'profesion_ocupacion' => 'Estudiante',
                'fecha_primera_consulta' => now()->subDays(20)->toDateString(),
                'motivo_consulta' => 'Motivo DEMO tipología de casos para panel clínico.',
                'etiquetas_motivo' => $perfil['etiquetas'],
            ]
        );

        if ($paciente->wasRecentlyCreated === false) {
            $paciente->etiquetas_motivo = $perfil['etiquetas'];
            $paciente->save();
        }

        $expediente = Expediente::query()
            ->where('paciente_id', $paciente->codigo)
            ->where('area_id', $area->id)
            ->first();

        if ($expediente === null) {
            $expediente = Expediente::query()->create([
                'paciente_id' => $paciente->codigo,
                'area_id' => $area->id,
                'motivo_consulta' => 'Expediente DEMO tipología de casos.',
                'notas_clinicas' => 'Nota sintética DEMO panel.',
                'diagnostico' => 'Diagnóstico sintético DEMO.',
                'estado' => Expediente::ESTADO_ABIERTO,
                'derivado_por_profesional_id' => $profesional->id,
                'fecha_derivacion' => now()->subDays(15),
            ]);
        }

        $consultaId = sprintf('00000000-0000-7000-8000-%012d', 500 + $index);
        if (! Consulta::withTrashed()->whereKey($consultaId)->exists()) {
            $consulta = new Consulta;
            $consulta->id = $consultaId;
            $consulta->expediente_id = $expediente->id;
            $consulta->setRelation('expediente', $expediente);
            $consulta->fill([
                'profesional_id' => $profesional->id,
                'motivo_consulta' => 'Consulta DEMO tipología / tendencia.',
                'notas_clinicas' => 'Registro DEMO para panel de alertas y casos.',
                'diagnostico' => 'Datos DEMO panel.',
                'plan_atencion' => 'Seguimiento DEMO.',
                'tecnica_utilizada' => 'Entrevista DEMO',
                'fecha_consulta' => now()->subDays($index + 1)->setTime(10, 0),
                'evaluacion_inicial' => [],
                'crisis_psicologica' => $perfil['indicadores'] !== [] ? 'Sí' : 'No',
                'indicadores_tendencia_suicida' => $perfil['indicadores'],
            ]);
            $consulta->save();
        }

        if ($perfil['indicadores'] === [] || ! in_array('ideas_suicidas', $perfil['indicadores'], true)) {
            return;
        }

        // Alertas de tendencia para un subconjunto (prioridad clínica en el panel).
        if (! in_array($perfil['carnet'], ['DG24006', 'DG24007', 'DG24012'], true)) {
            return;
        }

        $evaluacion = EvaluacionRiesgo::query()->firstOrCreate(
            [
                'evento_origen_tipo' => Consulta::class,
                'evento_origen_id' => $consultaId,
                'area_id' => $area->id,
            ],
            [
                'paciente_id' => $paciente->codigo,
                'rubrica_version_id' => $version->id,
                'disparador' => 'tendencia_suicida',
                'puntaje' => '1.00',
                'detalle' => 'DEMO tendencia suicida: '.implode(', ', $perfil['indicadores']),
                'estado_evaluacion' => 'evaluada',
                'evaluada_en' => Carbon::now()->subDays(2),
            ]
        );

        if (AlertaPreventiva::query()
            ->where('paciente_id', $paciente->codigo)
            ->where('area_id', $area->id)
            ->where('senal', 'tendencia_suicida')
            ->whereIn('estado', AlertaPreventiva::ESTADOS_ACTIVOS)
            ->exists()) {
            return;
        }

        AlertaPreventiva::query()->create([
            'paciente_id' => $paciente->codigo,
            'area_id' => $area->id,
            'evaluacion_riesgo_id' => $evaluacion->id,
            'rubrica_version_id' => $version->id,
            'senal' => 'tendencia_suicida',
            'valor_calculado' => '1.00',
            'umbral' => '1.00',
            'estado' => $perfil['carnet'] === 'DG24012' ? 'activa' : 'en_seguimiento',
            'responsable_profesional_id' => $profesional->id,
            'generada_en' => Carbon::now()->subDays(1),
        ]);
    }
}
