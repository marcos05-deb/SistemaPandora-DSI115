<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Especialista;
use Illuminate\Support\Carbon;

/**
 * Convierte estadísticas de ausencias en una alerta preventiva verificable (RP-05 / HU-12).
 */
final class AlertasPreventivasService
{
    public const UMBRAL_AUSENCIAS = 2;

    public const VENTANA_DIAS = 30;

    public function __construct(
        private readonly EstadisticasPreventivasService $estadisticas
    ) {}

    /**
     * @return array{
     *     activa: bool,
     *     total: int,
     *     umbral: int,
     *     ventana_dias: int,
     *     mensaje: string|null
     * }
     */
    public function alertaPaciente(Especialista $especialista, string $pacienteId): array
    {
        $desde = now()->subDays(self::VENTANA_DIAS);
        $total = $this->estadisticas->ausencias($especialista, $pacienteId, $desde)['total'];
        $activa = $total >= self::UMBRAL_AUSENCIAS;

        return [
            'activa' => $activa,
            'total' => $total,
            'umbral' => self::UMBRAL_AUSENCIAS,
            'ventana_dias' => self::VENTANA_DIAS,
            'mensaje' => $activa
                ? "Alerta preventiva: el paciente acumula {$total} ausencias en los últimos ".self::VENTANA_DIAS.' días.'
                : null,
        ];
    }

    public function requiereAlerta(Especialista $especialista, string $pacienteId, ?Carbon $desde = null): bool
    {
        $desde ??= now()->subDays(self::VENTANA_DIAS);

        return $this->estadisticas->ausencias($especialista, $pacienteId, $desde)['total'] >= self::UMBRAL_AUSENCIAS;
    }
}
