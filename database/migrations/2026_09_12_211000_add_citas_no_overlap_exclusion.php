<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * NH-05: exclusión de intervalos solapados (columna de rango + trigger).
     *
     * PostgreSQL no permite expresiones timestamptz+interval en índices GiST
     * porque no son IMMUTABLE; se materializa el rango en horario_agenda.
     */
    public function up(): void
    {
        DB::connection('pgsql_admin')->statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            ALTER TABLE citas
                ADD COLUMN IF NOT EXISTS horario_agenda tstzrange NULL
        SQL);

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            UPDATE citas
            SET horario_agenda = tstzrange(
                fecha_hora,
                fecha_hora + INTERVAL '60 minutes',
                '[)'
            )
            WHERE horario_agenda IS NULL AND fecha_hora IS NOT NULL
        SQL);

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION citas_set_horario_agenda()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.fecha_hora IS NULL THEN
                    NEW.horario_agenda := NULL;
                ELSE
                    NEW.horario_agenda := tstzrange(
                        NEW.fecha_hora,
                        NEW.fecha_hora + INTERVAL '60 minutes',
                        '[)'
                    );
                END IF;

                RETURN NEW;
            END;
            $$;
        SQL);

        DB::connection('pgsql_admin')->statement(
            'DROP TRIGGER IF EXISTS trg_citas_set_horario_agenda ON citas'
        );

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            CREATE TRIGGER trg_citas_set_horario_agenda
                BEFORE INSERT OR UPDATE OF fecha_hora
                ON citas
                FOR EACH ROW
                EXECUTE FUNCTION citas_set_horario_agenda()
        SQL);

        DB::connection('pgsql_admin')->statement('DROP INDEX IF EXISTS unique_profesional_cita');

        // RP-08: diagnosticar solapamientos existentes antes de crear la exclusión.
        $conflictos = DB::connection('pgsql_admin')->select(<<<'SQL'
            SELECT
                a.id AS cita_a,
                b.id AS cita_b,
                a.profesional_id
            FROM citas a
            JOIN citas b
              ON a.profesional_id = b.profesional_id
             AND a.id < b.id
             AND tstzrange(a.fecha_hora, a.fecha_hora + interval '60 minutes', '[)')
                 &&
                 tstzrange(b.fecha_hora, b.fecha_hora + interval '60 minutes', '[)')
            WHERE a.estado = 'programada'
              AND b.estado = 'programada'
              AND a.deleted_at IS NULL
              AND b.deleted_at IS NULL
            LIMIT 20
        SQL);

        if ($conflictos !== []) {
            $detalle = collect($conflictos)
                ->map(fn ($row) => "{$row->cita_a} ↔ {$row->cita_b} (profesional {$row->profesional_id})")
                ->implode('; ');

            throw new RuntimeException(
                'No se puede crear citas_no_solapamiento_programada: existen citas programadas solapadas. '.
                'Revise y resuelva manualmente antes de migrar. Conflictos: '.$detalle
            );
        }

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'citas_no_solapamiento_programada'
                ) THEN
                    ALTER TABLE citas
                        ADD CONSTRAINT citas_no_solapamiento_programada
                        EXCLUDE USING gist (
                            profesional_id WITH =,
                            horario_agenda WITH &&
                        )
                        WHERE (estado = 'programada' AND deleted_at IS NULL);
                END IF;
            END $$;
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql_admin')->statement(
            'ALTER TABLE citas DROP CONSTRAINT IF EXISTS citas_no_solapamiento_programada'
        );
        DB::connection('pgsql_admin')->statement('DROP TRIGGER IF EXISTS trg_citas_set_horario_agenda ON citas');
        DB::connection('pgsql_admin')->statement('DROP FUNCTION IF EXISTS citas_set_horario_agenda()');
        DB::connection('pgsql_admin')->statement('ALTER TABLE citas DROP COLUMN IF EXISTS horario_agenda');

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS unique_profesional_cita
                ON citas (profesional_id, fecha_hora)
                WHERE estado = 'programada' AND deleted_at IS NULL
        SQL);
    }
};
