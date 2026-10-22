<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AlertaPreventiva;
use App\Models\Especialista;
use App\Notifications\AlertaPreventivaCreada;
use Illuminate\Notifications\DatabaseNotification;

class AlertaPreventivaPolicy
{
    public function viewAny(Especialista $user): bool
    {
        return ! $user->hasRole('sysadmin')
            && ! $user->hasRole('psychosocial_referent')
            && ($user->hasRole('area_coordinator') || $user->hasRole('specialist'))
            && $user->areas()->exists();
    }

    public function view(Especialista $user, AlertaPreventiva $alerta): bool
    {
        if ($this->isExcluded($user) || ! $this->belongsToArea($user, $alerta)) {
            return false;
        }

        return $user->hasRole('area_coordinator')
            || $user->hasRole('specialist')
            || $this->isResponsible($user, $alerta);
    }

    public function registrarSeguimiento(Especialista $user, AlertaPreventiva $alerta): bool
    {
        return $this->view($user, $alerta);
    }

    public function cerrar(Especialista $user, AlertaPreventiva $alerta): bool
    {
        return ! $this->isExcluded($user)
            && $this->belongsToArea($user, $alerta)
            && ($user->hasRole('area_coordinator') || $this->isResponsible($user, $alerta));
    }

    public function descartar(Especialista $user, AlertaPreventiva $alerta): bool
    {
        return $this->cerrar($user, $alerta);
    }

    public function markNotificationAsRead(Especialista $user, DatabaseNotification $notification): bool
    {
        if (! $this->viewAny($user)
            || $notification->type !== AlertaPreventivaCreada::class
            || $notification->notifiable_type !== $user::class
            || (string) $notification->notifiable_id !== (string) $user->getKey()) {
            return false;
        }

        $alertId = $notification->data['alert_id'] ?? null;
        if (! is_string($alertId)) {
            return false;
        }

        $alerta = AlertaPreventiva::query()->find($alertId);

        return $alerta !== null && $this->view($user, $alerta);
    }

    private function isExcluded(Especialista $user): bool
    {
        return $user->hasRole('sysadmin') || $user->hasRole('psychosocial_referent');
    }

    private function belongsToArea(Especialista $user, AlertaPreventiva $alerta): bool
    {
        return $user->areas()->where('areas.id', $alerta->area_id)->exists();
    }

    private function isResponsible(Especialista $user, AlertaPreventiva $alerta): bool
    {
        return $alerta->responsable()->where('user_id', $user->getKey())->exists();
    }
}
