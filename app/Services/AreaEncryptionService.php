<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final readonly class AreaEncryptionService
{
    private string $areaKeySecret;

    public function __construct()
    {
        $secret = config('app.area_key_secret');
        
        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('La variable AREA_KEY_SECRET no está configurada o es inválida.');
        }
        
        $this->areaKeySecret = $secret;
    }

    public function encrypt(string $plaintext, string|int $areaId): string
    {
        $key = $this->deriveKey((string) $areaId);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            '',
            $nonce,
            $key
        );

        return base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $encryptedPayload, string|int $areaId): string
    {
        $key = $this->deriveKey((string) $areaId);
        $decoded = base64_decode($encryptedPayload, true);

        if ($decoded === false) {
            throw new RuntimeException('El payload encriptado no tiene un formato base64 válido.');
        }

        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        
        if (strlen($decoded) < $nonceLength) {
            throw new RuntimeException('El payload es demasiado corto para contener un nonce.');
        }

        $nonce = substr($decoded, 0, $nonceLength);
        $ciphertext = substr($decoded, $nonceLength);

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext,
            '',
            $nonce,
            $key
        );

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed: llave de área incorrecta o datos corruptos.');
        }

        return $plaintext;
    }

    private function deriveKey(string $areaId): string
    {
        return sodium_crypto_generichash(
            $areaId, 
            $this->areaKeySecret, 
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES
        );
    }
}
