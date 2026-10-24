<?php

declare(strict_types=1);

namespace App\Services;

use App\Auditing\Resolvers\SanitizedUrlResolver;
use App\Models\Audit;
use App\Models\Especialista;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra accesos de lectura a información clínica protegida (NH-04 / HU-21).
 */
final class ClinicalAccessAuditor
{
    public function record(Especialista $user, Model $resource, string $action = 'viewed'): Audit
    {
        $url = $action === 'read_notification_inbox'
            ? (string) (request()->route()?->getName() ?? '')
            : SanitizedUrlResolver::sanitize(request()->fullUrl());

        return Audit::query()->create([
            'user_type' => $user::class,
            'user_id' => $user->getKey(),
            'event' => 'accessed',
            'auditable_type' => $resource::class,
            'auditable_id' => (string) $resource->getKey(),
            'old_values' => [],
            'new_values' => [
                'action' => $action,
                'resource' => class_basename($resource),
            ],
            'url' => $url,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 1023),
            'tags' => 'clinical_access',
        ]);
    }

    /** @param array<string, mixed> $filters */
    public function recordAggregate(Especialista $user, string $action, array $filters): Audit
    {
        return Audit::query()->create([
            'user_type' => $user::class,
            'user_id' => $user->getKey(),
            'event' => 'accessed',
            'auditable_type' => 'App\\Analytics\\Indicadores',
            'auditable_id' => 'aggregate',
            'old_values' => [],
            'new_values' => [
                'action' => $action,
                'resource' => 'Indicadores',
                'filters' => $filters,
            ],
            'url' => SanitizedUrlResolver::sanitize(
                (string) (request()->route()?->getName() ?? request()->fullUrl())
            ),
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 1023),
            'tags' => 'aggregate_access',
        ]);
    }

    /** @param array<string, mixed> $filters */
    public function recordListing(Especialista $user, string $resource, array $filters): Audit
    {
        return Audit::query()->create([
            'user_type' => $user::class,
            'user_id' => $user->getKey(),
            'event' => 'accessed',
            'auditable_type' => $resource,
            'auditable_id' => 'listing',
            'old_values' => [],
            'new_values' => [
                'action' => 'list_consulted',
                'resource' => class_basename($resource),
                'filters' => $filters,
            ],
            'url' => SanitizedUrlResolver::sanitize(request()->fullUrl()),
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 1023),
            'tags' => 'listing_access',
        ]);
    }
}
