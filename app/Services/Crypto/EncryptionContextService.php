<?php

namespace App\Services\Crypto;

use App\Exceptions\DecryptionException;

/**
 * EncryptionContextService — Roadmap §HU-05, C-07.
 *
 * Abstrae el acceso a la clave simétrica de sesión (_sym_key).
 * Permite que EncryptedFieldCast sea testeable en aislamiento
 * mockeando este servicio en lugar de acceder a session() directamente.
 */
class EncryptionContextService
{
    /**
     * Retorna la clave simétrica de 32 bytes derivada de BLIND_INDEX_SECRET.
     *
     * @throws DecryptionException Si BLIND_INDEX_SECRET no está configurado.
     */
    public function getKey(): string
    {
        $secret = config('app.blind_index_secret');

        if (empty($secret)) {
            throw new DecryptionException(
                'BLIND_INDEX_SECRET no configurado. Contacte al administrador.'
            );
        }

        $raw = base64_decode($secret, strict: true);

        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new DecryptionException('BLIND_INDEX_SECRET debe ser 32 bytes en Base64.');
        }

        return $raw;
    }
}
