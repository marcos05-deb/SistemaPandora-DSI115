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

            $fechaConsulta = isset($data['fecha_consulta'])
                ? \Illuminate\Support\Carbon::parse($data['fecha_consulta'], config('app.timezone'))
                : now()->timezone(config('app.timezone'));

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
                'fecha_consulta'  => $fechaConsulta,
            ]);
            $consulta->save();

            if ($expediente->estado === Expediente::ESTADO_ABIERTO) {
                $expediente->estado = Expediente::ESTADO_EN_ATENCION;
                $expediente->save();
            }

            // PAN-15: fijar fecha de primera consulta clínica solo si aún está vacía.
            $paciente = $expediente->paciente()->lockForUpdate()->first();
            if ($paciente && blank($paciente->fecha_primera_consulta)) {
                $paciente->fecha_primera_consulta = $fechaConsulta->toDateString();
                $paciente->save();
            }

            return $consulta;
        });
    }
}
