<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => 'PANDORA',
            'appSubtitle' => 'Sistema de Gestión Clínica',
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'variant' => fn () => $request->session()->get('variant', 'success'),
            ],
        ];
    }
}
