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
 * Datos DEMO locales para gráficas del panel: etiquetas y tendencia por facultad/carrera.
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

        $carreras = Carrera::query()
            ->with('facultad')
            ->whereHas('facultad', static fn ($q) => $q->where('nombre', 'not like', 'Institución%'))
            ->orderBy('id')
            ->get();

        if ($carreras->count() < 4) {
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

        // Distribución intencional: varias facultades/carreras + casos con tendencia.
        $perfiles = [
            ['carnet' => 'DG24001', 'carrera_i' => 0, 'etiquetas' => ['Problemas Académicos', 'Dificultades Socioeconómicas'], 'indicadores' => []],
            ['carnet' => 'DG24002', 'carrera_i' => 1, 'etiquetas' => ['Duelo'], 'indicadores' => []],
            ['carnet' => 'DG24003', 'carrera_i' => 2, 'etiquetas' => ['Violencia Familiar'], 'indicadores' => []],
            ['carnet' => 'DG24004', 'carrera_i' => 3, 'etiquetas' => ['Violencia Pareja', 'Conflictos Interpersonales'], 'indicadores' => []],
            ['carnet' => 'DG24005', 'carrera_i' => 4, 'etiquetas' => ['Problemas de Adaptación'], 'indicadores' => []],
            ['carnet' => 'DG24006', 'carrera_i' => 0, 'etiquetas' => ['Ideas Suicidas', 'Problemas Académicos'], 'indicadores' => ['ideas_suicidas']],
            ['carnet' => 'DG24007', 'carrera_i' => 1, 'etiquetas' => ['Ideas Suicidas'], 'indicadores' => ['ideas_suicidas', 'planificacion']],
            ['carnet' => 'DG24008', 'carrera_i' => 5, 'etiquetas' => ['Ideas Suicidas', 'Duelo'], 'indicadores' => ['ideas_suicidas', 'intentos_previos']],
            ['carnet' => 'DG24009', 'carrera_i' => 6, 'etiquetas' => ['Violencia Docente'], 'indicadores' => ['autolesion']],
            ['carnet' => 'DG24010', 'carrera_i' => 2, 'etiquetas' => ['Conflictos Interpersonales', 'Problemas Académicos'], 'indicadores' => []],
            ['carnet' => 'DG24011', 'carrera_i' => 7, 'etiquetas' => ['Dificultades Socioeconómicas'], 'indicadores' => []],
            ['carnet' => 'DG24012', 'carrera_i' => 3, 'etiquetas' => ['Ideas Suicidas', 'Violencia Familiar'], 'indicadores' => ['ideas_suicidas', 'planificacion', 'autolesion']],
            ['carnet' => 'DG24013', 'carrera_i' => 0, 'etiquetas' => ['Ideas Suicidas'], 'indicadores' => ['ideas_suicidas']],
            ['carnet' => 'DG24014', 'carrera_i' => 5, 'etiquetas' => ['Ideas Suicidas', 'Problemas Académicos'], 'indicadores' => ['ideas_suicidas', 'planificacion']],
            ['carnet' => 'DG24015', 'carrera_i' => 6, 'etiquetas' => ['Ideas Suicidas'], 'indicadores' => ['ideas_suicidas']],
        ];

        foreach ($perfiles as $index => $perfil) {
            $carrera = $carreras[$perfil['carrera_i'] % $carreras->count()];
            $this->seedCaso($perfil, $index, $area, $profesional, $carrera, $version);
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
        Carrera $carrera,
        RubricaVersion $version
    ): void {
        foreach ($perfil['etiquetas'] as $tag) {
            if (! in_array($tag, StorePacienteRequest::ETIQUETAS_VALIDAS, true)) {
                return;
            }
        }

        $paciente = Paciente::query()->updateOrCreate(
            ['carnet' => $perfil['carnet']],
            [
                'carrera_id' => $carrera->id,
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
        $consulta = Consulta::withTrashed()->whereKey($consultaId)->first();
        if ($consulta === null) {
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
        } else {
            $consulta->setRelation('expediente', $expediente);
            $consulta->indicadores_tendencia_suicida = $perfil['indicadores'];
            $consulta->crisis_psicologica = $perfil['indicadores'] !== [] ? 'Sí' : 'No';
            $consulta->save();
        }

        if ($perfil['indicadores'] === [] || ! in_array('ideas_suicidas', $perfil['indicadores'], true)) {
            return;
        }

        if (! in_array($perfil['carnet'], ['DG24006', 'DG24007', 'DG24012', 'DG24014'], true)) {
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
