<?php

namespace App\Http\Resources;

use App\Exceptions\DecryptionException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PacienteResource extends JsonResource
{
    private function decrypt(string $field): ?string
    {
        try {
            return $this->{$field};
        } catch (DecryptionException) {
            return null;
        }
    }

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
            'nombre_completo' => $this->decrypt('nombre_completo'),
            'direccion' => $this->decrypt('direccion'),
            'sexo' => $this->sexo,
            'estado_civil' => $this->estado_civil,
            'fecha_nacimiento' => $this->decrypt('fecha_nacimiento'),
            'profesion_ocupacion' => $this->decrypt('profesion_ocupacion'),
            'fecha_primera_consulta' => $this->decrypt('fecha_primera_consulta'),
            'referido_por' => $this->decrypt('referido_por'),
            'llevado_por' => $this->decrypt('llevado_por'),
            'motivo_consulta' => $this->decrypt('motivo_consulta'),
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i'),
            
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
