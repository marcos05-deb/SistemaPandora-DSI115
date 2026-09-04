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
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Especialista $user, Expediente $expediente): bool
    {
        // 1. Debe pertenecer al área del expediente
        $perteneceArea = $user->profesional && $user->profesional->area_id === $expediente->area_id;
        
        // 2. El expediente no puede estar cerrado
        $estaAbierto = $expediente->estado !== 'cerrado';

        return $perteneceArea && $estaAbierto;
    }

    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }
}
