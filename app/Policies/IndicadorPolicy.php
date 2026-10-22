<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Especialista;
use Illuminate\Support\Facades\DB;

final class IndicadorPolicy
{
    public function ver(Especialista $user, ?int $areaId = null): bool
    {
        if ($user->hasRole('sysadmin')) {
            return true;
        }

        if (! $user->hasRole('area_coordinator')) {
            return false;
        }

        $profiles = $user->perfilesProfesionales();

        return $areaId === null
            ? $profiles->exists()
            : $profiles->where('area_id', $areaId)->exists();
    }

    /** @return list<int> */
    public function areasPermitidas(Especialista $user): array
    {
        $areas = $user->hasRole('sysadmin')
            ? DB::table('areas')->orderBy('id')->pluck('id')
            : $user->perfilesProfesionales()->orderBy('area_id')->pluck('area_id');

        return $areas->map(static fn (mixed $id): int => (int) $id)->unique()->values()->all();
    }

    public function layoutFor(Especialista $user): string
    {
        return $user->hasRole('sysadmin') ? 'admin' : 'clinical';
    }
}
