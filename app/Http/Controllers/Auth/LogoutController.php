<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/**
 * LogoutController — Roadmap §HU-02.
 *
 * Destrucción completa e irrecuperable del estado de autenticación
 * y material criptográfico.
 *
 * Secuencia de destrucción (orden estricto):
 * 1. Borrar _sym_key antes de invalidar sesión.
 * 2. Invalidar sesión completa.
 * 3. Regenerar token CSRF.
 * 4. Redirigir a /login.
 */
class LogoutController extends Controller
{
    public function destroy(Request $request)
    {
        // 1. Borrar la clave simétrica antes de invalidar la sesión
        session()->forget('_sym_key');

        // 2. Invalidar la sesión completa
        Session::invalidate();

        // 3. Regenerar el token CSRF
        Session::regenerateToken();

        // 4. Redirigir a login
        return redirect('/login');
    }
}
