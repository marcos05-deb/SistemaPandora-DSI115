<?php

declare(strict_types=1);

namespace App\Casts;

use App\Services\AreaEncryptionService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final readonly class EncryptedFieldCast implements CastsAttributes
{
    public function __construct() {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        $areaId = $this->resolveAreaId($model);
        $encryptionService = app(AreaEncryptionService::class);

        return $encryptionService->decrypt((string) $value, $areaId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        $areaId = $this->resolveAreaId($model);
        $encryptionService = app(AreaEncryptionService::class);

        return $encryptionService->encrypt((string) $value, $areaId);
    }

    private function resolveAreaId(Model $model): string|int
    {
        if (isset($model->area_id)) {
            return $model->area_id;
        }

        if (method_exists($model, 'area') && $model->area !== null && isset($model->area->id)) {
            return $model->area->id;
        }

        if (method_exists($model, 'expediente')) {
            if ($model->relationLoaded('expediente') && $model->expediente !== null && isset($model->expediente->area_id)) {
                return $model->expediente->area_id;
            }

            // Lazy load the relationship if we have the foreign key
            if (isset($model->expediente_id)) {
                $model->load('expediente');
                if ($model->expediente !== null && isset($model->expediente->area_id)) {
                    return $model->expediente->area_id;
                }
            }
        }

        throw new RuntimeException(
            sprintf('No se pudo resolver el area_id para el cifrado en el modelo %s.', $model::class)
        );
    }
}
