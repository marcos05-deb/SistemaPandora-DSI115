<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Consulta;
use App\Models\Especialista;
use App\Models\Expediente;

class ConsultaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Especialista $user): bool
    {
        return $user->hasRole('specialist') || $user->hasRole('area_coordinator');
    }

    /**
     * Registrar consulta: rol clínico + área del expediente + expediente no cerrado.
     *
     * La comprobación de área es explícita en Policy (no depende solo de AreaScope).
     */
    public function create(Especialista $user, Expediente $expediente): bool
    {
        if (! $user->hasRole('specialist') && ! $user->hasRole('area_coordinator')) {
            return false;
        }

        if (! $user->profesional) {
            return false;
        }

        $areasAutorizadas = $user->areas()->pluck('areas.id')->map(fn ($id) => (int) $id);

        if ($areasAutorizadas->isEmpty() && $user->profesional->area_id) {
            $areasAutorizadas = collect([(int) $user->profesional->area_id]);
        }

        if (! $areasAutorizadas->contains((int) $expediente->area_id)) {
            return false;
        }

        if ($expediente->estado === Expediente::ESTADO_CERRADO) {
            return false;
        }

        return in_array($expediente->estado, Expediente::ESTADOS_ACTIVOS, true);
    }
}
