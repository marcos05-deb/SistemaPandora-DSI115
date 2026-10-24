<?php

declare(strict_types=1);

namespace App\Services;

use App\Auditing\Resolvers\SanitizedUrlResolver;
use App\Models\Audit;
use App\Models\Especialista;
use Illuminate\Http\Request;

/**
 * Eventos de autenticación sin secretos (HU-21 Q3).
 */
final class AuthAccessAuditor
{
    public function loginSucceeded(Especialista $user, Request $request): Audit
    {
        return $this->write($user, 'auth_login_success', [
            'action' => 'login_success',
            'email_hash' => hash('sha256', strtolower($user->email)),
        ], $request);
    }

    public function loginFailed(?Especialista $user, string $email, Request $request): Audit
    {
        return $this->write($user, 'auth_login_failed', [
            'action' => 'login_failed',
            'email_hash' => hash('sha256', strtolower($email)),
        ], $request);
    }

    public function logout(Especialista $user, Request $request): Audit
    {
        return $this->write($user, 'auth_logout', [
            'action' => 'logout',
        ], $request);
    }

    public function passwordChanged(Especialista $user, Request $request): Audit
    {
        return $this->write($user, 'auth_password_changed', [
            'action' => 'password_changed',
        ], $request);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function write(?Especialista $user, string $event, array $payload, Request $request): Audit
    {
        return Audit::query()->create([
            'user_type' => $user ? $user::class : null,
            'user_id' => $user?->getKey(),
            'event' => $event,
            'auditable_type' => Especialista::class,
            'auditable_id' => (string) ($user?->getKey() ?? '0'),
            'old_values' => [],
            'new_values' => $payload,
            'url' => SanitizedUrlResolver::sanitize($request->fullUrl()),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1023),
            'tags' => 'auth_access',
        ]);
    }
}
