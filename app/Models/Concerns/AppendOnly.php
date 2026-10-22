<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\RubricaInmutableException;

trait AppendOnly
{
    protected static function bootAppendOnly(): void
    {
        static::updating(static function (): never {
            throw new RubricaInmutableException('Este registro de rúbrica es inmutable.');
        });

        static::deleting(static function (): never {
            throw new RubricaInmutableException('Este registro de rúbrica es inmutable.');
        });
    }
}
