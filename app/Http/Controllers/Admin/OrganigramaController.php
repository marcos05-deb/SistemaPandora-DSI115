<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Especialista;
use App\Models\Role;
use Inertia\Inertia;
use Inertia\Response;

class OrganigramaController extends Controller
{
    public function index(): Response
    {
        $roles = Role::orderBy('nivel', 'desc')->get()->map(function ($role) {
            $usuarios = Especialista::whereHas('roles', function ($q) use ($role) {
                $q->where('role_id', $role->id);
            })->get();

            return [
                'id' => $role->id,
                'nombre' => $role->nombre,
                'slug' => $role->slug,
                'nivel' => $role->nivel,
                'total_usuarios' => $usuarios->count(),
                'activos' => $usuarios->where('is_active', true)->count(),
                'usuarios' => $usuarios->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'name' => $u->name,
                        'email' => $u->email,
                        'is_active' => $u->is_active,
                        'area' => $u->profesional?->area?->nombre,
                        'especialidad' => $u->profesional?->especialidad,
                    ];
                })->values(),
            ];
        });

        $areas = Area::with(['profesionales.especialista.roles'])->get()->map(function ($area) {
            $coordinador = $area->profesionales->first(function ($p) {
                return $p->especialista && $p->especialista->hasRole('area_coordinator');
            });

            $especialistas = $area->profesionales->filter(function ($p) {
                return $p->especialista && $p->especialista->hasRole('specialist');
            })->map(function ($p) {
                return [
                    'id' => $p->especialista->id,
                    'name' => $p->especialista->name,
                    'email' => $p->especialista->email,
                    'especialidad' => $p->especialidad,
                    'is_active' => $p->especialista->is_active,
                ];
            })->values();

            $psicosociales = $area->profesionales->filter(function ($p) {
                return $p->especialista && $p->especialista->hasRole('psychosocial_referent');
            })->map(function ($p) {
                return [
                    'id' => $p->especialista->id,
                    'name' => $p->especialista->name,
                    'email' => $p->especialista->email,
                    'is_active' => $p->especialista->is_active,
                ];
            })->values();

            return [
                'id' => $area->id,
                'nombre' => $area->nombre,
                'coordinador' => $coordinador ? [
                    'name' => $coordinador->especialista->name,
                    'email' => $coordinador->especialista->email,
                    'is_active' => $coordinador->especialista->is_active,
                ] : null,
                'especialistas' => $especialistas,
                'psicosociales' => $psicosociales,
                'total_especialistas' => $especialistas->count(),
            ];
        });

        return Inertia::render('Admin/Organigrama/Index', [
            'roles' => $roles,
            'areas' => $areas,
            'totales' => [
                'usuarios' => Especialista::count(),
                'activos' => Especialista::where('is_active', true)->count(),
                'inactivos' => Especialista::onlyTrashed()->count(),
                'areas' => Area::count(),
            ],
        ]);
    }
}
