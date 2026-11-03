<?php

declare(strict_types=1);

namespace App\Support;

final class PrivacyThreshold
{
    /** Minimum cell size (k-anonymity); never below 2. */
    public static function minCell(): int
    {
        return max(2, (int) config('indicadores.min_celda', 5));
    }
}
