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
        Schema::table('citas', function (Blueprint $table) {
            $table->timestampTz('fecha_registro_asistencia')->nullable();
            $table->uuid('registrado_por_profesional_id')->nullable();
            $table->foreign('registrado_por_profesional_id')->references('id')->on('profesionales')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropForeign(['registrado_por_profesional_id']);
            $table->dropColumn(['registrado_por_profesional_id', 'fecha_registro_asistencia']);
        });
    }
};
