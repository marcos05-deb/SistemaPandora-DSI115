<?php

namespace App\Http\Resources;

use App\Exceptions\DecryptionException;
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
        $user = $request->user();

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
            'motivo_reprogramacion' => $this->motivo_reprogramacion,

            // Identificación legible para agenda clínica (carnet + nombre). UUID queda como dato técnico.
            'paciente' => $this->whenLoaded('expediente', function () {
                $paciente = $this->expediente->paciente;

                return [
                    'codigo' => $paciente?->codigo,
                    'carnet' => $paciente?->carnet,
                    'nombre_completo' => $this->decryptPacienteNombre($paciente),
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

            'registrado_por' => $this->whenLoaded('registradoPor', function () {
                return [
                    'id' => $this->registradoPor->id,
                    'nombre' => $this->registradoPor->especialista->name ?? null,
                ];
            }),

            'cita_origen' => $this->whenLoaded('citaOrigen', function () {
                if (! $this->citaOrigen) {
                    return null;
                }

                return [
                    'id' => $this->citaOrigen->id,
                    'fecha_hora' => $this->citaOrigen->fecha_hora?->toIso8601String(),
                    'motivo_reprogramacion' => $this->citaOrigen->motivo_reprogramacion,
                ];
            }),

            'cancelado_por_profesional_id' => $this->cancelado_por_profesional_id,
            'fecha_cancelacion' => $this->fecha_cancelacion?->toIso8601String(),
            'motivo_cancelacion' => $this->when(
                $this->estado === 'cancelada',
                $this->motivo_cancelacion
            ),

            'can' => [
                'registrar_asistencia' => $user ? $user->can('update', $this->resource) : false,
                'reprogramar' => $user ? $user->can('reprogramar', $this->resource) : false,
                'cancelar' => $user ? $user->can('cancelar', $this->resource) : false,
            ],
        ];
    }

    private function decryptPacienteNombre(?\App\Models\Paciente $paciente): ?string
    {
        if (! $paciente) {
            return null;
        }

        try {
            return $paciente->nombre_completo;
        } catch (DecryptionException) {
            return null;
        }
    }
}
