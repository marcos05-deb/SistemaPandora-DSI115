<?php

namespace App\Models\Casts;

use App\Exceptions\DecryptionException;
use App\Services\Crypto\EncryptionContextService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class EncryptedFieldCast implements CastsAttributes
{
    public function get($model, string $key, $value, $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }
        $encKey = app(EncryptionContextService::class)->getKey();
        return $this->decryptField($value, $encKey);
    }

    public function set($model, string $key, $value, $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }
        $encKey = app(EncryptionContextService::class)->getKey();
        return $this->encryptField($value, $encKey);
    }

    private function encryptField(string $plaintext, string $encKey): string
    {
        $nonce      = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $encKey);
        return base64_encode($nonce . $ciphertext);
    }

    private function decryptField(string $encoded, string $encKey): string
    {
        $decoded = base64_decode($encoded, strict: true);
        if ($decoded === false) {
            throw new DecryptionException("El valor cifrado del campo está malformado (base64 inválido).");
        }
        $nonce      = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        
        $plaintext  = sodium_crypto_secretbox_open($ciphertext, $nonce, $encKey);
        if ($plaintext === false) {
            throw new DecryptionException("No se pudo descifrar el campo. Posible corrupción o clave incorrecta.");
        }
        return $plaintext;
    }
}
