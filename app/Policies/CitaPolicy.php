<?php

namespace App\Policies;

use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Cita;

class CitaPolicy
{
    public function create(Especialista $user, Expediente $expediente): bool
    {
        $hasValidRole = $user->hasRole('specialist')
            || $user->hasRole('area_coordinator');

        return $hasValidRole
            && $user->areas()->where('areas.id', $expediente->area_id)->exists();
    }

    public function update(Especialista $user, Cita $cita): bool
    {
        return $user->hasRole('specialist')
            && $user->perfilesProfesionales()
                ->whereKey($cita->profesional_id)
                ->exists();
    }

    public function reprogramar(Especialista $user, Cita $cita): bool
    {
        $esEspecialistaAsignado = $user->hasRole('specialist')
            && $user->perfilesProfesionales()
                ->whereKey($cita->profesional_id)
                ->exists();

        $esCoordinadorAutorizado = $user->hasRole('area_coordinator')
            && $user->areas()->where('areas.id', $cita->area_id)->exists();

        return $esEspecialistaAsignado || $esCoordinadorAutorizado;
    }

    public function cancelar(Especialista $user, Cita $cita): bool
    {
        return $this->reprogramar($user, $cita);
    }
}
