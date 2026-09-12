<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Especialista;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Models\Audit;

/**
 * Registra accesos de lectura a información clínica protegida (NH-04).
 */
final class ClinicalAccessAuditor
{
    public function record(Especialista $user, Model $resource, string $action = 'viewed'): Audit
    {
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
            'url' => request()->fullUrl(),
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 1023),
            'tags' => 'clinical_access',
        ]);
    }
}
