<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NAMES = [
        'sysadmin' => ['Administrador del sistema', 'Administrador de Sistema'],
        'area_coordinator' => ['Coordinador de área', 'Coordinador de Área'],
        'specialist' => ['Especialista', 'Especialista'],
        'psychosocial_referent' => ['Referente psicosocial', 'Referente Psicosocial'],
    ];

    public function up(): void
    {
        foreach (self::NAMES as $slug => [$new]) {
            DB::table('roles')->where('slug', $slug)->update(['nombre' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::NAMES as $slug => [, $old]) {
            DB::table('roles')->where('slug', $slug)->update(['nombre' => $old]);
        }
    }
};
