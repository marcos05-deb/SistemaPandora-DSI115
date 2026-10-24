<?php

declare(strict_types=1);

namespace App\Models;

use OwenIt\Auditing\Models\Audit as OwenAudit;

/**
 * Modelo de auditoría inmutable (HU-21).
 * La app no puede actualizar ni eliminar filas existentes.
 */
class Audit extends OwenAudit
{
    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \RuntimeException('Los registros de auditoría son inmutables.');
        });

        static::deleting(static function (): never {
            throw new \RuntimeException('Los registros de auditoría son inmutables.');
        });
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \RuntimeException('Los registros de auditoría son inmutables.');
        }

        return parent::save($options);
    }
}
