<?php

use App\Services\Crypto\KeyDerivationService;

uses(Tests\TestCase::class);

/**
 * Tests unitarios del KeyDerivationService — HU-01.
 */

it('genera_sal_de_longitud_correcta', function () {
    $service = new KeyDerivationService();
    $saltBase64 = $service->generateSalt();
    $salt = base64_decode($saltBase64, strict: true);

    expect($salt)->not->toBeFalse()
        ->and(strlen($salt))->toBe(SODIUM_CRYPTO_PWHASH_SALTBYTES); // 16
});

it('deriva_clave_de_32_bytes', function () {
    $service = new KeyDerivationService();
    $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
    $key = $service->derive('password_segura_14', $salt);

    expect(strlen($key))->toBe(SODIUM_CRYPTO_SECRETBOX_KEYBYTES); // 32
});

it('misma_password_y_sal_producen_misma_clave', function () {
    $service = new KeyDerivationService();
    $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
    $password = 'password_consistente';

    $key1 = $service->derive($password, $salt);
    $key2 = $service->derive($password, $salt);

    expect($key1)->toBe($key2);
});

it('distinta_sal_produce_distinta_clave', function () {
    $service = new KeyDerivationService();
    $password = 'misma_password';

    $salt1 = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
    $salt2 = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);

    $key1 = $service->derive($password, $salt1);
    $key2 = $service->derive($password, $salt2);

    expect($key1)->not->toBe($key2);
});
