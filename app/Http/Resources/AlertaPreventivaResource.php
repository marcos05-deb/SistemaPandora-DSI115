<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertaPreventivaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'senal' => $this->senal,
            'valor_calculado' => $this->valor_calculado,
            'umbral_aplicado' => (float) $this->umbral,
            'estado' => $this->estado,
            'rubrica_version_numero' => $this->versionRubrica?->numero_version,
            'fecha_generacion' => $this->generada_en?->toIso8601String(),
            'paciente_carnet' => $this->whenLoaded('paciente', fn () => $this->paciente?->carnet),
        ];
    }
}
