<?php

use App\Http\Controllers\Mockup\LoginMockupController;
use App\Http\Controllers\Mockup\PatientRegistrationMockupController;
use App\Http\Controllers\Mockup\PrivacyCodesMockupController;
use App\Http\Controllers\Mockup\SecureSearchMockupController;
use App\Http\Controllers\Mockup\SessionLogoutMockupController;
use App\Http\Controllers\Mockup\UsersMockupController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/login', [LoginMockupController::class, 'show'])->name('login');
Route::post('/login', [LoginMockupController::class, 'store'])->name('login.store');

Route::get('/usuarios', [UsersMockupController::class, 'index'])->name('usuarios');
Route::get('/codigos-privacidad', [PrivacyCodesMockupController::class, 'index'])->name('codigos-privacidad');
Route::get('/registro-paciente', [PatientRegistrationMockupController::class, 'create'])->name('registro-paciente');
Route::post('/registro-paciente', [PatientRegistrationMockupController::class, 'store'])->name('registro-paciente.store');
Route::get('/busqueda-segura', [SecureSearchMockupController::class, 'index'])->name('busqueda-segura');
Route::post('/busqueda-segura', [SecureSearchMockupController::class, 'search'])->name('busqueda-segura.search');
Route::get('/cierre-sesion', [SessionLogoutMockupController::class, 'show'])->name('cierre-sesion');

Route::redirect('/mockups/usuarios', '/usuarios');
Route::redirect('/mockups/codigos-privacidad', '/codigos-privacidad');
Route::redirect('/mockups/registro-paciente', '/registro-paciente');
Route::redirect('/mockups/busqueda-segura', '/busqueda-segura');
Route::redirect('/mockups/cierre-sesion', '/cierre-sesion');
