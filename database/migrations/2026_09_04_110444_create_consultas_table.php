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
        Schema::create('consultas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('expediente_id')->constrained('expedientes')->cascadeOnDelete();
            $table->foreignUuid('profesional_id')->constrained('profesionales')->cascadeOnDelete();
            $table->text('motivo_consulta');
            $table->text('notas_clinicas');
            $table->text('diagnostico');
            $table->timestampTz('fecha_consulta');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultas');
    }
};
