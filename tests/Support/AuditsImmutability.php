<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cleanup de fixtures concurrentes que deben borrar filas de audits sin romper HU-21.
 * El rol app sigue sin DELETE; solo pgsql_admin + GUC de sesión.
 */
final class AuditsImmutability
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function bypass(callable $callback): mixed
    {
        $admin = DB::connection('pgsql_admin');
        $admin->unprepared("SELECT set_config('app.bypass_audits_immutability', 'on', false)");

        try {
            return $callback();
        } finally {
            try {
                $admin->unprepared("SELECT set_config('app.bypass_audits_immutability', 'off', false)");
            } catch (Throwable) {
                // La conexión pudo cerrarse tras un fallo de proceso hijo.
            }
        }
    }
}
