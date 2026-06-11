<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $csp = "default-src 'self'; script-src 'self'; style-src 'self'; font-src 'self' https://fonts.bunny.net; object-src 'none'; frame-ancestors 'none';";
        
        if (!in_array(config('app.env'), ['production', 'staging'])) {
            // Vite dev server requires unsafe-inline, unsafe-eval, and the Vite host
            $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:5173; style-src 'self' 'unsafe-inline' https://fonts.bunny.net http://localhost:5173; font-src 'self' https://fonts.bunny.net; connect-src 'self' ws://localhost:5173 http://localhost:5173; object-src 'none'; frame-ancestors 'none';";
        }

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
