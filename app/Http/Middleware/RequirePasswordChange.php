<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->must_change_password) {
            // Permitir acceso a la vista de configuración y a la acción de logout
            if (!$request->routeIs('password.setup') && !$request->routeIs('password.setup.store') && !$request->routeIs('logout')) {
                return redirect()->route('password.setup');
            }
        }

        return $next($request);
    }
}
