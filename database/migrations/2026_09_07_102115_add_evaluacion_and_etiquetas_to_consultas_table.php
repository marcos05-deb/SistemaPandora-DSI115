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
        Schema::table('consultas', function (Blueprint $table) {
            $table->text('tecnica_utilizada')->nullable();
            $table->text('antecedentes_problema')->nullable();
            $table->text('etiquetas_motivo')->nullable();
            $table->text('evaluacion_inicial')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->dropColumn([
                'tecnica_utilizada',
                'antecedentes_problema',
                'etiquetas_motivo',
                'evaluacion_inicial',
            ]);
        });
    }
};
