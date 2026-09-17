<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
        
        DB::statement("DROP TYPE IF EXISTS sexo_enum CASCADE;");
        DB::statement("CREATE TYPE sexo_enum AS ENUM ('M', 'F', 'Otro');");
        
        DB::statement("DROP TYPE IF EXISTS estado_civil_enum CASCADE;");
        DB::statement("CREATE TYPE estado_civil_enum AS ENUM ('Soltero', 'Casado', 'Divorciado', 'Viudo', 'Unión Libre');");
        
        DB::statement("DROP TYPE IF EXISTS parentesco_enum CASCADE;");
        DB::statement("CREATE TYPE parentesco_enum AS ENUM ('Padre', 'Madre', 'Tutor', 'Otro');");

        // Mínimo privilegio: DML sin CREATE/DDL en schema public para el rol de app.
        $appUser = config('database.connections.pgsql.username');
        $adminUser = config('database.connections.pgsql_admin.username');

        if ($appUser && $appUser !== $adminUser) {
            DB::statement("REVOKE CREATE ON SCHEMA public FROM PUBLIC");
            DB::statement("REVOKE ALL ON SCHEMA public FROM {$appUser}");
            DB::statement("GRANT USAGE ON SCHEMA public TO {$appUser}");
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {$appUser}");
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO {$appUser}");
            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO {$appUser}");
            DB::statement("GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO {$appUser}");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP TYPE IF EXISTS parentesco_enum;");
        DB::statement("DROP TYPE IF EXISTS estado_civil_enum;");
        DB::statement("DROP TYPE IF EXISTS sexo_enum;");
        DB::statement('DROP EXTENSION IF EXISTS "uuid-ossp";');
    }
};
