<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes — Sprint 1
|--------------------------------------------------------------------------
|
| Roadmap §HU-01: POST /login con rate limiting.
| Roadmap §HU-02: POST /logout con destrucción de sesión.
| Rutas protegidas requieren middleware 'auth'.
|
*/

// --- Rutas públicas (Guest) ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::redirect('/', '/login');

// --- Rutas protegidas (Auth con JWT) ---
Route::middleware(['auth.jwt', 'require_password_change'])->group(function () {
    // Password Setup (Primer Ingreso)
    Route::get('/password/setup', [\App\Http\Controllers\Auth\PasswordSetupController::class, 'show'])->name('password.setup')->withoutMiddleware('require_password_change');
    Route::post('/password/setup', [\App\Http\Controllers\Auth\PasswordSetupController::class, 'update'])->name('password.setup.store')->withoutMiddleware('require_password_change');

    // Clinical Dashboard
    Route::get('/dashboard', function () {
        if (auth()->user()->hasRole('sysadmin')) {
            return redirect()->route('admin.dashboard');
        }

        $user = auth()->user();
        $pacientes = [];
        $stats = [
            'total' => 0,
            'activos' => 0,
        ];

        // Solo el referente psicosocial ve los pacientes (y únicamente los que él registró)
        if ($user->hasRole('psychosocial_referent')) {
            $profesionalId = $user->profesional->id;

            $pacientes = \App\Models\Paciente::with('expedientes')
                ->where('creado_por_profesional_id', $profesionalId)
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get()
                ->map(function ($paciente) {
                    return [
                        'carnet' => $paciente->carnet,
                        'nombre_completo' => $paciente->nombre_completo,
                        'created_at' => $paciente->created_at->format('Y-m-d H:i'),
                    ];
                });

            $stats = [
                'total' => \App\Models\Paciente::where('creado_por_profesional_id', $profesionalId)->count(),
                'activos' => \App\Models\Expediente::whereHas('paciente', function ($q) use ($profesionalId) {
                    $q->where('creado_por_profesional_id', $profesionalId);
                })->count(),
            ];
        }

        return Inertia::render('Dashboard', [
            'pacientes' => $pacientes,
            'stats' => $stats,
        ]);
    })->name('dashboard');

    // Clinical Routes
    Route::get('/pacientes', [\App\Http\Controllers\PacienteController::class, 'index'])->name('pacientes.index');
    Route::get('/pacientes/create', [\App\Http\Controllers\PacienteController::class, 'create'])->name('pacientes.create');
    Route::post('/pacientes', [\App\Http\Controllers\PacienteController::class, 'store'])->name('pacientes.store');
    Route::get('/pacientes/{carnet}', [\App\Http\Controllers\PacienteController::class, 'show'])->name('pacientes.show');

    // Secure Search Routes (HU-06)
    Route::get('/busqueda-segura', [\App\Http\Controllers\SecureSearchController::class, 'index'])->name('busqueda-segura');
    Route::post('/busqueda-segura', [\App\Http\Controllers\SecureSearchController::class, 'search'])->name('busqueda-segura.search');

    // Admin Routes
    Route::middleware('sysadmin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('admin.dashboard');
        
        // User management
        Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('admin.users.index');
        Route::get('/users/create', [\App\Http\Controllers\Admin\UserController::class, 'create'])->name('admin.users.create');
        Route::post('/users', [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('admin.users.store');
        Route::get('/users/{id}/edit', [\App\Http\Controllers\Admin\UserController::class, 'edit'])->name('admin.users.edit');
        Route::put('/users/{id}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/users/{id}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('admin.users.destroy');

        // Patients audit
        Route::get('/pacientes', [\App\Http\Controllers\Admin\PacienteController::class, 'index'])->name('admin.pacientes.index');
    });

    Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout');
});
