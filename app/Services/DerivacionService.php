<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expediente;
use App\Models\Paciente;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DerivacionService
{
    /**
     * Deriva un paciente a un área clínica específica.
     */
    public function derivarPaciente(
        Paciente $paciente,
        int $areaId,
        string $profesionalId,
        string $motivoDerivacion,
    ): Expediente {
        try {
            return DB::transaction(function () use ($paciente, $areaId, $profesionalId, $motivoDerivacion) {
                // Bloqueo pesimista: evita que dos requests concurrentes creen duplicados.
                if (Expediente::existeActivoPara($paciente->codigo, $areaId, forUpdate: true)) {
                    throw ValidationException::withMessages([
                        'area_id' => Expediente::MENSAJE_EXPEDIENTE_ACTIVO_DUPLICADO,
                    ]);
                }

                $expediente = new Expediente();
                $expediente->paciente_id = $paciente->codigo;
                // area_id debe asignarse antes de motivo_derivacion (cifrado por área).
                $expediente->area_id = $areaId;
                $expediente->estado = Expediente::ESTADO_ABIERTO;
                $expediente->derivado_por_profesional_id = $profesionalId;
                $expediente->fecha_derivacion = now();
                $expediente->motivo_derivacion = $motivoDerivacion;
                $expediente->save();

                return $expediente;
            });
        } catch (UniqueConstraintViolationException) {
            // Red de seguridad si el índice único parcial detecta la carrera.
            throw ValidationException::withMessages([
                'area_id' => Expediente::MENSAJE_EXPEDIENTE_ACTIVO_DUPLICADO,
            ]);
        }
    }
}
