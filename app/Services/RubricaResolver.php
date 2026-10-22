<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Rubrica;
use App\Models\RubricaVersion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class RubricaResolver
{
    public function vigente(?CarbonInterface $en = null): ?RubricaVersion
    {
        $en ??= now();
        $rubricaId = Rubrica::query()
            ->where('codigo', Rubrica::CODIGO_RIESGO_PREVENTIVO)
            ->value('id');

        if ($rubricaId === null) {
            return null;
        }

        $query = RubricaVersion::query()
            ->with('variables')
            ->where('rubrica_id', $rubricaId)
            ->where('vigente_desde', '<=', $en)
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('es_demo', false)
                        ->whereNotNull('validada_en')
                        ->whereNotNull('referencia_validacion');
                });

                if (app()->environment(['local', 'testing'])) {
                    $query->orWhere('es_demo', true);
                }
            });

        $version = $query->orderByDesc('vigente_desde')->first();

        return $version;
    }
}
