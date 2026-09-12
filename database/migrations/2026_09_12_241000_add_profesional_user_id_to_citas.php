<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RF-01: la agenda física pertenece al usuario, no solo al perfil/área.
     */
    public function up(): void
    {
        Schema::connection('pgsql_admin')->table('citas', function (Blueprint $table) {
            $table->foreignId('profesional_user_id')
                ->nullable()
                ->after('profesional_id')
                ->constrained('users')
                ->restrictOnDelete();
        });

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            UPDATE citas AS c
            SET profesional_user_id = p.user_id
            FROM profesionales AS p
            WHERE c.profesional_id = p.id
              AND c.profesional_user_id IS NULL
        SQL);

        $sinUsuario = DB::connection('pgsql_admin')->selectOne(<<<'SQL'
            SELECT COUNT(*)::int AS total
            FROM citas
            WHERE profesional_user_id IS NULL
        SQL);

        if (($sinUsuario->total ?? 0) > 0) {
            throw new RuntimeException(
                'No se puede completar profesional_user_id: hay citas sin profesional asociado a users.'
            );
        }

        $conflictos = DB::connection('pgsql_admin')->select(<<<'SQL'
            SELECT
                a.id AS cita_a,
                b.id AS cita_b,
                a.profesional_user_id
            FROM citas a
            JOIN citas b
              ON a.profesional_user_id = b.profesional_user_id
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
                ->map(fn ($row) => "{$row->cita_a} ↔ {$row->cita_b} (user {$row->profesional_user_id})")
                ->implode('; ');

            throw new RuntimeException(
                'No se puede crear citas_no_solapamiento_usuario_programada: existen citas programadas solapadas '.
                'para la misma persona. Revise y resuelva manualmente antes de migrar. Conflictos: '.$detalle
            );
        }

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            ALTER TABLE citas
                ALTER COLUMN profesional_user_id SET NOT NULL
        SQL);

        DB::connection('pgsql_admin')->statement(
            'ALTER TABLE citas DROP CONSTRAINT IF EXISTS citas_no_solapamiento_programada'
        );

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'citas_no_solapamiento_usuario_programada'
                ) THEN
                    ALTER TABLE citas
                        ADD CONSTRAINT citas_no_solapamiento_usuario_programada
                        EXCLUDE USING gist (
                            profesional_user_id WITH =,
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
            'ALTER TABLE citas DROP CONSTRAINT IF EXISTS citas_no_solapamiento_usuario_programada'
        );

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

        Schema::connection('pgsql_admin')->table('citas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profesional_user_id');
        });
    }
};
