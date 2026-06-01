<?php

namespace App\Services\Crypto;

/**
 * Servicio de Derivación de Clave (KDF) — Roadmap §HU-01b.
 *
 * Deriva una clave simétrica de 32 bytes a partir de la contraseña del Especialista
 * usando sodium_crypto_pwhash con Argon2id. La clave resultante se usa para
 * cifrar/descifrar datos sensibles de pacientes (HU-05).
 *
 * La sal (kdf_salt) es única por Especialista, generada en el registro.
 * La contraseña NUNCA se persiste ni se loguea.
 */
class KeyDerivationService
{
    /**
     * Deriva una clave simétrica de 32 bytes a partir de la contraseña y la sal.
     *
     * @param string $password Contraseña en texto plano (se limpia después de usar).
     * @param string $salt     Sal de 16 bytes (SODIUM_CRYPTO_PWHASH_SALTBYTES), almacenada en Base64 en la DB.
     * @return string Clave derivada de 32 bytes (SODIUM_CRYPTO_SECRETBOX_KEYBYTES).
     */
    public function derive(string $password, string $salt): string
    {
        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES, // 32 bytes
            $password,
            $salt,                            // 16 bytes — decodificada de Base64
            config('hashing.kdf.opslimit', SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE),
            config('hashing.kdf.memlimit', SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE),
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );
    }

    /**
     * Genera una sal aleatoria para un nuevo Especialista.
     *
     * @return string Sal de 16 bytes codificada en Base64 para almacenamiento.
     */
    public function generateSalt(): string
    {
        return base64_encode(random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES));
    }
}
