<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HU-01: Añade columna kdf_salt a la tabla users para derivación de clave simétrica.
 * La sal es única por usuario, generada en el registro con random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES).
 * Se almacena codificada en Base64.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('kdf_salt')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('kdf_salt');
        });
    }
};
