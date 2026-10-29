<?php

declare(strict_types=1);

namespace App\Services;

use App\Auditing\Resolvers\SanitizedUrlResolver;
use App\DTO\FiltrosBitacora;
use App\Enums\AccionBitacora;
use App\Models\Audit;
use App\Models\Especialista;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BitacoraQueryService
{
    public const PER_PAGE = 25;

    public function buscar(FiltrosBitacora $filtros): LengthAwarePaginator
    {
        return $this->consulta($filtros)->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * Misma consulta de listado (HU-22), reutilizada por exportación (HU-23).
     *
     * @return Builder<Audit>
     */
    public function consulta(FiltrosBitacora $filtros, ?CarbonImmutable $corteEn = null): Builder
    {
        $tz = (string) config('app.timezone');
        $desde = CarbonImmutable::parse($filtros->desde, $tz)->startOfDay()->utc();
        $hasta = CarbonImmutable::parse($filtros->hasta, $tz)->endOfDay()->utc();
        if ($corteEn !== null && $corteEn->lt($hasta)) {
            $hasta = $corteEn->utc();
        }

        return Audit::query()
            ->select([
                'audits.id',
                'audits.user_id',
                'audits.event',
                'audits.auditable_type',
                'audits.auditable_id',
                'audits.old_values',
                'audits.new_values',
                'audits.url',
                'audits.ip_address',
                'audits.created_at',
            ])
            ->where('audits.created_at', '>=', $desde)
            ->where('audits.created_at', '<=', $hasta)
            ->when($filtros->usuarioId !== null, static fn ($q) => $q->where('audits.user_id', $filtros->usuarioId))
            ->when($filtros->accion !== null, static fn ($q) => $q->where('audits.event', $filtros->accion))
            ->when($filtros->registroId !== null, static fn ($q) => $q->where('audits.auditable_id', $filtros->registroId))
            ->when($filtros->tipo !== null, static fn ($q) => $q->where('audits.auditable_type', $filtros->tipo))
            ->orderByDesc('audits.created_at')
            ->orderByDesc('audits.id');
    }

    public function contar(FiltrosBitacora $filtros, ?CarbonImmutable $corteEn = null): int
    {
        return (int) $this->consulta($filtros, $corteEn)->toBase()->getCountForPagination();
    }

    /**
     * @param  LengthAwarePaginator<int, Audit>|iterable<int, Audit>  $eventos
     * @return list<array<string, mixed>>
     */
    public function mapear(LengthAwarePaginator|iterable $eventos): array
    {
        $items = $eventos instanceof LengthAwarePaginator
            ? $eventos->items()
            : (is_array($eventos) ? $eventos : iterator_to_array($eventos));

        $userIds = collect($items)
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, Especialista> $users */
        $users = Especialista::query()
            ->with(['roles' => static fn ($q) => $q->orderByDesc('roles.nivel')])
            ->whereIn('id', $userIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        return collect($items)->map(function (Audit $audit) use ($users): array {
            $user = $audit->user_id !== null ? $users->get((int) $audit->user_id) : null;
            $rol = $user?->roles->first()?->nombre;

            return [
                'id' => (int) $audit->id,
                'fecha' => $audit->created_at?->timezone(config('app.timezone'))->toIso8601String(),
                'usuario' => $user === null ? null : [
                    'id' => (int) $user->id,
                    'nombre' => (string) $user->name,
                    'rol' => $rol,
                ],
                'accion' => (string) $audit->event,
                'accion_etiqueta' => AccionBitacora::tryFrom((string) $audit->event)?->etiqueta() ?? (string) $audit->event,
                'objeto_tipo' => class_basename((string) $audit->auditable_type),
                'objeto_id' => (string) $audit->auditable_id,
                'ip' => $audit->ip_address,
                'ruta' => SanitizedUrlResolver::sanitize($audit->url),
                'campos' => $this->nombresCampos($audit->old_values, $audit->new_values),
            ];
        })->all();
    }

    /** @param array<string, mixed> $filtros */
    public function registrarConsulta(Especialista $user, array $filtros): Audit
    {
        return Audit::query()->create([
            'user_type' => $user::class,
            'user_id' => $user->getKey(),
            'event' => AccionBitacora::BitacoraConsultada->value,
            'auditable_type' => 'App\\Audit\\Bitacora',
            'auditable_id' => 'bitacora',
            'old_values' => [],
            'new_values' => [
                'action' => 'bitacora_consultada',
                'filters' => $filtros,
            ],
            'url' => SanitizedUrlResolver::sanitize(request()->fullUrl()),
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 1023),
            'tags' => 'bitacora_access',
        ]);
    }

    /**
     * @param  array<string, mixed>|string|null  $old
     * @param  array<string, mixed>|string|null  $new
     * @return list<string>
     */
    private function nombresCampos(array|string|null $old, array|string|null $new): array
    {
        $oldKeys = array_keys($this->asArray($old));
        $newKeys = array_keys($this->asArray($new));

        return array_values(array_unique(array_merge($oldKeys, $newKeys)));
    }

    /** @return array<string, mixed> */
    private function asArray(array|string|null $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return list<array{id: int, name: string}> */
    public function usuariosDisponibles(): array
    {
        return Especialista::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Especialista $u): array => [
                'id' => (int) $u->id,
                'name' => (string) $u->name,
            ])
            ->all();
    }

    /** @return list<array{id: string, etiqueta: string}> */
    public function catalogoAcciones(): array
    {
        return array_map(
            static fn (AccionBitacora $accion): array => [
                'id' => $accion->value,
                'etiqueta' => $accion->etiqueta(),
            ],
            AccionBitacora::cases()
        );
    }

    public function explainUsaIndice(FiltrosBitacora $filtros): bool
    {
        $tz = (string) config('app.timezone');
        $desde = CarbonImmutable::parse($filtros->desde, $tz)->startOfDay()->utc()->toDateTimeString();
        $hasta = CarbonImmutable::parse($filtros->hasta, $tz)->endOfDay()->utc()->toDateTimeString();

        $sql = 'EXPLAIN (FORMAT TEXT) SELECT audits.id FROM audits WHERE audits.created_at >= ? AND audits.created_at <= ?';
        $bindings = [$desde, $hasta];
        if ($filtros->usuarioId !== null) {
            $sql .= ' AND audits.user_id = ?';
            $bindings[] = $filtros->usuarioId;
        }
        if ($filtros->accion !== null) {
            $sql .= ' AND audits.event = ?';
            $bindings[] = $filtros->accion;
        }
        $sql .= ' ORDER BY audits.created_at DESC, audits.id DESC LIMIT 25';

        $plan = collect(DB::select($sql, $bindings))->pluck('QUERY PLAN')->implode("\n");

        return ! str_contains($plan, 'Seq Scan on audits');
    }
}
