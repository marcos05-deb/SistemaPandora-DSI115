<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Garantiza que citas.profesional_user_id coincida con el dueño del perfil.
     */
    public function up(): void
    {
        $inconsistentes = DB::connection('pgsql_admin')->select(<<<'SQL'
            SELECT c.id AS cita_id, c.profesional_id, c.profesional_user_id, p.user_id AS perfil_user_id
            FROM citas c
            JOIN profesionales p ON p.id = c.profesional_id
            WHERE c.profesional_user_id IS DISTINCT FROM p.user_id
            LIMIT 20
        SQL);

        if ($inconsistentes !== []) {
            $detalle = collect($inconsistentes)
                ->map(fn ($row) => "{$row->cita_id} (perfil {$row->profesional_id}: user {$row->profesional_user_id} ≠ {$row->perfil_user_id})")
                ->implode('; ');

            throw new RuntimeException(
                'No se puede crear la FK compuesta citas→profesionales: hay pares inconsistentes. '.
                'Casos: '.$detalle
            );
        }

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS profesionales_id_user_id_unique
            ON profesionales (id, user_id)
        SQL);

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'citas_profesional_id_user_id_fk'
                ) THEN
                    ALTER TABLE citas
                        ADD CONSTRAINT citas_profesional_id_user_id_fk
                        FOREIGN KEY (profesional_id, profesional_user_id)
                        REFERENCES profesionales (id, user_id)
                        ON UPDATE RESTRICT
                        ON DELETE RESTRICT;
                END IF;
            END $$;
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql_admin')->statement(
            'ALTER TABLE citas DROP CONSTRAINT IF EXISTS citas_profesional_id_user_id_fk'
        );
        DB::connection('pgsql_admin')->statement(
            'DROP INDEX IF EXISTS profesionales_id_user_id_unique'
        );
    }
};
