<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->text('fecha_nacimiento')->change();
            $table->text('fecha_primera_consulta')->nullable()->change();
        });

        Schema::table('contactos_paciente', function (Blueprint $table) {
            $table->text('telefono_personal')->change();
            $table->text('telefono_casa')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->date('fecha_nacimiento')->change();
            $table->date('fecha_primera_consulta')->nullable()->change();
        });

        Schema::table('contactos_paciente', function (Blueprint $table) {
            $table->string('telefono_personal', 50)->change();
            $table->string('telefono_casa', 50)->nullable()->change();
        });
    }
};

