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
        Schema::table('citas', function (Blueprint $table) {
            $table->text('motivo_cancelacion')->nullable();
            $table->uuid('cita_origen_id')->nullable();
            $table->foreign('cita_origen_id')->references('id')->on('citas')->onDelete('set null');
            
            // Reemplazar el constraint único ciego por un índice parcial
            $table->dropUnique('unique_profesional_cita');
        });
        
        DB::statement("CREATE UNIQUE INDEX unique_profesional_cita ON citas (profesional_id, fecha_hora) WHERE estado = 'programada' AND deleted_at IS NULL;");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS unique_profesional_cita;');
        
        Schema::table('citas', function (Blueprint $table) {
            // Restaurar constraint original (ciego al estado)
            $table->unique(['profesional_id', 'fecha_hora'], 'unique_profesional_cita');
            
            $table->dropForeign(['cita_origen_id']);
            $table->dropColumn(['cita_origen_id', 'motivo_cancelacion']);
        });
    }
};
