<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Especialista;

class RubricaPolicy
{
    public function viewAny(Especialista $user): bool
    {
        return $user->hasRole('area_coordinator') && $user->profesional !== null;
    }

    public function create(Especialista $user): bool
    {
        return $user->hasRole('area_coordinator') && $user->profesional !== null;
    }
}
