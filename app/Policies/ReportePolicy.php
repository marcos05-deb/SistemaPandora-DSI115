<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Especialista;

final readonly class ReportePolicy
{
    public function __construct(private IndicadorPolicy $indicadores) {}

    public function verFormulario(Especialista $user): bool
    {
        return $this->indicadores->ver($user);
    }

    public function generar(Especialista $user, ?int $areaId, bool $todasAreas): bool
    {
        if ($todasAreas) {
            return $this->indicadores->verTodasAreas($user);
        }

        return $areaId !== null && $this->indicadores->ver($user, $areaId);
    }

    public function exportar(Especialista $user, ?int $areaId, bool $todasAreas): bool
    {
        return $this->generar($user, $areaId, $todasAreas);
    }
}
