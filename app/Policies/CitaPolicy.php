<?php

namespace App\Policies;

use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Cita;

class CitaPolicy
{
    public function create(Especialista $user, Expediente $expediente): bool
    {
        // Roles permitidos según US-11 (y referente para Trabajo Social)
        $hasValidRole = $user->hasRole('specialist') || 
                        $user->hasRole('area_coordinator');
                        
        // Solo los profesionales asignados a las áreas del expediente pueden agendar citas
        return $hasValidRole && $user->areas()->where('areas.id', $expediente->area_id)->exists();
    }

    public function update(Especialista $user, Cita $cita): bool
    {
        return $user->profesional && $user->profesional->id === $cita->profesional_id;
    }

    public function reprogramar(Especialista $user, Cita $cita): bool
    {
        $esEspecialistaAsignado = $user->hasRole('specialist')
            && $user->profesional
            && $user->profesional->id === $cita->profesional_id;

        $esCoordinadorAutorizado = $user->hasRole('area_coordinator')
            && $user->areas()->where('areas.id', $cita->area_id)->exists();

        return $esEspecialistaAsignado || $esCoordinadorAutorizado;
    }

    public function cancelar(Especialista $user, Cita $cita): bool
    {
        // Misma lógica de autorización que reprogramar
        return $this->reprogramar($user, $cita);
    }
}
