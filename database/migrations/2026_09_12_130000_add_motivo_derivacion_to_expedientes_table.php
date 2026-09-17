<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Nullable para expedientes ya existentes; obligatorio solo en nuevas
     * derivaciones vía DerivacionStoreRequest.
     */
    public function up(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->text('motivo_derivacion')->nullable()->after('fecha_derivacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropColumn('motivo_derivacion');
        });
    }
};
