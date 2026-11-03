<?php

namespace Tests\Feature\Security;

use App\Models\Area;
use App\Models\Especialista;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Role x screen matrix: true = reachable (anything but 403), false = must be 403.
 */
class RoleScreenMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string, bool}> */
    public static function matrix(): array
    {
        $screens = [
            //                      sysadmin area_coordinator specialist psychosocial_referent
            '/citas' => [false, true, true, false],
            '/alertas' => [false, true, true, false],
            '/rubricas' => [false, true, false, false],
            '/indicadores' => [true, true, false, false],
            '/reportes' => [true, true, false, false],
            '/pacientes' => [false, true, true, true],
            '/pacientes/create' => [false, false, false, true],
            '/admin/users' => [true, false, false, false],
            '/admin/bitacora' => [true, false, false, false],
        ];
        $roles = ['sysadmin', 'area_coordinator', 'specialist', 'psychosocial_referent'];

        $cases = [];
        foreach ($screens as $url => $allowed) {
            foreach ($roles as $i => $role) {
                $cases["$role $url"] = [$role, $url, $allowed[$i]];
            }
        }

        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_role_access_matches_user_stories(string $role, string $url, bool $allowed): void
    {
        $this->withSession(['_sym_key' => base64_encode(str_repeat('s', SODIUM_CRYPTO_SECRETBOX_KEYBYTES))]);

        $area = Area::forceCreate(['nombre' => 'Psicologia', 'requiere_aprobacion_estricta' => false]);
        $user = Especialista::forceCreate(['name' => 'U', 'email' => "$role@test.com", 'password' => 'secret']);
        $user->roles()->attach(Role::forceCreate(['nombre' => $role, 'slug' => $role, 'nivel' => 10]));
        DB::table('profesionales')->insert([
            'id' => Str::uuid(),
            'user_id' => $user->id,
            'area_id' => $area->id,
            'especialidad' => 'Psicologia',
            'numero_registro' => 'REG-001',
        ]);

        $status = $this->actingAs($user)->get($url)->getStatusCode();

        $allowed ? $this->assertNotSame(403, $status) : $this->assertSame(403, $status);
    }
}
