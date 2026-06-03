<?php

namespace App\Support;

class MockupNavigation
{
    /**
     * @return array<int, array{label: string, href: string, active: bool}>
     */
    public static function items(array $items, string $activeHref): array
    {
        return array_map(function (array $item) use ($activeHref) {
            return [
                'label' => $item['label'],
                'href' => $item['href'],
                'active' => $item['href'] === $activeHref,
            ];
        }, $items);
    }

    public static function directorSecurity(): array
    {
        return [
            ['label' => 'Inicio', 'href' => '/cierre-sesion'],
            ['label' => 'Seguridad y Acceso', 'href' => '/usuarios'],
            ['label' => 'Identidad y Privacidad', 'href' => '/codigos-privacidad'],
            ['label' => 'Expedientes', 'href' => '#'],
            ['label' => 'Reportes', 'href' => '#'],
            ['label' => 'Auditoría', 'href' => '#'],
        ];
    }

    public static function directorPrivacy(): array
    {
        return [
            ['label' => 'Inicio', 'href' => '/cierre-sesion'],
            ['label' => 'Identidad y Privacidad', 'href' => '/codigos-privacidad'],
            ['label' => 'Expedientes', 'href' => '#'],
            ['label' => 'Citas', 'href' => '#'],
            ['label' => 'Alertas', 'href' => '#'],
            ['label' => 'Reportes', 'href' => '#'],
            ['label' => 'Auditoría', 'href' => '#'],
        ];
    }

    public static function reception(): array
    {
        return [
            ['label' => 'Inicio', 'href' => '/registro-paciente'],
            ['label' => 'Registro Pacientes', 'href' => '/registro-paciente'],
            ['label' => 'Citas', 'href' => '#'],
            ['label' => 'Expedientes', 'href' => '#'],
        ];
    }

    public static function specialist(): array
    {
        return [
            ['label' => 'Inicio', 'href' => '/busqueda-segura'],
            ['label' => 'Identidad y Privacidad', 'href' => '/codigos-privacidad'],
            ['label' => 'Busqueda Segura', 'href' => '/busqueda-segura'],
            ['label' => 'Expedientes', 'href' => '#'],
            ['label' => 'Mi Agenda', 'href' => '#'],
        ];
    }
}
