<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carreras', function (Blueprint $table) {
            $table->dropUnique('carreras_nombre_unique');
            $table->unique(['facultad_id', 'nombre'], 'carreras_facultad_nombre_unique');
        });
    }

    public function down(): void
    {
        Schema::table('carreras', function (Blueprint $table) {
            $table->dropUnique('carreras_facultad_nombre_unique');
            $table->unique('nombre', 'carreras_nombre_unique');
        });
    }
};
