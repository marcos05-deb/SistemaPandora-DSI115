<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RubricaVariableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo_senal' => $this->tipo_senal,
            'clave_indicador' => $this->clave_indicador,
            'modo_calculo' => $this->modo_calculo,
            'ventana_dias' => $this->ventana_dias,
            'ponderacion' => $this->ponderacion,
            'umbral' => $this->umbral,
        ];
    }
}
