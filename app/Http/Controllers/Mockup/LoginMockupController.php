<?php

namespace App\Http\Controllers\Mockup;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginMockupController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:1'],
        ]);

        return redirect()
            ->route('usuarios')
            ->with([
                'message' => 'Inicio de sesión simulado. Autenticación real pendiente.',
                'variant' => 'info',
            ]);
    }
}
