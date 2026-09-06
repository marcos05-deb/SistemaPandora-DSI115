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
            $table->text('motivo_cierre')->nullable();
            $table->timestampTz('fecha_cierre')->nullable();
            $table->foreignUuid('cerrado_por_profesional_id')->nullable()->constrained('profesionales')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropForeign(['cerrado_por_profesional_id']);
            $table->dropColumn(['motivo_cierre', 'fecha_cierre', 'cerrado_por_profesional_id']);
        });
    }
};
