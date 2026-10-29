<?php

use App\Exceptions\DecryptionException;
use App\Http\Middleware\EnforceAreaScope;
use App\Http\Middleware\EnsureIsSysadmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequirePasswordChange;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Azure App Service / reverse proxies
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            SecurityHeadersMiddleware::class,
            HandleInertiaRequests::class,
        ]);

        // Alias para uso en rutas
        $middleware->alias([
            'enforce_area_scope' => EnforceAreaScope::class,
            'sysadmin' => EnsureIsSysadmin::class,
            'require_password_change' => RequirePasswordChange::class,
            'role' => RoleMiddleware::class,
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

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();
            $handled = [403, 404, 419, 429, 500, 503];

            if ($request->expectsJson() || ! in_array($status, $handled, true)) {
                return $response;
            }

            // En local con debug, conservar la página de excepción de Laravel para 500.
            if ($status === 500 && config('app.debug')) {
                return $response;
            }

            $detail = null;
            if ($exception instanceof HttpExceptionInterface) {
                $message = trim((string) $exception->getMessage());
                $generic = [
                    '',
                    'Not Found',
                    'Forbidden',
                    'Unauthorized',
                    'Page Expired',
                    'Too Many Requests',
                    'Service Unavailable',
                    'Server Error',
                ];
                if ($message !== ''
                    && ! in_array($message, $generic, true)
                    && in_array($status, [403, 404, 419, 429], true)
                ) {
                    $detail = $message;
                }
            }

            return Inertia::render('Error', [
                'status' => $status,
                'detail' => $detail,
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
