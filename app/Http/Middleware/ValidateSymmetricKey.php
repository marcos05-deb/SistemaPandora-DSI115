<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ValidateSymmetricKey — Roadmap §HU-01, HU-05.
 *
 * Verifica que la clave simétrica (_sym_key) esté presente en sesión.
 * Se aplica a rutas que operan con datos cifrados (expedientes, pacientes).
 *
 * Si la clave no está en sesión (sesión expirada), redirige al login.
 */
class ValidateSymmetricKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->session()->has('_sym_key')) {
            if ($request->wantsJson() || $request->inertia()) {
                return response()->json([
                    'message' => 'Sesión expirada. Por favor, inicie sesión nuevamente.',
                ], 401);
            }

            return redirect()->route('login')->withErrors([
                'session' => 'Su sesión expiró. Por favor, inicie sesión nuevamente.',
            ]);
        }

        return $next($request);
    }
}
