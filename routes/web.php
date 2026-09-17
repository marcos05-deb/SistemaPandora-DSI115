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
    Route::redirect('/', '/login');
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

// --- Rutas protegidas (Auth con JWT) ---
Route::middleware(['auth', 'require_password_change'])->group(function () {
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
        $areaIds = $user->areas()->pluck('areas.id')->toArray();
        $areasDisponibles = [];

        // Solo el referente psicosocial ve los pacientes (y únicamente los que él registró)
        if ($user->hasRole('psychosocial_referent') && $user->profesional) {
            $profesionalId = $user->profesional->id;

            $pacientesQuery = \App\Models\Paciente::with(['expedientes' => function ($q) {
                $q->withoutGlobalScope(\App\Models\Scopes\AreaScope::class)
                  ->select('id', 'paciente_id', 'area_id', 'estado', 'created_at', 'updated_at');
            }])
                ->where('creado_por_profesional_id', $profesionalId)
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get();

            $pacientes = \App\Http\Resources\PacienteResource::collection($pacientesQuery)->resolve();

            $stats = [
                'total' => \App\Models\Paciente::where('creado_por_profesional_id', $profesionalId)->count(),
                'activos' => \App\Models\Expediente::whereHas('paciente', function ($q) use ($profesionalId) {
                    $q->where('creado_por_profesional_id', $profesionalId);
                })->where('estado', '!=', 'cerrado')->count(),
            ];
            
            $areasDisponibles = \App\Models\Area::select('id', 'nombre')->orderBy('nombre')->get();
        }

        // Coordinador y especialista: pacientes con expedientes en su área
        if ($user->hasRole('area_coordinator') || $user->hasRole('specialist')) {
            $pacientesQuery = \App\Models\Paciente::with('expedientes')
                ->whereHas('expedientes', function ($q) use ($areaIds) {
                    $q->whereIn('area_id', $areaIds);
                })
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get();

            $pacientes = \App\Http\Resources\PacienteResource::collection($pacientesQuery)->resolve();

            $stats = [
                'total' => \App\Models\Paciente::whereHas('expedientes', function ($q) use ($areaIds) {
                    $q->whereIn('area_id', $areaIds);
                })->count(),
                'activos' => \App\Models\Expediente::whereIn('area_id', $areaIds)->where('estado', '!=', 'cerrado')->count(),
            ];
        }

        return Inertia::render('Dashboard', [
            'pacientes' => $pacientes,
            'stats' => $stats,
            'areasDisponibles' => $areasDisponibles,
        ]);
    })->name('dashboard');

    // Clinical Routes
    Route::middleware('role:psychosocial_referent')->group(function () {
        Route::get('/pacientes/create', [\App\Http\Controllers\PacienteController::class, 'create'])->name('pacientes.create');
        Route::post('/pacientes', [\App\Http\Controllers\PacienteController::class, 'store'])->name('pacientes.store');
        
        Route::post('/pacientes/{paciente}/derivar', [\App\Http\Controllers\DerivacionController::class, 'store'])->name('pacientes.derivar.store');
    });

    Route::middleware('role:psychosocial_referent|specialist|area_coordinator|sysadmin')->group(function () {
        Route::get('/pacientes', [\App\Http\Controllers\PacienteController::class, 'index'])->name('pacientes.index')->middleware('throttle:30,1');
        Route::get('/pacientes/{carnet}', [\App\Http\Controllers\PacienteController::class, 'show'])->name('pacientes.show')->middleware('throttle:30,1');
        
        Route::middleware('enforce_area_scope')->group(function () {
            Route::get('/citas', [\App\Http\Controllers\CitaController::class, 'index'])->name('citas.index');
            Route::get('/pacientes/{paciente}/historial', [\App\Http\Controllers\HistorialController::class, 'show'])->name('pacientes.historial');
            
            Route::get('/expedientes/{expediente}/consultas/create', [\App\Http\Controllers\ConsultaController::class, 'create'])->name('consultas.create');
            Route::post('/expedientes/{expediente}/consultas', [\App\Http\Controllers\ConsultaController::class, 'store'])->name('consultas.store');
            Route::post('/expedientes/{expediente}/cerrar', [\App\Http\Controllers\ExpedienteController::class, 'close'])->name('expedientes.cerrar');
            Route::patch('/expedientes/{expediente}', [\App\Http\Controllers\ExpedienteController::class, 'update'])->name('expedientes.update');
            
            Route::post('/expedientes/{expediente}/citas', [\App\Http\Controllers\CitaController::class, 'store'])->name('citas.store');
            Route::scopeBindings()->group(function () {
                Route::patch('/expedientes/{expediente}/citas/{cita}/asistencia', [\App\Http\Controllers\CitaController::class, 'actualizarAsistencia'])->name('citas.asistencia');
                Route::patch('/expedientes/{expediente}/citas/{cita}/reprogramar', [\App\Http\Controllers\CitaController::class, 'reprogramar'])->name('citas.reprogramar');
                Route::patch('/expedientes/{expediente}/citas/{cita}/cancelar', [\App\Http\Controllers\CitaController::class, 'cancelar'])->name('citas.cancelar');
            });
        });
    });

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

        // Organigrama
        Route::get('/organigrama', [\App\Http\Controllers\Admin\OrganigramaController::class, 'index'])->name('admin.organigrama.index');
        
        // Calendario de Citas
        Route::get('/citas', [\App\Http\Controllers\Admin\CitaController::class, 'index'])->name('admin.citas');
        Route::get('/api/citas/{date}', [\App\Http\Controllers\Admin\CitaController::class, 'citasPorDia'])->name('admin.api.citas.dia');
    });

    Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout');
});
