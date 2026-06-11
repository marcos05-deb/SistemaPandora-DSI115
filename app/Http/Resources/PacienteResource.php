<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PacienteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'codigo' => $this->codigo,
            'carnet' => $this->carnet,
            'nombre_completo' => $this->nombre_completo,
            'direccion' => $this->direccion,
            'sexo' => $this->sexo,
            'estado_civil' => $this->estado_civil,
            'fecha_nacimiento' => $this->fecha_nacimiento,
            'profesion_ocupacion' => $this->profesion_ocupacion,
            'fecha_primera_consulta' => $this->fecha_primera_consulta,
            'referido_por' => $this->referido_por,
            'llevado_por' => $this->llevado_por,
            'motivo_consulta' => $this->motivo_consulta,
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i'),
            
            // Relaciones explícitas (solo se cargan si están incluidas)
            'creator' => [
                'name' => $this->whenLoaded('creator', fn() => $this->creator->name ?? 'Desconocido')
            ],
            'contactos' => $this->whenLoaded('contactos'),
            'carrera' => $this->whenLoaded('carrera', function () {
                return [
                    'id' => $this->carrera->id,
                    'nombre' => $this->carrera->nombre,
                    'facultad' => $this->carrera->relationLoaded('facultad') ? $this->carrera->facultad : null,
                ];
            }),
            'expedientes' => $this->whenLoaded('expedientes'),
        ];
    }
}
