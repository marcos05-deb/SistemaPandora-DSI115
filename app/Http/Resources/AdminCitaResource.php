<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vista supervisora del calendario: sin nombre ni carnet del estudiante.
 */
class AdminCitaResource extends JsonResource
{
    /**
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
            'expediente_id' => $this->expediente_id,
            'profesional_id' => $this->profesional_id,
            'area_id' => $this->area_id,

            'paciente' => $this->whenLoaded('expediente', function () {
                $carrera = $this->expediente->paciente?->carrera;
                $nombreCarrera = $carrera?->nombre;
                $nombreFacultad = $carrera?->facultad?->nombre;

                if ($nombreCarrera && $nombreFacultad) {
                    $etiqueta = "Estudiante de {$nombreCarrera}, {$nombreFacultad}";
                } elseif ($nombreCarrera) {
                    $etiqueta = "Estudiante de {$nombreCarrera}";
                } elseif ($nombreFacultad) {
                    $etiqueta = "Estudiante de {$nombreFacultad}";
                } else {
                    $etiqueta = 'Estudiante (carrera no disponible)';
                }

                return [
                    'etiqueta' => $etiqueta,
                    'carrera' => $nombreCarrera,
                    'facultad' => $nombreFacultad,
                ];
            }),

            'profesional' => $this->whenLoaded('profesional', function () {
                return [
                    'id' => $this->profesional->id,
                    'nombre' => $this->profesional->especialista->name ?? null,
                ];
            }),

            'area' => $this->whenLoaded('area', function () {
                return [
                    'id' => $this->area->id,
                    'nombre' => $this->area->nombre,
                ];
            }),
        ];
    }
}
