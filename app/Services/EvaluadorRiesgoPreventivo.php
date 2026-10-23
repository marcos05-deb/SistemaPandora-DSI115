<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AlertaPreventiva;
use App\Models\Cita;
use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\EstadisticaPreventivaAusencia;
use App\Models\EvaluacionRiesgo;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\RubricaVariable;
use App\Notifications\AlertaPreventivaCreada;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

final class EvaluadorRiesgoPreventivo
{
    public function __construct(private readonly RubricaResolver $rubricaResolver) {}

    public function evaluarConsulta(Consulta $consulta): void
    {
        $consulta->loadMissing('expediente');
        if (! $consulta->expediente) {
            return;
        }

        $indicadores = is_array($consulta->indicadores_tendencia_suicida)
            ? $consulta->indicadores_tendencia_suicida
            : [];
        $senales = [];
        if ($this->crisisMarcada($consulta->crisis_psicologica)) {
            $senales[] = 'crisis_psicologica';
        }
        if ($indicadores !== []) {
            $senales[] = 'tendencia_suicida';
        }
        if ($senales === []) {
            return;
        }

        $this->evaluar(
            $consulta->expediente->paciente_id,
            (int) $consulta->expediente->area_id,
            Consulta::class,
            (string) $consulta->id,
            $consulta->profesional_id,
            $senales,
            $indicadores,
        );
    }

    public function evaluarAusencia(Cita $cita): void
    {
        $cita->loadMissing('expediente');
        if (! $cita->expediente) {
            return;
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

        $this->evaluar(
            $cita->expediente->paciente_id,
            (int) $cita->area_id,
            Cita::class,
            (string) $cita->id,
            $cita->registrado_por_profesional_id ?? $cita->profesional_id,
            ['ausencia_cita'],
        );
    }

    public function reevaluar(?string $pacienteId = null): int
    {
        $evaluados = 0;

        Consulta::query()
            ->with('expediente')
            ->whereHas('expediente', fn (Builder $query) => $query
                ->when($pacienteId !== null, fn (Builder $query) => $query->where('paciente_id', $pacienteId)))
            ->chunkById(100, function (EloquentCollection $consultas) use (&$evaluados): void {
                foreach ($consultas as $consulta) {
                    $indicadores = $consulta->indicadores_tendencia_suicida;
                    if (! $this->crisisMarcada($consulta->crisis_psicologica)
                        && (! is_array($indicadores) || $indicadores === [])) {
                        continue;
                    }

                    $evaluados++;
                    $this->evaluarConsulta($consulta);
                }
            });

        EstadisticaPreventivaAusencia::query()
            ->with('cita.expediente')
            ->when($pacienteId !== null, fn (Builder $query) => $query->where('paciente_id', $pacienteId))
            ->chunkById(100, function (EloquentCollection $ausencias) use (&$evaluados): void {
                foreach ($ausencias as $ausencia) {
                    $cita = $ausencia->cita;
                    if (! $cita?->expediente) {
                        continue;
                    }

                    $evaluados++;
                    $this->evaluarAusencia($cita);
                }
            });

        return $evaluados;
    }

    private function evaluar(
        string $pacienteId,
        int $areaId,
        string $origenTipo,
        string $origenId,
        ?string $profesionalRelevanteId,
        array $senales,
        array $indicadores = [],
    ): void {
        DB::transaction(function () use ($pacienteId, $areaId, $origenTipo, $origenId, $profesionalRelevanteId, $senales, $indicadores): void {
            $paciente = Paciente::query()->whereKey($pacienteId)->lockForUpdate()->first();
            if (! $paciente) {
                return;
            }

            $ahora = now();
            $version = $this->rubricaResolver->vigente($ahora);
            if (! $version) {
                EvaluacionRiesgo::query()->firstOrCreate(
                    [
                        'evento_origen_tipo' => $origenTipo,
                        'evento_origen_id' => $origenId,
                        'area_id' => $areaId,
                        'estado_evaluacion' => 'omitida',
                    ],
                    [
                        'paciente_id' => $pacienteId,
                        'rubrica_version_id' => null,
                        'disparador' => 'sin_rubrica_vigente',
                        'puntaje' => null,
                        'detalle' => null,
                        'evaluada_en' => $ahora,
                    ]
                );

                return;
            }

            $variables = RubricaVariable::ordenarParaEvaluacion($version->variables);
            $senales = array_values(array_unique(array_intersect($senales, RubricaVariable::TIPOS_SENAL)));
            $indicadoresIncluidos = $variables
                ->where('tipo_senal', 'tendencia_suicida')
                ->pluck('clave_indicador')
                ->filter()
                ->all();
            if (in_array('tendencia_suicida', $senales, true)) {
                $indicadores = array_values(array_intersect($indicadores, $indicadoresIncluidos));
                if ($indicadores === []) {
                    $senales = array_values(array_diff($senales, ['tendencia_suicida']));
                }
            }
            $senales = array_values(array_filter(
                $senales,
                static fn (string $senal): bool => $variables->contains('tipo_senal', $senal),
            ));
            if ($senales === []) {
                return;
            }

            $resultadosPorSenal = [];
            foreach ($senales as $senal) {
                $reglas = $variables->where('tipo_senal', $senal)->values();
                if ($senal === 'tendencia_suicida') {
                    $reglas = $reglas->whereIn('clave_indicador', $indicadores)->values();
                }
                if ($reglas->isEmpty()) {
                    continue;
                }

                $resultadosPorSenal[$senal] = $reglas->map(function (RubricaVariable $regla) use ($pacienteId, $areaId, $ahora): array {
                    $ocurrencias = match ($regla->tipo_senal) {
                        'crisis_psicologica' => $this->contarCrisis($pacienteId, $areaId, $regla, $ahora),
                        'ausencia_cita' => $this->contarAusencias($pacienteId, $areaId, $regla, $ahora),
                        'tendencia_suicida' => $this->contarIndicador($pacienteId, $areaId, (string) $regla->clave_indicador, $regla, $ahora),
                    };
                    $valor = $regla->modo_calculo === 'puntaje'
                        ? $ocurrencias * (float) $regla->ponderacion
                        : (float) $ocurrencias;

                    return [
                        'clave_indicador' => $regla->clave_indicador,
                        'valor' => $valor,
                        'umbral' => (float) $regla->umbral,
                        'modo_calculo' => $regla->modo_calculo,
                        'ventana_dias' => $regla->ventana_dias,
                        'ponderacion' => (float) $regla->ponderacion,
                    ];
                });
            }

            if ($resultadosPorSenal === []) {
                return;
            }

            $disparador = count($resultadosPorSenal) === 1
                ? array_key_first($resultadosPorSenal)
                : 'consulta_senales';
            $todasLasReglas = collect($resultadosPorSenal)->flatten(1);
            $evaluacion = EvaluacionRiesgo::query()->firstOrCreate(
                [
                    'evento_origen_tipo' => $origenTipo,
                    'evento_origen_id' => $origenId,
                    'rubrica_version_id' => $version->id,
                    'estado_evaluacion' => 'evaluada',
                ],
                [
                    'paciente_id' => $pacienteId,
                    'area_id' => $areaId,
                    'disparador' => $disparador,
                    'puntaje' => $todasLasReglas->max('valor') ?? 0,
                    'detalle' => json_encode($resultadosPorSenal, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'evaluada_en' => $ahora,
                ]
            );

            foreach ($resultadosPorSenal as $senal => $resultados) {
                $reglaDisparadora = $resultados
                    ->filter(fn (array $resultado): bool => $resultado['valor'] >= $resultado['umbral'])
                    ->first();

                if ($reglaDisparadora !== null) {
                    $this->crearAlerta(
                        $pacienteId,
                        $areaId,
                        $senal,
                        $reglaDisparadora,
                        $evaluacion,
                        $version->id,
                        $profesionalRelevanteId,
                        $ahora
                    );
                }
            }
        });
    }

    private function contarCrisis(string $pacienteId, int $areaId, RubricaVariable $regla, Carbon $ahora): int
    {
        $query = Consulta::query()
            ->whereHas('expediente', fn ($expediente) => $expediente
                ->where('paciente_id', $pacienteId)
                ->where('area_id', $areaId));

        if ($regla->modo_calculo === 'frecuencia') {
            $query->whereBetween('fecha_consulta', [$ahora->copy()->subDays($regla->ventana_dias), $ahora]);
        } elseif ($regla->modo_calculo === 'acumulacion') {
            $desde = $this->fechaInicioAcumulacion($pacienteId, $areaId);
            if ($desde === null) {
                return 0;
            }
            $query->where('fecha_consulta', '>=', $desde);
        }

        return $query->get(['id', 'expediente_id', 'crisis_psicologica'])
            ->filter(fn (Consulta $consulta): bool => in_array(
                mb_strtolower(trim((string) $consulta->crisis_psicologica)),
                ['1', 'true', 'si', 'sí', 'yes'],
                true
            ))
            ->count();
    }

    private function contarAusencias(string $pacienteId, int $areaId, RubricaVariable $regla, Carbon $ahora): int
    {
        $query = EstadisticaPreventivaAusencia::query()
            ->where('paciente_id', $pacienteId)
            ->where('area_id', $areaId);

        if ($regla->modo_calculo === 'frecuencia') {
            $query->whereBetween('fecha_registro', [$ahora->copy()->subDays($regla->ventana_dias), $ahora]);
        } elseif ($regla->modo_calculo === 'acumulacion') {
            $desde = $this->fechaInicioAcumulacion($pacienteId, $areaId);
            if ($desde === null) {
                return 0;
            }
            $query->where('fecha_registro', '>=', $desde);
        }

        return $query->count();
    }

    private function contarIndicador(
        string $pacienteId,
        int $areaId,
        string $claveIndicador,
        RubricaVariable $regla,
        Carbon $ahora,
    ): int {
        $query = Consulta::query()
            ->whereHas('expediente', fn ($expediente) => $expediente
                ->where('paciente_id', $pacienteId)
                ->where('area_id', $areaId));

        if ($regla->modo_calculo === 'frecuencia') {
            $query->whereBetween('fecha_consulta', [$ahora->copy()->subDays($regla->ventana_dias), $ahora]);
        } elseif ($regla->modo_calculo === 'acumulacion') {
            $desde = $this->fechaInicioAcumulacion($pacienteId, $areaId);
            if ($desde === null) {
                return 0;
            }
            $query->where('fecha_consulta', '>=', $desde);
        }

        return $query->get(['id', 'expediente_id', 'indicadores_tendencia_suicida'])
            ->filter(fn (Consulta $consulta): bool => is_array($consulta->indicadores_tendencia_suicida)
                && in_array($claveIndicador, $consulta->indicadores_tendencia_suicida, true))
            ->count();
    }

    private function fechaInicioAcumulacion(string $pacienteId, int $areaId): ?Carbon
    {
        $fecha = Expediente::query()
            ->where('paciente_id', $pacienteId)
            ->where('area_id', $areaId)
            ->where('estado', '!=', Expediente::ESTADO_CERRADO)
            ->whereNotNull('fecha_derivacion')
            ->orderByDesc('fecha_derivacion')
            ->value('fecha_derivacion');

        return $fecha === null ? null : Carbon::parse($fecha, config('app.timezone'));
    }

    private function crisisMarcada(mixed $value): bool
    {
        return in_array(mb_strtolower(trim((string) $value)), ['1', 'true', 'si', 'sí', 'yes'], true);
    }

    /** @param array{clave_indicador: string|null, valor: int, umbral: float, modo_calculo: string, ventana_dias: int|null} $regla */
    private function crearAlerta(
        string $pacienteId,
        int $areaId,
        string $senal,
        array $regla,
        EvaluacionRiesgo $evaluacion,
        string $versionId,
        ?string $profesionalRelevanteId,
        Carbon $ahora
    ): void {
        if (AlertaPreventiva::query()
            ->where('paciente_id', $pacienteId)
            ->where('area_id', $areaId)
            ->where('senal', $senal)
            ->whereIn('estado', ['activa', 'en_seguimiento'])
            ->exists()) {
            return;
        }

        $alerta = AlertaPreventiva::query()->firstOrCreate(
            [
                'evaluacion_riesgo_id' => $evaluacion->id,
                'senal' => $senal,
            ],
            [
                'paciente_id' => $pacienteId,
                'area_id' => $areaId,
                'rubrica_version_id' => $versionId,
                'valor_calculado' => (string) $regla['valor'],
                'umbral' => $regla['umbral'],
                'estado' => 'activa',
                'responsable_profesional_id' => $this->responsableId($areaId, $profesionalRelevanteId),
                'generada_en' => $ahora,
            ]
        );

        if ($alerta->wasRecentlyCreated) {
            $destinatarios = Especialista::query()
                ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['area_coordinator', 'specialist']))
                ->whereDoesntHave('roles', fn ($query) => $query->whereIn('slug', ['sysadmin', 'psychosocial_referent']))
                ->whereHas('perfilesProfesionales', fn ($query) => $query->where('area_id', $areaId))
                ->get();

            Notification::send($destinatarios, new AlertaPreventivaCreada($alerta));
        }
    }

    private function responsableId(int $areaId, ?string $profesionalRelevanteId): string
    {
        if ($profesionalRelevanteId && Profesional::query()
            ->whereKey($profesionalRelevanteId)
            ->where('area_id', $areaId)
            ->exists()) {
            return $profesionalRelevanteId;
        }

        $coordinadorId = Profesional::query()
            ->where('area_id', $areaId)
            ->whereHas('especialista.roles', fn ($query) => $query->where('slug', 'area_coordinator'))
            ->orderByDesc('created_at')
            ->value('id');

        if (! is_string($coordinadorId)) {
            throw new RuntimeException('No hay profesional responsable configurado para el área.');
        }

        return $coordinadorId;
    }
}
