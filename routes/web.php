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

// --- Rutas protegidas (Auth) ---
Route::middleware(['auth', 'require_password_change'])->group(function () {
    // Password Setup (Primer Ingreso)
    Route::get('/password/setup', [\App\Http\Controllers\Auth\PasswordSetupController::class, 'show'])->name('password.setup')->withoutMiddleware('require_password_change');
    Route::post('/password/setup', [\App\Http\Controllers\Auth\PasswordSetupController::class, 'update'])->name('password.setup.store')->withoutMiddleware('require_password_change');

    // Clinical Dashboard
    Route::get('/dashboard', function () {
        if (auth()->user()->hasRole('sysadmin')) {
            return redirect()->route('admin.dashboard');
        }
        return Inertia::render('Dashboard');
    })->name('dashboard');

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
    });

    Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout');
});
