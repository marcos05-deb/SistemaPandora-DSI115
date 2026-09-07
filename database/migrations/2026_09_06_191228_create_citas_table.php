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
        Schema::create('citas', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            
            $table->uuid('expediente_id');
            $table->foreign('expediente_id')->references('id')->on('expedientes')->onDelete('cascade');
            
            $table->uuid('profesional_id');
            $table->foreign('profesional_id')->references('id')->on('profesionales')->onDelete('restrict');
            
            $table->unsignedInteger('area_id');
            $table->foreign('area_id')->references('id')->on('areas')->onDelete('restrict');
            
            $table->timestampTz('fecha_hora');
            
            // Unique constraint to prevent double booking exact same time for the same specialist
            $table->unique(['profesional_id', 'fecha_hora'], 'unique_profesional_cita');
            
            $table->string('estado')->default('programada');
            $table->text('motivo');
            
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('deleted_at')->nullable();
        });
        
        DB::statement('CREATE INDEX idx_citas_deleted_at ON citas(deleted_at) WHERE deleted_at IS NULL;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citas');
    }
};
