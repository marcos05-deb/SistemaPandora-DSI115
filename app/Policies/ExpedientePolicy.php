<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;

class ExpedientePolicy
{
    public function viewAny(Especialista $especialista): bool
    {
        return $especialista->hasRole('psychosocial_referent') ||
               $especialista->hasRole('specialist') ||
               $especialista->hasRole('area_coordinator');
    }

    public function view(Especialista $especialista, Expediente $expediente): bool
    {
        if ($especialista->hasRole('psychosocial_referent')) {
            return $expediente->paciente->creado_por_profesional_id === $especialista->profesional->id;
        }

        return $especialista->hasRole('specialist') || $especialista->hasRole('area_coordinator');
    }

    public function create(Especialista $especialista): bool
    {
        return $especialista->hasRole('specialist') || $especialista->hasRole('area_coordinator');
    }

    /**
     * Actualizar campos clínicos: especialista/coordinador del área, expediente no cerrado.
     */
    public function update(Especialista $especialista, Expediente $expediente): bool
    {
        return $this->close($especialista, $expediente);
    }

    public function delete(Especialista $especialista, Expediente $expediente): bool
    {
        return false;
    }

    /**
     * Derivar: rol referente + responsabilidad/autorización vigente sobre el paciente.
     */
    public function derivar(Especialista $especialista, Paciente $paciente): bool
    {
        if (! $especialista->hasRole('psychosocial_referent')) {
            return false;
        }

        return $paciente->puedeSerDerivadoPor($especialista);
    }

    /**
     * Cerrar expediente: especialista o coordinador del área (HU-10 / Jira).
     */
    public function close(Especialista $user, Expediente $expediente): bool
    {
        if ($expediente->estado === Expediente::ESTADO_CERRADO) {
            return false;
        }

        if (! $user->profesional) {
            return false;
        }

        $mismaArea = $user->areas()->where('areas.id', $expediente->area_id)->exists()
            || $user->profesional->area_id === $expediente->area_id;

        if (! $mismaArea) {
            return false;
        }

        return $user->hasRole('specialist') || $user->hasRole('area_coordinator');
    }
}
