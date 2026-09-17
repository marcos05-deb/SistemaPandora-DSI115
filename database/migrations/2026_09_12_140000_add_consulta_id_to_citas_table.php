<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * HU-11: vincular cada cita a la consulta origen.
     * Nullable para citas históricas; obligatorio en nuevas creaciones vía Request.
     * ON DELETE SET NULL conserva la cita si la consulta se elimina en duro.
     */
    public function up(): void
    {
        DB::connection('pgsql_admin')->statement(<<<'SQL'
            ALTER TABLE citas
                ADD COLUMN IF NOT EXISTS consulta_id UUID NULL
        SQL);

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'citas_consulta_id_foreign'
                ) THEN
                    ALTER TABLE citas
                        ADD CONSTRAINT citas_consulta_id_foreign
                        FOREIGN KEY (consulta_id) REFERENCES consultas(id)
                        ON DELETE SET NULL;
                END IF;
            END $$;
        SQL);

        DB::connection('pgsql_admin')->statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_citas_consulta_id ON citas (consulta_id)
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql_admin')->statement('ALTER TABLE citas DROP CONSTRAINT IF EXISTS citas_consulta_id_foreign');
        DB::connection('pgsql_admin')->statement('DROP INDEX IF EXISTS idx_citas_consulta_id');
        DB::connection('pgsql_admin')->statement('ALTER TABLE citas DROP COLUMN IF EXISTS consulta_id');
    }
};
