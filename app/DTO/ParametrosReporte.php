<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ParametrosReporte
{
    /**
     * @param  list<string>  $indicadores
     * @param  list<string>  $agruparPor
     */
    public function __construct(
        public string $desde,
        public string $hasta,
        public ?int $areaId,
        public bool $todasAreas,
        public array $indicadores,
        public array $agruparPor,
    ) {}

    /** @return array{desde: string, hasta: string, area: int|string, indicadores: list<string>, agrupar_por: list<string>} */
    public function toArray(): array
    {
        return [
            'desde' => $this->desde,
            'hasta' => $this->hasta,
            'area' => $this->todasAreas ? 'todas' : (int) $this->areaId,
            'indicadores' => $this->indicadores,
            'agrupar_por' => $this->agruparPor,
        ];
    }
}
