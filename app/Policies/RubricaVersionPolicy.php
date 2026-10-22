<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Especialista;
use App\Models\Rubrica;
use App\Models\RubricaVersion;

class RubricaVersionPolicy
{
    public function view(Especialista $user, RubricaVersion $version): bool
    {
        return $user->hasRole('area_coordinator')
            && $version->rubrica?->codigo === Rubrica::CODIGO_RIESGO_PREVENTIVO;
    }
}
