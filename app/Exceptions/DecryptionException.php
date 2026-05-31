<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lanzada cuando un campo cifrado no puede descifrarse.
 * Causas comunes: sesión expirada, clave incorrecta, datos corruptos.
 *
 * Roadmap §HU-05, C-05.
 */
final class DecryptionException extends RuntimeException {}
