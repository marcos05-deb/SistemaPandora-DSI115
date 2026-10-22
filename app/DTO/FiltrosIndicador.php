<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class FiltrosIndicador
{
    /** @param list<string> $indicadores */
    public function __construct(
        public string $desde,
        public string $hasta,
        public ?int $areaId,
        public array $indicadores,
    ) {}

    /** @return array{desde: string, hasta: string, area_id: int|null, indicadores: list<string>} */
    public function toArray(): array
    {
        return [
            'desde' => $this->desde,
            'hasta' => $this->hasta,
            'area_id' => $this->areaId,
            'indicadores' => $this->indicadores,
        ];
    }
}
