<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Especialista;
use App\Models\Role;
use App\Models\Area;
use App\Models\Profesional;

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

        // 0. Sysadmin
        $admin = Especialista::firstOrCreate(
            ['email' => 'admin@pandora.com'],
            ['name' => 'Administrador', 'password' => 'password_segura', 'must_change_password' => false]
        );
        $sysadminRole = Role::where('slug', 'sysadmin')->first();
        if ($sysadminRole && !$admin->roles()->where('role_id', $sysadminRole->id)->exists()) {
            $admin->roles()->attach($sysadminRole);
        }

        // 1. Coordinador de Área — Psicología
        $this->createProfessional(
            email: 'coordinador@pandora.com',
            name: 'Dr. Coordinador',
            roleSlug: 'area_coordinator',
            areaNombre: 'Psicología',
            especialidad: 'Psicología Clínica',
            numeroRegistro: 'PSI-COORD-001'
        );

        // 2. Referente Psicosocial — Trabajo Social
        $this->createProfessional(
            email: 'psicosocial@pandora.com',
            name: 'Lic. Psicosocial',
            roleSlug: 'psychosocial_referent',
            areaNombre: 'Trabajo Social',
            especialidad: 'Trabajo Social',
            numeroRegistro: 'TS-001'
        );

        // 3. Especialista — Medicina General
        $this->createProfessional(
            email: 'especialista@pandora.com',
            name: 'Dr. Especialista',
            roleSlug: 'specialist',
            areaNombre: 'Medicina General',
            especialidad: 'Medicina General',
            numeroRegistro: 'MED-001'
        );

        // 4. Especialista — Nutrición
        $this->createProfessional(
            email: 'especialista-nutri@pandora.com',
            name: 'Licda. Nutricionista',
            roleSlug: 'specialist',
            areaNombre: 'Nutrición',
            especialidad: 'Nutrición Clínica',
            numeroRegistro: 'NUT-001'
        );

        // 5. Especialista 2 — Psicología (Para probar aislamiento AreaScope)
        $this->createProfessional(
            email: 'especialista-psi@pandora.com',
            name: 'Lic. Psicólogo Dos',
            roleSlug: 'specialist',
            areaNombre: 'Psicología',
            especialidad: 'Psicología Infantil',
            numeroRegistro: 'PSI-002'
        );

        $this->call([
            ExpedienteSeeder::class,
            CitaSeeder::class,
        ]);
    }

    private function createProfessional(
        string $email,
        string $name,
        string $roleSlug,
        string $areaNombre,
        string $especialidad,
        string $numeroRegistro
    ): void {
        $user = Especialista::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => 'password_segura', 'must_change_password' => false]
        );

        $role = Role::where('slug', $roleSlug)->first();
        if ($role && !$user->roles()->where('role_id', $role->id)->exists()) {
            $user->roles()->attach($role);
        }

        $area = Area::where('nombre', $areaNombre)->first();
        if ($area) {
            Profesional::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'area_id' => $area->id,
                    'especialidad' => $especialidad,
                    'numero_registro' => $numeroRegistro,
                ]
            );
        }
    }
}
