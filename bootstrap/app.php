<?php

use App\Exceptions\DecryptionException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Alias para uso en rutas
        $middleware->alias([
            'enforce_area_scope' => \App\Http\Middleware\EnforceAreaScope::class,
            'sysadmin' => \App\Http\Middleware\EnsureIsSysadmin::class,
            'require_password_change' => \App\Http\Middleware\RequirePasswordChange::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (DecryptionException $e, Request $request) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Sesión expirada. Por favor, inicie sesión nuevamente.',
                ], 401);
            }

            return redirect()->route('login')->withErrors([
                'session' => 'Su sesión expiró durante la operación.',
            ]);
        });
    })->create();
