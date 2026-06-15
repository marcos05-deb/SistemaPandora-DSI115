<?php

namespace App\Services\Crypto;

use App\Exceptions\DecryptionException;

/**
 * EncryptionContextService — Roadmap §HU-05, C-07.
 *
 * Provee la clave simétrica de 32 bytes para cifrar/descifrar datos de pacientes.
 *
 * La clave criptográfica real es BLIND_INDEX_SECRET (config/app),
 * común a todo el sistema para que múltiples especialistas puedan leer
 * los mismos pacientes.
 *
 * _sym_key en sesión actúa como gate de autenticación:
 *   - Web: debe existir en sesión (el usuario hizo login).
 *   - CLI: se omite el gate (seeders, comandos artisan).
 *
 * RBAC y Area Scope son responsables de controlar QUIÉN ve QUÉ paciente;
 * EncryptionContextService solo protege los datos en reposo.
 */
class EncryptionContextService
{
    /**
     * Retorna la clave simétrica de 32 bytes (BLIND_INDEX_SECRET).
     *
     * @throws DecryptionException Si en modo web no hay _sym_key en sesión
     *                             o si BLIND_INDEX_SECRET no está configurado.
     */
    public function getKey(): string
    {
        if (!app()->runningInConsole() || app()->runningUnitTests()) {
            if (empty(session('_sym_key'))) {
                throw new DecryptionException(
                    'Clave de sesión no disponible. La sesión puede haber expirado.'
                );
            }
        }

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
