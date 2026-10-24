<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_admin')->create('exportaciones_reporte', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('formato', 10);
            $table->jsonb('parametros');
            $table->string('sha256', 64);
            $table->timestampTz('generado_en');
            $table->timestamps();
            $table->index(['usuario_id', 'generado_en']);
        });

        $appUser = config('database.connections.pgsql.username');
        $adminUser = config('database.connections.pgsql_admin.username');
        if ($appUser && $appUser !== $adminUser) {
            DB::connection('pgsql_admin')->statement(
                "REVOKE UPDATE, DELETE, TRUNCATE ON TABLE exportaciones_reporte FROM {$appUser}"
            );
            DB::connection('pgsql_admin')->statement(
                "GRANT SELECT, INSERT ON TABLE exportaciones_reporte TO {$appUser}"
            );
        }
    }

    public function down(): void
    {
        Schema::connection('pgsql_admin')->dropIfExists('exportaciones_reporte');
    }
};
