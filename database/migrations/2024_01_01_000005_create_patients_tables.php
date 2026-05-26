<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pacientes', function (Blueprint $table) {
            $table->uuid('codigo')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('carnet', 50)->unique();
            $table->unsignedInteger('carrera_id');
            $table->foreign('carrera_id')->references('id')->on('carreras')->onDelete('restrict');
            $table->uuid('creado_por_profesional_id');
            $table->foreign('creado_por_profesional_id')->references('id')->on('profesionales')->onDelete('restrict');
        });

        // Add custom enum columns
        DB::statement('ALTER TABLE pacientes ADD COLUMN sexo sexo_enum NOT NULL;');
        DB::statement('ALTER TABLE pacientes ADD COLUMN estado_civil estado_civil_enum NOT NULL;');

        Schema::table('pacientes', function (Blueprint $table) {
            $table->date('fecha_nacimiento');
            $table->string('profesion_ocupacion', 255)->nullable();
            $table->date('fecha_primera_consulta')->nullable();
            $table->string('referido_por', 255)->nullable();
            $table->string('llevado_por', 255)->nullable();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('deleted_at')->nullable();
        });

        Schema::create('contactos_paciente', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('paciente_id');
            $table->foreign('paciente_id')->references('codigo')->on('pacientes')->onDelete('cascade');
            $table->string('nombre_completo', 255);
        });

        // Add custom enum column
        DB::statement('ALTER TABLE contactos_paciente ADD COLUMN parentesco parentesco_enum NOT NULL;');

        Schema::table('contactos_paciente', function (Blueprint $table) {
            $table->string('telefono_personal', 50);
            $table->string('telefono_casa', 50)->nullable();
            $table->text('direccion')->nullable();
            $table->boolean('es_responsable')->default(false);
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('deleted_at')->nullable();
        });

        // Indices
        DB::statement('CREATE INDEX idx_pacientes_deleted_at ON pacientes(deleted_at) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_contactos_deleted_at ON contactos_paciente(deleted_at) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_pacientes_carnet ON pacientes(carnet);');
        DB::statement('CREATE INDEX idx_contactos_paciente_id ON contactos_paciente(paciente_id);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contactos_paciente');
        Schema::dropIfExists('pacientes');
    }
};
