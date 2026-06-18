<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expedientes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('paciente_id');
            $table->foreign('paciente_id')->references('codigo')->on('pacientes')->onDelete('cascade');
            $table->unsignedInteger('area_id');
            $table->foreign('area_id')->references('id')->on('areas')->onDelete('restrict');
            $table->text('motivo_consulta')->nullable();
            $table->text('notas_clinicas')->nullable();
            $table->text('diagnostico')->nullable();
            
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('deleted_at')->nullable();
        });

        DB::statement('CREATE INDEX idx_expedientes_deleted_at ON expedientes(deleted_at) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('expedientes');
    }
};
