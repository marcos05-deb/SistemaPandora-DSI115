<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Especialista;

final class BitacoraPolicy
{
    public function viewAny(Especialista $user): bool
    {
        return $user->hasRole('sysadmin');
    }

    public function exportar(Especialista $user): bool
    {
        return $this->viewAny($user);
    }
}
