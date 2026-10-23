<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTO\ResultadoIndicadores;
use App\Enums\TipoIndicador;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ResultadoIndicadores */
final class IndicadoresResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource->toArray(),
            'catalogo' => array_map(static fn (TipoIndicador $indicador): array => [
                'id' => $indicador->value,
                'etiqueta' => $indicador->etiqueta(),
                'definicion' => $indicador->definicion(),
                'formula' => $indicador->formula(),
                'fuente' => $indicador->fuente(),
                'categoria' => $indicador->categoria(),
            ], TipoIndicador::cases()),
        ];
    }
}
