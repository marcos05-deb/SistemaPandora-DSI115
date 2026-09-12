<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Consulta;
use App\Models\Expediente;
use Illuminate\Support\Facades\DB;

class ConsultaService
{
    public function registrarConsulta(Expediente $expediente, array $data, string $profesionalId): Consulta
    {
        return DB::transaction(function () use ($expediente, $data, $profesionalId) {
            $consulta = new Consulta();
            $consulta->expediente_id = $expediente->id;
            $consulta->setRelation('expediente', $expediente);
            
            $consulta->fill([
                'profesional_id'  => $profesionalId,
                'motivo_consulta' => $data['motivo_consulta'],
                'notas_clinicas'  => $data['notas_clinicas'] ?? null,
                'diagnostico'     => $data['diagnostico'] ?? null,
                'plan_atencion'   => $data['plan_atencion'],
                'tecnica_utilizada' => $data['tecnica_utilizada'],
                'antecedentes_problema' => $data['antecedentes_problema'] ?? null,
                'etiquetas_motivo' => $data['etiquetas_motivo'] ?? [],
                'evaluacion_inicial' => $data['evaluacion_inicial'] ?? null,
                'fecha_consulta'  => $data['fecha_consulta'] ?? now(),
            ]);
            $consulta->save();

            if ($expediente->estado === Expediente::ESTADO_ABIERTO) {
                $expediente->estado = Expediente::ESTADO_EN_ATENCION;
                $expediente->save();
            }

            return $consulta;
        });
    }
}
