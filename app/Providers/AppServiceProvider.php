<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Rate Limiting: capa secundaria de protección (10 intentos por IP+email).
     * El bloqueo principal de cuenta es a 3 intentos (ver LoginController).
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower($request->input('email')) . '|' . $request->ip();

            return Limit::perMinutes(10, 10)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    $retryAfter = $headers['Retry-After'] ?? 900;

                    if ($request->wantsJson()) {
                        return response()->json([
                            'message' => 'Demasiados intentos de inicio de sesión. Intente nuevamente en 15 minutos.',
                        ], 429, $headers);
                    }

                    return back()->withErrors([
                        'throttle' => 'Demasiados intentos de inicio de sesión. Intente nuevamente en 15 minutos.',
                    ])->onlyInput('email');
                });
        });
    }
}
