<?php

/**
 * Configuración de Autenticación.
 *
 * El provider 'users' apunta al modelo Especialista que opera
 * sobre la tabla 'users' existente. Esto mantiene la compatibilidad
 * con la DB original mientras se usa la nomenclatura del roadmap.
 *
 * Guard: jwt mediante cookie HttpOnly (token JWT exp. 8h).
 * HU-01: Autenticación mediante token JWT con expiración de 8 horas.
 */
return [
    'defaults' => [
        'guard'     => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Especialista::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider'  => 'users',
            'table'     => 'password_reset_tokens',
            'expire'    => 60,
            'throttle'  => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
