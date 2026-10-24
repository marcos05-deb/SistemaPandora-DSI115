<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Casts\AreaEncryptedFieldCast;
use App\Casts\EncryptedArrayCast;
use App\Casts\EncryptedFieldCast;
use App\Casts\EncryptedJsonFieldCast;

/**
 * Redacta atributos cifrados en eventos OwenIt ([CIFRADO]).
 */
trait MasksEncryptedAuditAttributes
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function transformAudit(array $data): array
    {
        $fields = array_unique(array_merge(
            $this->encryptedAuditAttributes(),
            $this->additionalMaskedAuditAttributes()
        ));

        foreach (['old_values', 'new_values'] as $bucket) {
            if (! isset($data[$bucket]) || ! is_array($data[$bucket])) {
                continue;
            }

            foreach ($fields as $field) {
                if (array_key_exists($field, $data[$bucket])) {
                    $data[$bucket][$field] = '[CIFRADO]';
                }
            }
        }

        return $this->afterMaskAudit($data);
    }

    /** @return list<string> */
    protected function encryptedAuditAttributes(): array
    {
        $casts = method_exists($this, 'getCasts') ? $this->getCasts() : [];
        $fields = [];

        foreach ($casts as $attribute => $cast) {
            $castClass = is_string($cast) ? $cast : (is_object($cast) ? $cast::class : '');
            if (in_array($castClass, [
                EncryptedFieldCast::class,
                AreaEncryptedFieldCast::class,
                EncryptedJsonFieldCast::class,
                EncryptedArrayCast::class,
            ], true)) {
                $fields[] = $attribute;
            }
        }

        return $fields;
    }

    /** @return list<string> */
    protected function additionalMaskedAuditAttributes(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function afterMaskAudit(array $data): array
    {
        return $data;
    }
}
