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
                'notas_clinicas'  => $data['notas_clinicas'],
                'diagnostico'     => $data['diagnostico'],
                'fecha_consulta'  => now(),
            ]);
            $consulta->save();

            if ($expediente->estado === 'abierto') {
                $expediente->estado = 'en_atencion';
                $expediente->save();
            }

            return $consulta;
        });
    }
}
