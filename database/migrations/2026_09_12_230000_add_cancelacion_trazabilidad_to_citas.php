<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_admin')->table('citas', function (Blueprint $table) {
            $table->foreignUuid('cancelado_por_profesional_id')
                ->nullable()
                ->constrained('profesionales')
                ->nullOnDelete();
            $table->timestampTz('fecha_cancelacion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_admin')->table('citas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelado_por_profesional_id');
            $table->dropColumn('fecha_cancelacion');
        });
    }
};
