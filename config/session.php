<?php

use Illuminate\Support\Str;

/**
 * Configuración de Sesión — Roadmap §HU-01c, §4.
 *
 * - Driver: database (para sesión única por Especialista).
 * - Lifetime: 120 minutos (sesión deslizante).
 * - secure: true en producción, false en desarrollo sin HTTPS.
 * - same_site: strict (protección CSRF adicional).
 */
return [
    'driver'          => env('SESSION_DRIVER', 'database'),
    'lifetime'        => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt'         => env('SESSION_ENCRYPT', false),
    'files'           => storage_path('framework/sessions'),
    'connection'      => env('SESSION_CONNECTION'),
    'table'           => env('SESSION_TABLE', 'sessions'),
    'store'           => env('SESSION_STORE'),
    'lottery'         => [2, 100],
    'cookie'          => env('SESSION_COOKIE', Str::slug(env('APP_NAME', 'pandora'), '_') . '_session'),
    'path'            => env('SESSION_PATH', '/'),
    'domain'          => env('SESSION_DOMAIN'),
    'secure'          => env('SESSION_SECURE_COOKIE', false),
    'http_only'       => true,
    'same_site'       => 'strict',
    'partitioned'     => env('SESSION_PARTITIONED_COOKIE', false),
];
