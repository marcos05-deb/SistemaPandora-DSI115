<?php

namespace App\Http\Middleware;

use App\Models\Rubrica;
use App\Models\AlertaPreventiva;
use App\Models\Especialista;
use App\Notifications\AlertaPreventivaCreada;
use App\Services\ClinicalAccessAuditor;
use App\Policies\IndicadorPolicy;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $clinicalPermissions = [
            'rubricas' => $user?->can('viewAny', Rubrica::class) ?? false,
            'crear' => $user?->can('create', Rubrica::class) ?? false,
            'alertas' => $user?->can('viewAny', AlertaPreventiva::class) ?? false,
            'indicadores' => $user instanceof Especialista
                ? app(IndicadorPolicy::class)->ver($user)
                : false,
        ];

        return [
            ...parent::share($request),
            'appName'     => 'PANDORA',
            'appSubtitle' => 'Sistema de Gestión Clínica',
            'auth' => [
                'user' => $user ? $this->sharedUser($user) : null,
            ],
            'clinicalPermissions' => $clinicalPermissions,
            'can' => $clinicalPermissions,
            'clinicalNotifications' => fn () => $this->clinicalNotifications($user),
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'variant' => fn () => $request->session()->get('variant', 'success'),
                'generated_password' => fn () => $request->session()->get('generated_password'),
                'prompt_cita_expediente_id' => fn () => $request->session()->get('prompt_cita_expediente_id'),
                'prompt_cita_consulta_id' => fn () => $request->session()->get('prompt_cita_consulta_id'),
            ],
        ];
    }

    /** @return array{unreadCount: int, unread: list<array{id: string, senal: string, label: string, created_at: string}>} */
    private function clinicalNotifications(?Especialista $user): array
    {
        if (! $user?->can('viewAny', AlertaPreventiva::class)) {
            return ['unreadCount' => 0, 'unread' => []];
        }

        $notifications = $user->unreadNotifications()
            ->where('type', AlertaPreventivaCreada::class)
            ->latest()
            ->get();
        $alertIds = $notifications
            ->pluck('data.alert_id')
            ->filter(fn ($id): bool => is_string($id))
            ->unique()
            ->values();
        $visibleAlertIds = AlertaPreventiva::query()
            ->whereIn('id', $alertIds)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();
        $notifications = $notifications
            ->filter(fn ($notification): bool => in_array((string) ($notification->data['alert_id'] ?? ''), $visibleAlertIds, true))
            ->values();
        $unreadCount = $notifications->count();
        $visibleNotifications = $notifications->take(5);

        if ($visibleNotifications->isNotEmpty()) {
            app(ClinicalAccessAuditor::class)->record($user, $visibleNotifications->first(), 'read_notification_inbox');
        }

        return [
            'unreadCount' => $unreadCount,
            'unread' => $visibleNotifications->map(function ($notification): array {
                $senal = in_array($notification->data['senal'] ?? null, AlertaPreventiva::SENALES, true)
                    ? $notification->data['senal']
                    : '';

                return [
                    'id' => (string) $notification->id,
                    'senal' => $senal,
                    'label' => AlertaPreventiva::labelForSignal($senal),
                    'created_at' => $notification->created_at->toIso8601String(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Datos de sesión para layout clínico: slugs (autorización) + etiquetas legibles (UI).
     *
     * @return array{id: mixed, email: string, name: string, roles: list<string>, role_label: string, area: string|null, areas: list<string>}
     */
    private function sharedUser(\App\Models\Especialista $user): array
    {
        $roles = $user->roles()->orderByDesc('roles.nivel')->get(['roles.slug', 'roles.nombre', 'roles.nivel']);
        $slugs = $roles->pluck('slug')->values()->all();
        $primaryRole = $roles->first();

        $areaNames = $user->areas()
            ->orderBy('areas.nombre')
            ->pluck('areas.nombre')
            ->unique()
            ->values()
            ->all();

        $areaLabel = match (true) {
            count($areaNames) === 0 => null,
            count($areaNames) === 1 => $areaNames[0],
            default => implode(', ', $areaNames),
        };

        $roleNombre = $primaryRole?->nombre ?? 'Usuario';
        $roleLabel = $areaLabel && $primaryRole && $primaryRole->slug !== 'sysadmin'
            ? "{$roleNombre} — {$areaLabel}"
            : $roleNombre;

        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'roles' => $slugs,
            'role_label' => $roleLabel,
            'area' => $areaLabel,
            'areas' => $areaNames,
        ];
    }
}
