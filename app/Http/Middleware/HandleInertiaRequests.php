<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'appName'     => 'PANDORA',
            'appSubtitle' => 'Sistema de Gestión Clínica',
            'auth' => [
                'user' => $user ? $this->sharedUser($user) : null,
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'variant' => fn () => $request->session()->get('variant', 'success'),
                'generated_password' => fn () => $request->session()->get('generated_password'),
                'prompt_cita_expediente_id' => fn () => $request->session()->get('prompt_cita_expediente_id'),
                'prompt_cita_consulta_id' => fn () => $request->session()->get('prompt_cita_consulta_id'),
            ],
        ];
    }

    /**
     * Datos de sesión para layout clínico: slugs (autorización) + etiquetas legibles (UI).
     *
     * @return array{id: mixed, email: string, name: string, roles: list<string>, role_label: string, area: string|null, areas: list<string>}
     */
    private function sharedUser(\App\Models\Especialista $user): array
    {
        $roles = $user->roles()->orderByDesc('roles.nivel')->get(['roles.slug', 'roles.nombre', 'roles.nivel']);
        $slugs = $roles->pluck('slug')->values()->all();
        $primaryRole = $roles->first();

        $areaNames = $user->areas()
            ->orderBy('areas.nombre')
            ->pluck('areas.nombre')
            ->unique()
            ->values()
            ->all();

        $areaLabel = match (true) {
            count($areaNames) === 0 => null,
            count($areaNames) === 1 => $areaNames[0],
            default => implode(', ', $areaNames),
        };

        $roleNombre = $primaryRole?->nombre ?? 'Usuario';
        $roleLabel = $areaLabel && $primaryRole && $primaryRole->slug !== 'sysadmin'
            ? "{$roleNombre} — {$areaLabel}"
            : $roleNombre;

        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'roles' => $slugs,
            'role_label' => $roleLabel,
            'area' => $areaLabel,
            'areas' => $areaNames,
        ];
    }
}
