<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Especialista;
use App\Services\AuthAccessAuditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * LogoutController — HU-02.
 *
 * Destrucción completa e irrecuperable del estado de autenticación,
 * material criptográfico y token JWT.
 *
 * Secuencia de destrucción (orden estricto):
 * 1. Borrar _sym_key antes de invalidar sesión.
 * 2. Auth::logout() para desvincular el guard.
 * 3. Invalidar sesión completa.
 * 4. Regenerar token CSRF.
 * 5. Eliminar cookie JWT (pandora_token).
 * 6. Redirigir a /login.
 */
class LogoutController extends Controller
{
    public function destroy(Request $request, AuthAccessAuditor $authAuditor)
    {
        $user = Auth::guard('web')->user();
        if ($user instanceof Especialista) {
            $authAuditor->logout($user, $request);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->withCookie(
            cookie()->forget('pandora_token')
        );
    }
}
