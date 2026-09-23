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

        if ($especialista->hasRole('specialist') || $especialista->hasRole('area_coordinator')) {
            // Un especialista solo puede ver al paciente si tiene un expediente en su misma área (AreaScope se encarga del filtrado)
            return $paciente->expedientes()->exists();
        }

        return false;
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
     *
     * Solo el referente psicosocial que registró al paciente puede corregir
     * datos generales. Tras la primera corrección, el servicio exige un permiso
     * admin aprobado (consumible). Especialistas, coordinadores y administradores: denegado.
     */
    public function update(Especialista $especialista, Paciente $paciente): bool
    {
        if (! $especialista->hasRole('psychosocial_referent')) {
            return false;
        }

        if (! $especialista->profesional) {
            return false;
        }

        return $paciente->creado_por_profesional_id === $especialista->profesional->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Especialista $especialista, Paciente $paciente): bool
    {
        return false; // Eliminación de pacientes no está habilitada
    }
}
