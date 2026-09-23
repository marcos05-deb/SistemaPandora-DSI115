<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->timestamp('datos_corregidos_en')->nullable()->after('ultima_accion');
            $table->unsignedBigInteger('datos_corregidos_por_usuario_id')->nullable()->after('datos_corregidos_en');
            $table->foreign('datos_corregidos_por_usuario_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });

        Schema::table('expedientes', function (Blueprint $table) {
            $table->timestamp('actualizado_clinicamente_en')->nullable()->after('cerrado_por_profesional_id');
            $table->unsignedBigInteger('actualizado_clinicamente_por_usuario_id')->nullable()->after('actualizado_clinicamente_en');
            $table->foreign('actualizado_clinicamente_por_usuario_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });

        // Ampliar columnas de carnet en auditoría para placeholder [CIFRADO]
        DB::statement('ALTER TABLE paciente_correcciones_auditoria ALTER COLUMN carnet_anterior TYPE VARCHAR(32)');
        DB::statement('ALTER TABLE paciente_correcciones_auditoria ALTER COLUMN carnet_nuevo TYPE VARCHAR(32)');

        Schema::create('solicitudes_correccion_expediente', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('paciente_id');
            $table->foreign('paciente_id')->references('codigo')->on('pacientes')->onDelete('restrict');

            $table->uuid('expediente_id')->nullable();
            $table->foreign('expediente_id')->references('id')->on('expedientes')->onDelete('restrict');

            $table->string('tipo', 32); // datos_generales | clinico
            $table->unsignedBigInteger('solicitante_usuario_id');
            $table->foreign('solicitante_usuario_id')->references('id')->on('users')->onDelete('restrict');

            $table->json('campos_solicitados');
            $table->text('motivo');
            $table->string('estado', 24); // pendiente | aprobada | rechazada | consumida

            $table->unsignedBigInteger('revisado_por_usuario_id')->nullable();
            $table->foreign('revisado_por_usuario_id')->references('id')->on('users')->onDelete('restrict');
            $table->timestamp('revisado_en')->nullable();
            $table->text('nota_admin')->nullable();

            $table->timestamps();

            $table->index(['paciente_id', 'estado']);
            $table->index(['expediente_id', 'estado']);
            $table->index(['estado', 'created_at']);
            $table->index('solicitante_usuario_id');
        });

        // Pacientes que ya tienen auditoría de corrección: marcar 1ª corrección usada
        DB::statement(<<<'SQL'
            UPDATE pacientes p
            SET
                datos_corregidos_en = sub.first_at,
                datos_corregidos_por_usuario_id = sub.usuario_id
            FROM (
                SELECT DISTINCT ON (paciente_id)
                    paciente_id,
                    created_at AS first_at,
                    usuario_id
                FROM paciente_correcciones_auditoria
                ORDER BY paciente_id, created_at ASC
            ) sub
            WHERE p.codigo = sub.paciente_id
              AND p.datos_corregidos_en IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_correccion_expediente');

        DB::statement('ALTER TABLE paciente_correcciones_auditoria ALTER COLUMN carnet_anterior TYPE VARCHAR(7)');
        DB::statement('ALTER TABLE paciente_correcciones_auditoria ALTER COLUMN carnet_nuevo TYPE VARCHAR(7)');

        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropForeign(['actualizado_clinicamente_por_usuario_id']);
            $table->dropColumn(['actualizado_clinicamente_en', 'actualizado_clinicamente_por_usuario_id']);
        });

        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropForeign(['datos_corregidos_por_usuario_id']);
            $table->dropColumn(['datos_corregidos_en', 'datos_corregidos_por_usuario_id']);
        });
    }
};
