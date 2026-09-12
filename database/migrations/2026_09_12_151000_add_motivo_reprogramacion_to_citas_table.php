<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * HU-13: motivo, autor y timestamp de reprogramación.
     */
    public function up(): void
    {
        DB::connection('pgsql_admin')->statement(<<<'SQL'
            ALTER TABLE citas
                ADD COLUMN IF NOT EXISTS motivo_reprogramacion TEXT NULL,
                ADD COLUMN IF NOT EXISTS reprogramado_por_profesional_id UUID NULL,
                ADD COLUMN IF NOT EXISTS fecha_reprogramacion TIMESTAMPTZ NULL
        SQL);

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'citas_reprogramado_por_profesional_id_foreign'
                ) THEN
                    ALTER TABLE citas
                        ADD CONSTRAINT citas_reprogramado_por_profesional_id_foreign
                        FOREIGN KEY (reprogramado_por_profesional_id) REFERENCES profesionales(id)
                        ON DELETE SET NULL;
                END IF;
            END $$;
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql_admin')->statement(<<<'SQL'
            ALTER TABLE citas
                DROP CONSTRAINT IF EXISTS citas_reprogramado_por_profesional_id_foreign
        SQL);

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            ALTER TABLE citas
                DROP COLUMN IF EXISTS motivo_reprogramacion,
                DROP COLUMN IF EXISTS reprogramado_por_profesional_id,
                DROP COLUMN IF EXISTS fecha_reprogramacion
        SQL);
    }
};
