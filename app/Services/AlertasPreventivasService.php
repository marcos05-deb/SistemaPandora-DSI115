<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\RubricaVariable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Convierte estadísticas de ausencias en una alerta preventiva verificable (RP-05 / HU-12).
 */
final class AlertasPreventivasService
{
    public const UMBRAL_AUSENCIAS = 2;

    public const VENTANA_DIAS = 30;

    public function __construct(
        private readonly EstadisticasPreventivasService $estadisticas,
        private readonly RubricaResolver $rubricaResolver
    ) {}

    /**
     * @return array{
     *     activa: bool,
     *     total: int|float,
     *     umbral: int|float|null,
     *     ventana_dias: int|null,
     *     modo_calculo: string|null,
     *     mensaje: string|null
     * }
     */
    public function alertaPaciente(
        Especialista $especialista,
        string $pacienteId,
        ?Carbon $desde = null,
        ?int $areaId = null,
    ): array {
        $ahora = now();
        $desdeExplicito = $desde !== null;
        try {
            $version = $this->rubricaResolver->vigente($ahora);
        } catch (Throwable $exception) {
            Log::warning('Preventive alert summary could not resolve the current rubric.', [
                'exception' => $exception::class,
            ]);
            $version = null;
        }

        $reglas = $version
            ? RubricaVariable::ordenarParaEvaluacion($version->variables->where('tipo_senal', 'ausencia_cita'))
            : collect();
        if ($reglas->isEmpty()) {
            return [
                'activa' => false,
                'total' => 0,
                'umbral' => null,
                'ventana_dias' => null,
                'modo_calculo' => null,
                'mensaje' => null,
            ];
        }

        $areaId ??= Expediente::query()
            ->where('paciente_id', $pacienteId)
            ->orderByDesc('updated_at')
            ->value('area_id');

        $resultados = $reglas->map(function (RubricaVariable $regla) use ($especialista, $pacienteId, $areaId, $desde, $desdeExplicito, $ahora): array {
            $desdeRegla = $desde;
            if ($desdeRegla === null && $regla->modo_calculo === 'frecuencia') {
                $desdeRegla = $ahora->copy()->subDays((int) $regla->ventana_dias);
            } elseif ($desdeRegla === null && $regla->modo_calculo === 'acumulacion' && $areaId !== null) {
                $fechaDerivacion = Expediente::query()
                    ->where('paciente_id', $pacienteId)
                    ->where('area_id', $areaId)
                    ->where('estado', '!=', Expediente::ESTADO_CERRADO)
                    ->whereNotNull('fecha_derivacion')
                    ->orderByDesc('fecha_derivacion')
                    ->value('fecha_derivacion');

                $desdeRegla = $fechaDerivacion === null
                    ? null
                    : Carbon::parse($fechaDerivacion, config('app.timezone'));
            }

            $sinFechaDeAcumulacion = $regla->modo_calculo === 'acumulacion' && $desdeRegla === null;
            $items = $areaId !== null && ! $sinFechaDeAcumulacion
                ? $this->estadisticas->ausencias(
                    $especialista,
                    $pacienteId,
                    $desdeRegla,
                    $desdeExplicito || $regla->modo_calculo === 'frecuencia' ? $ahora : null,
                )['items']->where('area_id', $areaId)
                : collect();
            $total = $regla->modo_calculo === 'puntaje'
                ? $items->count() * (float) $regla->ponderacion
                : $items->count();

            return [
                'regla' => $regla,
                'total' => $total,
                'umbral' => (float) $regla->umbral,
                'activa' => $total >= (float) $regla->umbral,
                'desde' => $desdeRegla,
            ];
        });
        $resultado = $resultados->first(fn (array $resultado): bool => $resultado['activa'])
            ?? $resultados->first();
        $regla = $resultado['regla'];
        $desde = $resultado['desde'];
        $total = $resultado['total'];
        $activa = $resultado['activa'];
        $umbralValor = $resultado['umbral'];
        $umbral = floor($umbralValor) === $umbralValor ? (int) $umbralValor : $umbralValor;
        $ventanaDias = $regla->modo_calculo === 'frecuencia' ? $regla->ventana_dias : null;
        $mensaje = match ($regla->modo_calculo) {
            'frecuencia' => $desdeExplicito
                ? "Alerta preventiva: el paciente acumula {$total} ausencias en el rango seleccionado."
                : "Alerta preventiva: el paciente acumula {$total} ausencias en los últimos {$ventanaDias} días.",
            'acumulacion' => "Alerta preventiva: el paciente acumula {$total} ausencias desde la fecha de derivación.",
            'puntaje' => "Alerta preventiva: el paciente alcanzó un puntaje de {$total} por ausencias.",
            default => null,
        };

        return [
            'activa' => $activa,
            'total' => $total,
            'umbral' => $umbral,
            'ventana_dias' => $ventanaDias,
            'modo_calculo' => $regla->modo_calculo,
            'mensaje' => $activa ? $mensaje : null,
        ];
    }

    public function requiereAlerta(
        Especialista $especialista,
        string $pacienteId,
        ?Carbon $desde = null,
        ?int $areaId = null,
    ): bool {
        return $this->alertaPaciente($especialista, $pacienteId, $desde, $areaId)['activa'];
    }
}
