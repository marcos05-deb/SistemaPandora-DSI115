<?php

namespace App\Policies;

use App\Models\Especialista;
use App\Models\Expediente;
use Illuminate\Auth\Access\Response;

class ExpedientePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Especialista $especialista): bool
    {
        return $especialista->hasRole('psychosocial_referent') || 
               $especialista->hasRole('specialist') || 
               $especialista->hasRole('area_coordinator');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Especialista $especialista, Expediente $expediente): bool
    {
        if ($especialista->hasRole('psychosocial_referent')) {
            return $expediente->paciente->creado_por_profesional_id === $especialista->profesional->id;
        }

        // En HU-03 Especialista/Coordinador se validará el AreaScope. 
        // A nivel de policy general, permitimos si tienen rol clínico base.
        return $especialista->hasRole('specialist') || $especialista->hasRole('area_coordinator');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Especialista $especialista): bool
    {
        return $especialista->hasRole('specialist') || $especialista->hasRole('area_coordinator');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Especialista $especialista, Expediente $expediente): bool
    {
        return $this->view($especialista, $expediente);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Especialista $especialista, Expediente $expediente): bool
    {
        return false;
    }
}
