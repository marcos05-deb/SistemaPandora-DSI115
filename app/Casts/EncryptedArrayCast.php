<?php

declare(strict_types=1);

namespace App\Casts;

use App\Services\AreaEncryptionService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final readonly class EncryptedArrayCast implements CastsAttributes
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

        $decrypted = $encryptionService->decrypt((string) $value, $areaId);
        
        $decoded = json_decode($decrypted, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Error al decodificar JSON descifrado: " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value) && !is_object($value)) {
            throw new RuntimeException("EncryptedArrayCast espera un array u objeto para serializar.");
        }

        $encoded = json_encode($value);
        if ($encoded === false) {
            throw new RuntimeException("Error al codificar a JSON: " . json_last_error_msg());
        }

        $areaId = $this->resolveAreaId($model);
        $encryptionService = app(AreaEncryptionService::class);

        return $encryptionService->encrypt($encoded, $areaId);
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

            // Lazy load the relationship si tenemos el foreign key
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
