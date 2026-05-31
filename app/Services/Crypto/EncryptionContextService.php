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
     * Retorna la clave simétrica de 32 bytes desde sesión.
     *
     * @throws DecryptionException Si la clave no está disponible (sesión expirada o no autenticado).
     */
    public function getKey(): string
    {
        if (!session()->has('_sym_key')) {
            throw new DecryptionException(
                'Clave de cifrado no disponible. La sesión expiró o el usuario no está autenticado.'
            );
        }

        $decoded = base64_decode(session('_sym_key'), strict: true);

        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new DecryptionException('La clave de sesión está malformada o tiene longitud incorrecta.');
        }

        return $decoded;
    }
}
