<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HU-03 prep: Añade slug y nivel a la tabla roles para soportar RBAC jerárquico.
 * Roles definidos en Roadmap §HU-03:
 *   sysadmin (nivel 100), area_coordinator (nivel 50), specialist (nivel 10)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('slug', 30)->unique()->nullable()->after('nombre');
            $table->smallInteger('nivel')->default(0)->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['slug', 'nivel']);
        });
    }
};
