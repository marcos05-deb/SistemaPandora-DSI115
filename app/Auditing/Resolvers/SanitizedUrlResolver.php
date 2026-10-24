<?php

declare(strict_types=1);

namespace App\Auditing\Resolvers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Resolvers\UrlResolver;

final class SanitizedUrlResolver extends UrlResolver
{
    public static function resolve(Auditable $auditable): string
    {
        if (App::runningInConsole()) {
            return 'console';
        }

        $url = Request::fullUrl();

        return self::sanitize($url);
    }

    public static function sanitize(?string $url): string
    {
        if ($url === null || $url === '') {
            return '';
        }

        $pattern = (string) config('auditoria.carnet_url_pattern', '/\/pacientes\/[A-Za-z]{2}\d{5}(?=\/|$|\?)/');
        $replacement = (string) config('auditoria.carnet_url_replacement', '/pacientes/{carnet}');

        $sanitized = preg_replace($pattern, $replacement, $url);

        return is_string($sanitized) ? $sanitized : $url;
    }
}
