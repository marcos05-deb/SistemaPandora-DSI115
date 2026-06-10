<?php

namespace App\Policies;

use App\Models\Especialista;
use App\Models\Paciente;
use Illuminate\Auth\Access\Response;

class PacientePolicy
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
    public function view(Especialista $especialista, Paciente $paciente): bool
    {
        if ($especialista->hasRole('psychosocial_referent')) {
            return $paciente->creado_por_profesional_id === $especialista->profesional->id;
        }

        return $especialista->hasRole('specialist') || $especialista->hasRole('area_coordinator');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Especialista $especialista): bool
    {
        return $especialista->hasRole('psychosocial_referent');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Especialista $especialista, Paciente $paciente): bool
    {
        if ($especialista->hasRole('psychosocial_referent')) {
            return $paciente->creado_por_profesional_id === $especialista->profesional->id;
        }
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Especialista $especialista, Paciente $paciente): bool
    {
        return false; // Soft deletes / auditable actions will be phase 3
    }
}
