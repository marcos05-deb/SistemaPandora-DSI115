<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CitaResource extends JsonResource
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
            'fecha_hora' => $this->fecha_hora->toIso8601String(),
            'estado' => $this->estado,
            'motivo' => $this->motivo,
            'asistio' => $this->asistio,
            'fecha_registro_asistencia' => $this->fecha_registro_asistencia,
            'cita_origen_id' => $this->cita_origen_id,
            'expediente_id' => $this->expediente_id,
            'profesional_id' => $this->profesional_id,
            'area_id' => $this->area_id,
            
            // Retornamos paciente anidado omitiendo PHi (nombre_completo, etc.)
            'paciente' => $this->whenLoaded('expediente', function () {
                return [
                    'codigo' => $this->expediente->paciente?->codigo,
                    'carnet' => $this->expediente->paciente?->carnet,
                ];
            }),

            'profesional' => $this->whenLoaded('profesional', function () {
                return [
                    'id' => $this->profesional->id,
                    'nombre' => $this->profesional->especialista->name ?? null,
                ];
            }),
            
            'registrado_por' => $this->whenLoaded('registradoPor', function () {
                return [
                    'id' => $this->registradoPor->id,
                    'nombre' => $this->registradoPor->especialista->name ?? null,
                ];
            }),
        ];
    }
}
