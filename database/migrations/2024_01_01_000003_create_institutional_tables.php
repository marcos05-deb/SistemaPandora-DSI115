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
        Schema::create('areas', function (Blueprint $table) {
            $table->increments('id'); // SERIAL PRIMARY KEY
            $table->string('nombre', 255);
            $table->boolean('requiere_aprobacion_estricta')->default(false);
        });

        Schema::create('facultades', function (Blueprint $table) {
            $table->increments('id'); // SERIAL PRIMARY KEY
            $table->string('nombre', 255)->unique();
        });

        Schema::create('carreras', function (Blueprint $table) {
            $table->increments('id'); // SERIAL PRIMARY KEY
            $table->unsignedInteger('facultad_id'); // Match increments
            $table->foreign('facultad_id')->references('id')->on('facultades')->onDelete('restrict');
            $table->string('nombre', 255)->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carreras');
        Schema::dropIfExists('facultades');
        Schema::dropIfExists('areas');
    }
};
