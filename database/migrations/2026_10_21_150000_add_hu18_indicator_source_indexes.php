<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('pgsql_admin');
        $connection->statement('CREATE INDEX IF NOT EXISTS consultas_fecha_expediente_hu18_index ON consultas (fecha_consulta, expediente_id) WHERE deleted_at IS NULL');
        $connection->statement('CREATE INDEX IF NOT EXISTS expedientes_area_created_hu18_index ON expedientes (area_id, created_at) WHERE deleted_at IS NULL');
        $connection->statement('CREATE INDEX IF NOT EXISTS expedientes_area_derivacion_hu18_index ON expedientes (area_id, fecha_derivacion) WHERE deleted_at IS NULL AND fecha_derivacion IS NOT NULL');
        $connection->statement('CREATE INDEX IF NOT EXISTS expedientes_area_cierre_hu18_index ON expedientes (area_id, fecha_cierre) WHERE deleted_at IS NULL AND fecha_cierre IS NOT NULL');
        $connection->statement('CREATE INDEX IF NOT EXISTS citas_area_fecha_hu18_index ON citas (area_id, fecha_hora) WHERE deleted_at IS NULL');
        $connection->statement('CREATE INDEX IF NOT EXISTS seguimientos_area_created_hu18_index ON seguimientos_preventivos (area_id, created_at)');
    }

    public function down(): void
    {
        $connection = DB::connection('pgsql_admin');
        $connection->statement('DROP INDEX IF EXISTS consultas_fecha_expediente_hu18_index');
        $connection->statement('DROP INDEX IF EXISTS expedientes_area_created_hu18_index');
        $connection->statement('DROP INDEX IF EXISTS expedientes_area_derivacion_hu18_index');
        $connection->statement('DROP INDEX IF EXISTS expedientes_area_cierre_hu18_index');
        $connection->statement('DROP INDEX IF EXISTS citas_area_fecha_hu18_index');
        $connection->statement('DROP INDEX IF EXISTS seguimientos_area_created_hu18_index');
    }
};
