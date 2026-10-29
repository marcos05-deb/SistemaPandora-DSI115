<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['slug' => 'sysadmin'], ['nombre' => 'Administrador de Sistema', 'nivel' => 100]);
        Role::firstOrCreate(['slug' => 'area_coordinator'], ['nombre' => 'Coordinador de Área', 'nivel' => 50]);
        Role::firstOrCreate(['slug' => 'psychosocial_referent'], ['nombre' => 'Referente Psicosocial', 'nivel' => 20]);
        Role::firstOrCreate(['slug' => 'specialist'], ['nombre' => 'Especialista', 'nivel' => 10]);
    }
}
