<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsSysadmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $especialista = Auth::user();

        if (! $especialista || ! $especialista->hasRole('sysadmin')) {
            abort(403, 'Acceso denegado. Esta sección es solo para el Administrador del sistema.');
        }

        return $next($request);
    }
}
