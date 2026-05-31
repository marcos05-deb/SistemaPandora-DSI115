<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * LogoutController — Roadmap §HU-02.
 *
 * Destrucción completa e irrecuperable del estado de autenticación
 * y material criptográfico.
 *
 * Secuencia de destrucción (orden estricto):
 * 1. Borrar _sym_key antes de invalidar sesión.
 * 2. Auth::logout() para desvincular el guard.
 * 3. Invalidar sesión completa.
 * 4. Regenerar token CSRF.
 * 5. Redirigir a /login.
 */
class LogoutController extends Controller
{
    public function destroy(Request $request)
    {
        // 1. Borrar la clave simétrica antes de invalidar la sesión
        session()->forget('_sym_key');

        // 2. Desloguear al usuario en el guard (necesario para pruebas y limpieza de estado de auth)
        Auth::guard('web')->logout();

        // 3. Invalidar la sesión completa
        $request->session()->invalidate();

        // 4. Regenerar el token CSRF
        $request->session()->regenerateToken();

        // 5. Redirigir a login
        return redirect('/login');
    }
}
