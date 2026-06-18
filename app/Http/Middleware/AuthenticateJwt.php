<?php

namespace App\Http\Middleware;

use App\Models\Especialista;
use App\Services\Auth\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateJwt
{
    public function __construct(
        private readonly JwtService $jwt,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            return $next($request);
        }

        $token = $request->cookie('pandora_token');
        if (!$token) {
            return redirect()->route('login');
        }

        $userId = $this->jwt->validate($token);
        if (!$userId) {
            return $this->clearAndRedirect();
        }

        $user = Especialista::find($userId);
        if (!$user || !$user->is_active) {
            return $this->clearAndRedirect();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return $next($request);
    }

    private function clearAndRedirect()
    {
        return redirect()->route('login')->withCookie(
            cookie()->forget('pandora_token')
        );
    }
}
