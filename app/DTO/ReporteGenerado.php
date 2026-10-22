<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ReporteGenerado
{
    /**
     * @param  array{desde: string, hasta: string, area: int|string, indicadores: list<string>, agrupar_por: list<string>}  $parametrosAplicados
     * @param  list<array{id: string, etiqueta: string, categoria: string, formula: string, fuente: string}>  $columnas
     * @param  list<array{dimensiones: array<string, int|string>, valores: array<string, array<string, mixed>>}>  $grupos
     * @param  array<string, array<string, mixed>>  $totales
     */
    public function __construct(
        public array $parametrosAplicados,
        public string $generadoEn,
        public string $generadoPor,
        public array $columnas,
        public array $grupos,
        public array $totales,
        public bool $sinDatos,
        public int $minimoCelda,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'parametros_aplicados' => $this->parametrosAplicados,
            'generado_en' => $this->generadoEn,
            'generado_por' => $this->generadoPor,
            'columnas' => $this->columnas,
            'grupos' => $this->grupos,
            'totales' => $this->totales,
            'sin_datos' => $this->sinDatos,
            'minimo_celda' => $this->minimoCelda,
        ];
    }
}
