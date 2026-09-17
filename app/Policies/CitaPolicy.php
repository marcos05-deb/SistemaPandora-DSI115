<?php

namespace App\Policies;

use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Cita;

class CitaPolicy
{
    public function create(Especialista $user, Expediente $expediente): bool
    {
        if ($expediente->estado === Expediente::ESTADO_CERRADO) {
            return false;
        }

        $hasValidRole = $user->hasRole('specialist')
            || $user->hasRole('area_coordinator');

        return $hasValidRole
            && $user->areas()->where('areas.id', $expediente->area_id)->exists();
    }

    /**
     * Registrar asistencia/ausencia: especialista asignado o coordinador del área.
     */
    public function update(Especialista $user, Cita $cita): bool
    {
        return $this->gestionaCitaEnSuAmbito($user, $cita);
    }

    public function reprogramar(Especialista $user, Cita $cita): bool
    {
        return $this->gestionaCitaEnSuAmbito($user, $cita)
            && $this->expedientePermiteGestion($cita);
    }

    public function cancelar(Especialista $user, Cita $cita): bool
    {
        return $this->gestionaCitaEnSuAmbito($user, $cita)
            && $this->expedientePermiteGestion($cita);
    }

    private function gestionaCitaEnSuAmbito(Especialista $user, Cita $cita): bool
    {
        $esEspecialistaAsignado = $user->hasRole('specialist')
            && $user->perfilesProfesionales()
                ->whereKey($cita->profesional_id)
                ->exists();

        $esCoordinadorAutorizado = $user->hasRole('area_coordinator')
            && $user->areas()->where('areas.id', $cita->area_id)->exists();

        return $esEspecialistaAsignado || $esCoordinadorAutorizado;
    }

    private function expedientePermiteGestion(Cita $cita): bool
    {
        $expediente = $cita->relationLoaded('expediente')
            ? $cita->expediente
            : $cita->expediente()->first();

        return $expediente !== null
            && $expediente->estado !== Expediente::ESTADO_CERRADO;
    }
}
