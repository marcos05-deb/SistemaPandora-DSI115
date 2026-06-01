<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Especialista;

use App\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AreaSeeder::class,
        ]);

        $admin = Especialista::firstOrCreate(
            ['email' => 'admin@pandora.com'],
            ['name' => 'Administrador', 'password' => 'password_segura', 'must_change_password' => false]
        );

        $sysadminRole = Role::where('slug', 'sysadmin')->first();
        if ($sysadminRole && !$admin->roles()->where('role_id', $sysadminRole->id)->exists()) {
            $admin->roles()->attach($sysadminRole);
        }
    }
}
