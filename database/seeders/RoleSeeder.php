<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['slug' => 'sysadmin'], ['nombre' => 'Administrador del sistema', 'nivel' => 100]);
        Role::firstOrCreate(['slug' => 'area_coordinator'], ['nombre' => 'Coordinador de área', 'nivel' => 50]);
        Role::firstOrCreate(['slug' => 'psychosocial_referent'], ['nombre' => 'Referente psicosocial', 'nivel' => 20]);
        Role::firstOrCreate(['slug' => 'specialist'], ['nombre' => 'Especialista', 'nivel' => 10]);
    }
}
