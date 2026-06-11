<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Especialista;
use App\Models\Profesional;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EspecialistaFactory extends Factory
{
    protected $model = Especialista::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->numerify('########'),
            'is_active' => true,
            'password' => Hash::make('password_segura'),
            'kdf_salt' => base64_encode(random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES)),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ];
    }

    /**
     * Crea el perfil profesional asociado al Especialista.
     * Si no se proporciona un Área, crea una genérica.
     */
    public function withProfesional(Area $area = null): self
    {
        return $this->afterCreating(function (Especialista $especialista) use ($area) {
            $areaToAssign = $area ?? Area::factory()->create();
            
            Profesional::factory()->create([
                'user_id' => $especialista->id,
                'area_id' => $areaToAssign->id,
            ]);
        });
    }

    /**
     * Asigna un Rol al Especialista.
     */
    public function withRole(string|Role $roleOrSlug): self
    {
        return $this->afterCreating(function (Especialista $especialista) use ($roleOrSlug) {
            if ($roleOrSlug instanceof Role) {
                $role = $roleOrSlug;
            } else {
                $role = Role::firstOrCreate(
                    ['slug' => $roleOrSlug],
                    ['nombre' => ucfirst(str_replace('_', ' ', $roleOrSlug)), 'nivel' => 10]
                );
            }
            
            $especialista->roles()->syncWithoutDetaching([$role->id]);
        });
    }
}
