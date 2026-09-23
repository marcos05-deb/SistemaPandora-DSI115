<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paciente_correcciones_auditoria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('paciente_id');
            $table->foreign('paciente_id')->references('codigo')->on('pacientes')->onDelete('restrict');

            $table->string('carnet_anterior', 7);
            $table->string('carnet_nuevo', 7);

            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('restrict');

            $table->uuid('profesional_id');
            $table->foreign('profesional_id')->references('id')->on('profesionales')->onDelete('restrict');

            $table->string('rol_usuario', 64);
            $table->text('motivo');
            $table->json('campos_modificados');
            $table->json('valores_anteriores');
            $table->json('valores_nuevos');

            $table->string('ip_address', 45)->nullable();
            $table->string('tipo_evento', 64);
            $table->string('nivel_evento', 16); // normal | sensible
            $table->boolean('aviso_sensible')->default(false);
            $table->boolean('aviso_revisado')->default(false);
            $table->timestamp('revisado_en')->nullable();
            $table->unsignedBigInteger('revisado_por_usuario_id')->nullable();
            $table->foreign('revisado_por_usuario_id')->references('id')->on('users')->onDelete('restrict');

            $table->timestamps();

            $table->index(['tipo_evento', 'created_at']);
            $table->index(['nivel_evento', 'aviso_sensible', 'aviso_revisado']);
            $table->index(['paciente_id', 'created_at']);
            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paciente_correcciones_auditoria');
    }
};
