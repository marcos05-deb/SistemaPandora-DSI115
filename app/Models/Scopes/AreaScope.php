<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class AreaScope implements Scope
{
    /**
     * Aplica el scope a un query builder dado.
     */
    public function apply(Builder $builder, Model $model): void
    {
        /** @var \App\Models\Especialista|null $especialista */
        $especialista = Auth::user();
        
        if ($especialista && !$especialista->hasRole('sysadmin')) {
            // whereIn para cubrir todas las áreas autorizadas del especialista
            $areaIds = $especialista->areas()->pluck('areas.id');
            $builder->whereIn('area_id', $areaIds);
        }
    }
}
