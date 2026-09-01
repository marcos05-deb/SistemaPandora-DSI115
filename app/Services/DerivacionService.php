<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expediente;
use Illuminate\Support\Facades\DB;
use App\Models\Paciente;

class DerivacionService
{
    /**
     * Deriva un paciente a un área clínica específica.
     */
    public function derivarPaciente(Paciente $paciente, int $areaId, string $profesionalId): Expediente
    {
        return DB::transaction(function () use ($paciente, $areaId, $profesionalId) {
            $expediente = new Expediente();
            $expediente->paciente_id = $paciente->codigo;
            $expediente->area_id = $areaId;
            $expediente->estado = 'abierto';
            $expediente->derivado_por_profesional_id = $profesionalId;
            $expediente->fecha_derivacion = now();
            
            // Si hay datos que copiar del paciente o expediente anterior, 
            // se haría aquí (por ahora vacío)
            
            $expediente->save();

            return $expediente;
        });
    }
}
