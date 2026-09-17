<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HistorialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fecha_consulta' => $this->fecha_consulta->format('Y-m-d H:i:s'),
            'motivo_consulta' => $this->motivo_consulta,
            'notas_clinicas' => $this->notas_clinicas,
            'diagnostico' => $this->diagnostico,
            'plan_atencion' => $this->plan_atencion,
            'evaluacion_inicial' => $this->evaluacion_inicial,
            'tecnica_utilizada' => $this->tecnica_utilizada,
            'tipo_atencion' => 'consulta',
            'profesional' => [
                'id' => $this->profesional->id,
                'nombre' => $this->profesional->especialista->name ?? 'Profesional Desconocido',
            ],
            'area' => [
                'id' => $this->expediente->area->id,
                'nombre' => $this->expediente->area->nombre,
                'color' => $this->expediente->area->color ?? 'bg-nord-4',
            ],
        ];
    }
}
