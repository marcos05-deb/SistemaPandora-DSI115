<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTO\ReporteGenerado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReporteGenerado */
final class ReporteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
