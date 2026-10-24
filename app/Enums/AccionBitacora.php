<?php

declare(strict_types=1);

namespace App\Enums;

enum AccionBitacora: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case Accessed = 'accessed';
    case AuthLoginSuccess = 'auth_login_success';
    case AuthLoginFailed = 'auth_login_failed';
    case AuthLogout = 'auth_logout';
    case AuthPasswordChanged = 'auth_password_changed';
    case BitacoraConsultada = 'bitacora_consultada';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Created => 'Creación',
            self::Updated => 'Actualización',
            self::Deleted => 'Eliminación',
            self::Restored => 'Restauración',
            self::Accessed => 'Acceso / lectura',
            self::AuthLoginSuccess => 'Inicio de sesión',
            self::AuthLoginFailed => 'Intento de sesión fallido',
            self::AuthLogout => 'Cierre de sesión',
            self::AuthPasswordChanged => 'Cambio de contraseña',
            self::BitacoraConsultada => 'Consulta de bitácora',
        };
    }
}
