<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * RF-03: un solo perfil profesional activo por (usuario, área).
     */
    public function up(): void
    {
        $duplicados = DB::connection('pgsql_admin')->select(<<<'SQL'
            SELECT user_id, area_id, COUNT(*) AS total
            FROM profesionales
            WHERE deleted_at IS NULL
            GROUP BY user_id, area_id
            HAVING COUNT(*) > 1
            LIMIT 20
        SQL);

        if ($duplicados !== []) {
            $detalle = collect($duplicados)
                ->map(fn ($row) => "user {$row->user_id} / área {$row->area_id} ({$row->total})")
                ->implode('; ');

            throw new RuntimeException(
                'No se puede crear profesionales_usuario_area_activo_unique: hay perfiles activos duplicados. '.
                'Consolide o archive duplicados antes de migrar. Casos: '.$detalle
            );
        }

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS profesionales_usuario_area_activo_unique
            ON profesionales (user_id, area_id)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql_admin')->statement(
            'DROP INDEX IF EXISTS profesionales_usuario_area_activo_unique'
        );
    }
};
