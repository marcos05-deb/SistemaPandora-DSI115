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
        Schema::table('expedientes', function (Blueprint $table) {
            $table->string('estado', 20)->default('abierto');
            $table->uuid('derivado_por_profesional_id')->nullable();
            $table->foreign('derivado_por_profesional_id')->references('id')->on('profesionales')->onDelete('restrict');
            $table->timestampTz('fecha_derivacion')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropForeign(['derivado_por_profesional_id']);
            $table->dropColumn(['estado', 'derivado_por_profesional_id', 'fecha_derivacion']);
        });
    }
};
