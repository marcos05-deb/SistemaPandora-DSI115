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
        'memory'  => env('HASH_ARGON_MEMORY', 65536), // 64 MB
        'threads' => env('HASH_ARGON_THREADS', 1),
        'time'    => env('HASH_ARGON_TIME', 4),
        'verify'  => true,
    ],

    'kdf' => [
        'opslimit' => env('KDF_OPSLIMIT', SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE),
        'memlimit' => env('KDF_MEMLIMIT', SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE),
    ],

    'rehash_on_login' => true,
];
