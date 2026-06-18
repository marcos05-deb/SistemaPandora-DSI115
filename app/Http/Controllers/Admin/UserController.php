<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Especialista;
use App\Models\Profesional;
use App\Models\Role;
use App\Models\Area;
use App\Services\Crypto\KeyDerivationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly KeyDerivationService $kdfService,
    ) {}

    /**
     * List all users.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $roleFilter = $request->input('role');
        $trashed = $request->input('trashed');

        $query = Especialista::with(['roles', 'profesional', 'areas'])
            ->orderBy('id', 'desc');

        if ($trashed) {
            $query->onlyTrashed();
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', '%' . $search . '%')
                  ->orWhere('email', 'ilike', '%' . $search . '%');
            });
        }

        if ($roleFilter) {
            $query->whereHas('roles', function ($q) use ($roleFilter) {
                $q->where('id', $roleFilter);
            });
        }

        $users = $query->paginate(15)->withQueryString();

        $roles = Role::where('slug', '!=', 'sysadmin')->get();
        $areas = Area::all();

        $metrics = [
            'total' => Especialista::count(),
            'active' => Especialista::where('is_active', true)->count(),
            'inactive' => Especialista::onlyTrashed()->count(),
            'coordinators_ratio' => Especialista::whereHas('roles', function($q) {
                $q->where('slug', 'area_coordinator');
            })->count() . ' / ' . Area::count(),
            'psychosocial_referents' => Especialista::whereHas('roles', function($q) {
                $q->where('slug', 'psychosocial_referent');
            })->count(),
        ];

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'areas' => $areas,
            'metrics' => $metrics,
            'filters' => [
                'search' => $search,
                'role' => $roleFilter,
                'trashed' => $trashed,
            ]
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        $roles = Role::where('slug', '!=', 'sysadmin')->get();
        $areas = Area::all();

        return Inertia::render('Admin/Users/Form', [
            'roles' => $roles,
            'areas' => $areas,
        ]);
    }

    /**
     * Store a new user.
     */
    public function store(Request $request)
    {
        $sysadminRole = Role::where('slug', 'sysadmin')->first();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'role_id' => ['required', 'exists:roles,id', 'not_in:' . ($sysadminRole->id ?? 0)],
            // Datos del profesional (obligatorios)
            'area_id' => [
                'required',
                'exists:areas,id',
                function ($attribute, $value, $fail) use ($request) {
                    $role = Role::find($request->role_id);
                    if ($role && $role->slug === 'area_coordinator') {
                        $exists = \App\Models\Profesional::where('area_id', $value)
                            ->whereHas('especialista.roles', function ($q) {
                                $q->where('slug', 'area_coordinator');
                            })->exists();
                        if ($exists) {
                            $fail('Ya existe un coordinador activo asignado a esta área clínica.');
                        }
                    }
                }
            ],
            'especialidad' => 'required|string|max:255',
            'numero_registro' => 'required|string|max:255',
        ]);

        $user = null;

        DB::transaction(function () use ($validated, &$user) {
            // Generar kdf_salt
            $saltBase64 = $this->kdfService->generateSalt();

            // Crear usuario
            $user = Especialista::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'password' => Str::random(64),
                'kdf_salt' => $saltBase64,
            ]);

            // Asignar rol
            $role = Role::findOrFail($validated['role_id']);
            $user->roles()->attach($role);

            // Crear el perfil profesional (siempre obligatorio ahora que no hay sysadmin)
            Profesional::create([
                'user_id' => $user->id,
                'area_id' => $validated['area_id'],
                'especialidad' => $validated['especialidad'],
                'numero_registro' => $validated['numero_registro'],
            ]);
        });

        $signedUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'password.setup',
            now()->addHours(24),
            ['user' => $user->id]
        );

        $user->notify(new \App\Notifications\UserCreatedNotification($signedUrl));

        return redirect()->route('admin.users.index')
            ->with('message', 'Usuario creado. Se ha enviado el enlace de configuración al correo del usuario.')
            ->with('variant', 'success');
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit($id): Response
    {
        $user = Especialista::with(['roles', 'profesional', 'areas'])->findOrFail($id);
        $roles = Role::where('slug', '!=', 'sysadmin')->get();
        $areas = Area::all();

        return Inertia::render('Admin/Users/Form', [
            'user' => $user,
            'roles' => $roles,
            'areas' => $areas,
        ]);
    }

    /**
     * Update an existing user.
     */
    public function update(Request $request, $id)
    {
        $user = Especialista::with('profesional')->findOrFail($id);

        // Si el usuario que se está editando es un sysadmin, solo permitimos actualizar nombre, correo, etc
        if ($user->hasRole('sysadmin')) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
                'phone' => 'nullable|string|max:20',
                'is_active' => 'boolean',
            ]);

            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            return redirect()->route('admin.users.index')->with('message', 'Perfil de administrador actualizado.')->with('variant', 'success');
        }

        $sysadminRole = Role::where('slug', 'sysadmin')->first();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'role_id' => ['required', 'exists:roles,id', 'not_in:' . ($sysadminRole->id ?? 0)],
            // Datos del profesional (obligatorios)
            'area_id' => [
                'required',
                'exists:areas,id',
                function ($attribute, $value, $fail) use ($request, $user) {
                    $role = Role::find($request->role_id);
                    if ($role && $role->slug === 'area_coordinator') {
                        $exists = \App\Models\Profesional::where('area_id', $value)
                            ->where('user_id', '!=', $user->id)
                            ->whereHas('especialista.roles', function ($q) {
                                $q->where('slug', 'area_coordinator');
                            })->exists();
                        if ($exists) {
                            $fail('Ya existe un coordinador activo asignado a esta área clínica.');
                        }
                    }
                }
            ],
            'especialidad' => 'required|string|max:255',
            'numero_registro' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($validated, $user) {
            // Actualizar datos básicos (No password)
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Actualizar rol
            $role = Role::findOrFail($validated['role_id']);
            $user->roles()->sync([$role->id]);

            // Gestionar perfil profesional
            if ($user->profesional) {
                // Update existing
                $user->profesional()->update([
                    'area_id' => $validated['area_id'],
                    'especialidad' => $validated['especialidad'],
                    'numero_registro' => $validated['numero_registro'],
                ]);
            } else {
                // Create new
                Profesional::create([
                    'user_id' => $user->id,
                    'area_id' => $validated['area_id'],
                    'especialidad' => $validated['especialidad'],
                    'numero_registro' => $validated['numero_registro'],
                ]);
            }
        });

        return redirect()->route('admin.users.index')->with('message', 'Usuario actualizado exitosamente.')->with('variant', 'success');
    }

    /**
     * Delete (deactivate) a user.
     */
    public function destroy($id)
    {
        $user = Especialista::findOrFail($id);
        
        // Prevent deleting oneself
        if ($user->id === auth()->id()) {
            return redirect()->back()->withErrors(['error' => 'No puedes desactivar tu propia cuenta.']);
        }

        DB::transaction(function () use ($user) {
            $user->update(['is_active' => false]);
            $user->delete(); // Soft delete
        });

        return redirect()->back()->with('message', 'Usuario desactivado. Ya no podrá ingresar al sistema.')->with('variant', 'success');
    }
}
