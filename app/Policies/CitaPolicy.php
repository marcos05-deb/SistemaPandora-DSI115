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
                        $user->hasRole('area_coordinator') || 
                        $user->hasRole('psychosocial_referent');
                        
        // Solo los profesionales asignados a las áreas del expediente pueden agendar citas
        return $hasValidRole && $user->areas()->where('areas.id', $expediente->area_id)->exists();
    }
}
