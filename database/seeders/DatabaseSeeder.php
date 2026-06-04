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
            FacultadCarreraSeeder::class,
        ]);

        $admin = Especialista::firstOrCreate(
            ['email' => 'admin@pandora.com'],
            ['name' => 'Administrador', 'password' => 'password_segura', 'must_change_password' => false]
        );

        $sysadminRole = Role::where('slug', 'sysadmin')->first();
        if ($sysadminRole && !$admin->roles()->where('role_id', $sysadminRole->id)->exists()) {
            $admin->roles()->attach($sysadminRole);
        }

        // Seed Clinical Staff
        $kdfService = app(\App\Services\Crypto\KeyDerivationService::class);
        $area = \App\Models\Area::first() ?? \App\Models\Area::create(['nombre' => 'Área General']);

        // 1. Coordinador
        $coordinador = Especialista::firstOrCreate(
            ['email' => 'coordinador@pandora.com'],
            ['name' => 'Dr. Coordinador', 'password' => 'password_segura', 'must_change_password' => false, 'kdf_salt' => $kdfService->generateSalt()]
        );
        $roleCoord = Role::where('slug', 'area_coordinator')->first();
        if ($roleCoord && !$coordinador->roles()->where('role_id', $roleCoord->id)->exists()) {
            $coordinador->roles()->attach($roleCoord);
            \App\Models\Profesional::firstOrCreate(
                ['user_id' => $coordinador->id],
                ['area_id' => $area->id, 'especialidad' => 'Medicina General', 'numero_registro' => 'MED-001']
            );
        }

        // 2. Referente Psicosocial
        $psicosocial = Especialista::firstOrCreate(
            ['email' => 'psicosocial@pandora.com'],
            ['name' => 'Lic. Psicosocial', 'password' => 'password_segura', 'must_change_password' => false, 'kdf_salt' => $kdfService->generateSalt()]
        );
        $rolePsico = Role::where('slug', 'psychosocial_referent')->first();
        if ($rolePsico && !$psicosocial->roles()->where('role_id', $rolePsico->id)->exists()) {
            $psicosocial->roles()->attach($rolePsico);
            \App\Models\Profesional::firstOrCreate(
                ['user_id' => $psicosocial->id],
                ['area_id' => $area->id, 'especialidad' => 'Psicología', 'numero_registro' => 'PSI-001']
            );
        }

        // 3. Especialista Normal
        $especialista = Especialista::firstOrCreate(
            ['email' => 'especialista@pandora.com'],
            ['name' => 'Dr. Especialista', 'password' => 'password_segura', 'must_change_password' => false, 'kdf_salt' => $kdfService->generateSalt()]
        );
        $roleEsp = Role::where('slug', 'specialist')->first();
        if ($roleEsp && !$especialista->roles()->where('role_id', $roleEsp->id)->exists()) {
            $especialista->roles()->attach($roleEsp);
            \App\Models\Profesional::firstOrCreate(
                ['user_id' => $especialista->id],
                ['area_id' => $area->id, 'especialidad' => 'Pediatría', 'numero_registro' => 'PED-001']
            );
        }
    }
}
