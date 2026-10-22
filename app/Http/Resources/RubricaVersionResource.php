<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RubricaVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_version' => $this->numero_version,
            'vigente_desde' => $this->vigente_desde?->toIso8601String(),
            'umbral_puntaje_global' => $this->umbral_puntaje_global,
            'justificacion_cambio' => $this->justificacion_cambio,
            'validada_en' => $this->validada_en?->toDateString(),
            'referencia_validacion' => $this->referencia_validacion,
            'es_demo' => $this->es_demo,
            'created_at' => $this->created_at?->toIso8601String(),
            'es_vigente' => (bool) $this->es_vigente,
            'estado_vigencia' => $this->es_demo
                ? 'demo'
                : ($this->es_vigente
                    ? 'vigente'
                    : ($this->vigente_desde?->isFuture() ? 'programada' : 'historica')),
            'responsable_profesional' => $this->whenLoaded('creadaPor', fn () => $this->creadaPor ? [
                'id' => $this->creadaPor->id,
                'nombre' => $this->creadaPor->especialista?->name,
            ] : null),
            'variables' => RubricaVariableResource::collection($this->whenLoaded('variables'))->resolve(),
        ];
    }
}
