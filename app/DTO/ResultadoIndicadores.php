<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ResultadoIndicadores
{
    /**
     * @param  array{desde: string, hasta: string, area_id: int|null, indicadores: list<string>}  $filtros
     * @param  list<array{id: int, nombre: string}>  $areas
     * @param  list<array{id: int, nombre: string}>  $areasDisponibles
     * @param  array<string, array{etiqueta: string, categoria: string, valor: int|float|null, suprimido: bool, supresion: string|null, unidad: string}>  $totales
     * @param  list<array{indicador: string, etiqueta: string, categoria: string, area_id: int, area: string, segmento: string|null, segmento_etiqueta?: string, valor: int|float|null, suprimido: bool, supresion: string|null}>  $filas
     */
    public function __construct(
        public array $filtros,
        public array $areas,
        public array $areasDisponibles,
        public array $totales,
        public array $filas,
        public bool $sinDatos,
        public int $minimoCelda,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'filtros' => $this->filtros,
            'areas' => $this->areas,
            'areas_disponibles' => $this->areasDisponibles,
            'totales' => $this->totales,
            'filas' => $this->filas,
            'sin_datos' => $this->sinDatos,
            'minimo_celda' => $this->minimoCelda,
        ];
    }
}
