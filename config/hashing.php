<?php

/**
 * Configuración de Hashing — Roadmap §4, C-12.
 *
 * Se usa Argon2id en lugar del bcrypt por defecto de Laravel,
 * para consistencia con el KDF de la clave simétrica.
 * Esto aplica al hash almacenado en users.password.
 */
return [
    'driver' => 'argon2id',

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => true,
    ],

    'argon' => [
        'memory'  => 65536, // 64 MB
        'threads' => 1,
        'time'    => 4,
        'verify'  => true,
    ],

    'rehash_on_login' => true,
];
