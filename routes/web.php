<?php

use App\Http\Controllers\Admin\BitacoraController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrganigramaController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AlertaPreventivaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordSetupController;
use App\Http\Controllers\CitaController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\DerivacionController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\IndicadoresController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\RubricaController;
use App\Http\Controllers\SecureSearchController;
use App\Http\Controllers\SolicitudCorreccionController;
use App\Http\Resources\PacienteResource;
use App\Models\Area;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\Scopes\AreaScope;
use App\Services\DashboardAlertOverviewService;
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
    Route::get('/password/setup', [PasswordSetupController::class, 'show'])->name('password.setup')->withoutMiddleware('require_password_change');
    Route::post('/password/setup', [PasswordSetupController::class, 'update'])->name('password.setup.store')->withoutMiddleware('require_password_change');

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

            $pacientesQuery = Paciente::with(['expedientes' => function ($q) {
                $q->withoutGlobalScope(AreaScope::class)
                    ->select('id', 'paciente_id', 'area_id', 'estado', 'created_at', 'updated_at');
            }])
                ->where('creado_por_profesional_id', $profesionalId)
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get();

            $pacientes = PacienteResource::collection($pacientesQuery)->resolve();

            $stats = [
                'total' => Paciente::where('creado_por_profesional_id', $profesionalId)->count(),
                'activos' => Expediente::whereHas('paciente', function ($q) use ($profesionalId) {
                    $q->where('creado_por_profesional_id', $profesionalId);
                })->where('estado', '!=', 'cerrado')->count(),
            ];

            $areasDisponibles = Area::select('id', 'nombre')->orderBy('nombre')->get();
        }

        // Coordinador y especialista: pacientes con expedientes en su área
        if ($user->hasRole('area_coordinator') || $user->hasRole('specialist')) {
            $pacientesQuery = Paciente::with('expedientes')
                ->whereHas('expedientes', function ($q) use ($areaIds) {
                    $q->whereIn('area_id', $areaIds);
                })
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get();

            $pacientes = PacienteResource::collection($pacientesQuery)->resolve();

            $stats = [
                'total' => Paciente::whereHas('expedientes', function ($q) use ($areaIds) {
                    $q->whereIn('area_id', $areaIds);
                })->count(),
                'activos' => Expediente::whereIn('area_id', $areaIds)->where('estado', '!=', 'cerrado')->count(),
            ];
        }

        $alertOverview = app(DashboardAlertOverviewService::class)
            ->forUser($user);

        return Inertia::render('Dashboard', [
            'pacientes' => $pacientes,
            'stats' => $stats,
            'areasDisponibles' => $areasDisponibles,
            'alertOverview' => $alertOverview,
        ]);
    })->name('dashboard');

    // Clinical Routes
    Route::middleware('role:psychosocial_referent')->group(function () {
        Route::get('/pacientes/create', [PacienteController::class, 'create'])->name('pacientes.create');
        Route::post('/pacientes', [PacienteController::class, 'store'])->name('pacientes.store');

        Route::get('/pacientes/{paciente}/editar', [PacienteController::class, 'edit'])
            ->name('pacientes.edit')
            ->middleware('throttle:30,1');
        Route::patch('/pacientes/{paciente}', [PacienteController::class, 'update'])
            ->name('pacientes.update')
            ->middleware('throttle:20,1');
        Route::post('/pacientes/{paciente}/solicitudes-correccion', [SolicitudCorreccionController::class, 'storeDatos'])
            ->name('pacientes.solicitudes-correccion.store')
            ->middleware('throttle:10,1');

        Route::post('/pacientes/{paciente}/derivar', [DerivacionController::class, 'store'])->name('pacientes.derivar.store');
    });

    Route::middleware('role:psychosocial_referent|specialist|area_coordinator|sysadmin')->group(function () {
        Route::get('/pacientes', [PacienteController::class, 'index'])->name('pacientes.index')->middleware('throttle:30,1');
        Route::get('/pacientes/{carnet}', [PacienteController::class, 'show'])->name('pacientes.show')->middleware('throttle:30,1');

        Route::middleware('enforce_area_scope')->group(function () {
            Route::get('/citas', [CitaController::class, 'index'])->name('citas.index');
            Route::get('/pacientes/{paciente}/historial', [HistorialController::class, 'show'])->name('pacientes.historial');
            Route::get('/expedientes/{expediente}/consultas/create', [ConsultaController::class, 'create'])->name('consultas.create');
            Route::post('/expedientes/{expediente}/consultas', [ConsultaController::class, 'store'])->name('consultas.store');
            Route::post('/expedientes/{expediente}/cerrar', [ExpedienteController::class, 'close'])->name('expedientes.cerrar');
            Route::patch('/expedientes/{expediente}', [ExpedienteController::class, 'update'])->name('expedientes.update');
            Route::post('/expedientes/{expediente}/solicitudes-correccion', [SolicitudCorreccionController::class, 'storeClinico'])
                ->name('expedientes.solicitudes-correccion.store')
                ->middleware('throttle:10,1');

            Route::post('/expedientes/{expediente}/citas', [CitaController::class, 'store'])->name('citas.store');
            Route::scopeBindings()->group(function () {
                Route::patch('/expedientes/{expediente}/citas/{cita}/asistencia', [CitaController::class, 'actualizarAsistencia'])->name('citas.asistencia');
                Route::patch('/expedientes/{expediente}/citas/{cita}/reprogramar', [CitaController::class, 'reprogramar'])->name('citas.reprogramar');
                Route::patch('/expedientes/{expediente}/citas/{cita}/cancelar', [CitaController::class, 'cancelar'])->name('citas.cancelar');
            });
        });
    });

    Route::get('/indicadores', [IndicadoresController::class, 'index'])
        ->middleware('throttle:30,1')
        ->name('indicadores.index');

    Route::get('/reportes', [ReportesController::class, 'index'])
        ->middleware('throttle:30,1')
        ->name('reportes.index');
    Route::get('/reportes/generar', [ReportesController::class, 'generate'])
        ->middleware('throttle:10,1')
        ->name('reportes.generate');
    Route::get('/reportes/exportar/pdf', [ReportesController::class, 'exportPdf'])
        ->middleware('throttle:5,1')
        ->name('reportes.export.pdf');
    Route::get('/reportes/exportar/excel', [ReportesController::class, 'exportExcel'])
        ->middleware('throttle:5,1')
        ->name('reportes.export.excel');

    Route::get('/rubricas', [RubricaController::class, 'index'])
        ->middleware('throttle:30,1')
        ->name('rubricas.index');
    Route::get('/rubricas/versiones/crear', [RubricaController::class, 'create'])
        ->middleware('throttle:30,1')
        ->name('rubricas.create');
    Route::post('/rubricas/versiones', [RubricaController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('rubricas.store');
    Route::get('/rubricas/versiones/{version}', [RubricaController::class, 'show'])
        ->whereUuid('version')
        ->middleware('throttle:30,1')
        ->name('rubricas.show');

    Route::get('/alertas', [AlertaPreventivaController::class, 'index'])
        ->middleware('throttle:30,1')
        ->name('alertas.index');
    Route::get('/alertas/{alerta}', [AlertaPreventivaController::class, 'show'])
        ->whereUuid('alerta')
        ->middleware('throttle:30,1')
        ->name('alertas.show');
    Route::post('/alertas/{alerta}/seguimientos', [AlertaPreventivaController::class, 'storeSeguimiento'])
        ->whereUuid('alerta')
        ->middleware('throttle:20,1')
        ->name('alertas.seguimientos.store');
    Route::post('/alertas/{alerta}/cerrar', [AlertaPreventivaController::class, 'cerrar'])
        ->whereUuid('alerta')
        ->middleware('throttle:10,1')
        ->name('alertas.cerrar');
    Route::post('/alertas/{alerta}/descartar', [AlertaPreventivaController::class, 'descartar'])
        ->whereUuid('alerta')
        ->middleware('throttle:10,1')
        ->name('alertas.descartar');
    Route::post('/notificaciones/{notification}/leida', [AlertaPreventivaController::class, 'markNotificationAsRead'])
        ->whereUuid('notification')
        ->middleware('throttle:30,1')
        ->name('notificaciones.leida');

    // Secure Search Routes (HU-06)
    Route::get('/busqueda-segura', [SecureSearchController::class, 'index'])->name('busqueda-segura');
    Route::post('/busqueda-segura', [SecureSearchController::class, 'search'])->name('busqueda-segura.search');

    // Admin Routes
    Route::middleware('sysadmin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

        // User management
        Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('admin.users.create');
        Route::post('/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::get('/users/{id}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
        Route::put('/users/{id}', [UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('admin.users.destroy');

        Route::get('/bitacora', [BitacoraController::class, 'index'])
            ->middleware('throttle:30,1')
            ->name('admin.bitacora.index');
        Route::get('/bitacora/exportar', [BitacoraController::class, 'export'])
            ->middleware('throttle:5,1')
            ->name('admin.bitacora.export');

        // Patients audit (solo consulta; sin edición de datos clínicos/personales)
        Route::get('/pacientes', [App\Http\Controllers\Admin\PacienteController::class, 'index'])->name('admin.pacientes.index');
        Route::get('/pacientes/correcciones/{correccion}', [App\Http\Controllers\Admin\PacienteController::class, 'showCorreccion'])
            ->name('admin.pacientes.correcciones.show');
        Route::post('/pacientes/correcciones/{correccion}/revisar', [App\Http\Controllers\Admin\PacienteController::class, 'marcarRevisado'])
            ->name('admin.pacientes.correcciones.revisar')
            ->middleware('throttle:30,1');
        Route::post('/pacientes/solicitudes/{solicitud}/aprobar', [App\Http\Controllers\Admin\PacienteController::class, 'aprobarSolicitud'])
            ->name('admin.pacientes.solicitudes.aprobar')
            ->middleware('throttle:30,1');
        Route::post('/pacientes/solicitudes/{solicitud}/rechazar', [App\Http\Controllers\Admin\PacienteController::class, 'rechazarSolicitud'])
            ->name('admin.pacientes.solicitudes.rechazar')
            ->middleware('throttle:30,1');

        // Organigrama
        Route::get('/organigrama', [OrganigramaController::class, 'index'])->name('admin.organigrama.index');

        // Calendario de Citas
        Route::get('/citas', [App\Http\Controllers\Admin\CitaController::class, 'index'])->name('admin.citas');
        Route::get('/api/citas/{date}', [App\Http\Controllers\Admin\CitaController::class, 'citasPorDia'])->name('admin.api.citas.dia');
    });

    Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout');
});
