<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SeguimientoPreventivo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertaDetalleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $terminal = in_array($this->estado, ['cerrada', 'descartada'], true);

        return [
            'id' => (string) $this->id,
            'senal' => $this->senal,
            'etiqueta_senal' => $this->resource::labelForSignal($this->senal),
            'valor_calculado' => $this->valor_calculado,
            'umbral_aplicado' => (float) $this->umbral,
            'version_rubrica' => $this->whenLoaded('versionRubrica', fn (): ?array => $this->versionRubrica === null ? null : [
                'numero' => $this->versionRubrica->numero_version,
                'vigente_desde' => $this->versionRubrica->vigente_desde?->toIso8601String(),
            ]),
            'estado' => $this->estado,
            'fecha_generacion' => $this->generada_en?->toIso8601String(),
            'paciente_carnet' => $this->whenLoaded('paciente', fn (): ?string => $this->paciente?->carnet),
            'responsable' => $this->whenLoaded('responsable', fn (): array => [
                'nombre' => $this->responsable?->especialista?->name,
            ]),
            'resultado_cierre' => $this->resultado_cierre,
            'cerrada_en' => $this->cerrada_en?->toIso8601String(),
            'cerrada_por' => $this->whenLoaded('cerradaPor', fn (): array => [
                'nombre' => $this->cerradaPor?->especialista?->name,
            ]),
            'justificacion_descarte' => $this->justificacion_descarte,
            'descartada_en' => $this->descartada_en?->toIso8601String(),
            'descartada_por' => $this->whenLoaded('descartadaPor', fn (): array => [
                'nombre' => $this->descartadaPor?->especialista?->name,
            ]),
            'seguimientos' => $this->whenLoaded('seguimientos', fn () => $this->seguimientos
                ->map(static fn (SeguimientoPreventivo $seguimiento): array => [
                    'id' => (string) $seguimiento->id,
                    'valoracion' => $seguimiento->valoracion,
                    'accion_preventiva' => $seguimiento->accion_preventiva,
                    'responsable' => ['nombre' => $seguimiento->responsable?->especialista?->name],
                    'registrado_por' => ['nombre' => $seguimiento->registradoPor?->especialista?->name],
                    'proxima_revision' => $seguimiento->proxima_revision?->toDateString(),
                    'registrado_en' => $seguimiento->created_at?->toIso8601String(),
                ])->values()),
            'proxima_revision' => $this->whenLoaded('seguimientos', fn (): ?string => $this->seguimientos
                ->last()?->proxima_revision?->toDateString()),
            'can' => [
                'seguimiento' => ! $terminal && ($user?->can('registrarSeguimiento', $this->resource) ?? false),
                'cerrar' => ! $terminal && ($user?->can('cerrar', $this->resource) ?? false),
                'descartar' => ! $terminal && ($user?->can('descartar', $this->resource) ?? false),
            ],
        ];
    }
}
