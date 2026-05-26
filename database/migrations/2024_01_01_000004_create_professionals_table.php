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
        Schema::create('profesionales', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->unsignedInteger('area_id');
            $table->foreign('area_id')->references('id')->on('areas')->onDelete('restrict');
            $table->string('especialidad', 255);
            $table->string('numero_registro', 255);
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('deleted_at')->nullable();
        });

        // Index
        DB::statement('CREATE INDEX idx_profesionales_deleted_at ON profesionales(deleted_at) WHERE deleted_at IS NULL;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profesionales');
    }
};
