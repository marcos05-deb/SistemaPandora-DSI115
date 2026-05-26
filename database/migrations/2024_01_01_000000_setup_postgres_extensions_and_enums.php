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
        
        DB::statement("CREATE TYPE sexo_enum AS ENUM ('M', 'F', 'Otro');");
        DB::statement("CREATE TYPE estado_civil_enum AS ENUM ('Soltero', 'Casado', 'Divorciado', 'Viudo', 'Unión Libre');");
        DB::statement("CREATE TYPE parentesco_enum AS ENUM ('Padre', 'Madre', 'Tutor', 'Otro');");
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
