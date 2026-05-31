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
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(
        private readonly KeyDerivationService $kdfService,
    ) {}

    /**
     * List all users.
     */
    public function index(): Response
    {
        $users = Especialista::with(['roles', 'profesional', 'areas'])
            ->orderBy('id', 'desc')
            ->get();

        $roles = Role::where('slug', '!=', 'sysadmin')->get();
        $areas = Area::all();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
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
            'password' => ['required', Password::defaults()],
            'role_id' => ['required', 'exists:roles,id', 'not_in:' . ($sysadminRole->id ?? 0)],
            // Datos del profesional (obligatorios)
            'area_id' => 'required|exists:areas,id',
            'especialidad' => 'required|string|max:255',
            'numero_registro' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            // Generar kdf_salt
            $saltBase64 = $this->kdfService->generateSalt();

            // Crear usuario (la contraseña se hashea automáticamente por el cast 'hashed' en Especialista)
            $user = Especialista::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
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

        return redirect()->back()->with('message', 'Usuario creado exitosamente.')->with('variant', 'success');
    }

    /**
     * Update an existing user.
     */
    public function update(Request $request, $id)
    {
        $user = Especialista::with('profesional')->findOrFail($id);

        // Si el usuario que se está editando es un sysadmin, solo permitimos actualizar nombre y correo
        if ($user->hasRole('sysadmin')) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            ]);

            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            return redirect()->back()->with('message', 'Perfil de administrador actualizado.')->with('variant', 'success');
        }

        $sysadminRole = Role::where('slug', 'sysadmin')->first();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role_id' => ['required', 'exists:roles,id', 'not_in:' . ($sysadminRole->id ?? 0)],
            // Datos del profesional (obligatorios)
            'area_id' => 'required|exists:areas,id',
            'especialidad' => 'required|string|max:255',
            'numero_registro' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($validated, $user) {
            // Actualizar datos básicos (No password)
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
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

        return redirect()->back()->with('message', 'Usuario actualizado exitosamente.')->with('variant', 'success');
    }

    /**
     * Delete a user.
     */
    public function destroy($id)
    {
        $user = Especialista::findOrFail($id);
        
        // Prevent deleting oneself
        if ($user->id === auth()->id()) {
            return redirect()->back()->withErrors(['error' => 'No puedes eliminar tu propia cuenta.']);
        }

        DB::transaction(function () use ($user) {
            $user->roles()->detach();
            if ($user->profesional) {
                $user->profesional()->forceDelete(); // Hard delete to prevent FK violation since users table doesn't have SoftDeletes
            }
            $user->delete();
        });

        return redirect()->back()->with('message', 'Usuario eliminado.')->with('variant', 'success');
    }
}
