<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\AreaEncryptedFieldCast;
use App\Models\Scopes\AreaScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class SeguimientoPreventivo extends Model implements AuditableContract
{
    use Auditable;
    use HasUuids;

    protected $table = 'seguimientos_preventivos';

    protected $fillable = [
        'alerta_preventiva_id',
        'area_id',
        'valoracion',
        'accion_preventiva',
        'responsable_profesional_id',
        'proxima_revision',
        'registrado_por_profesional_id',
    ];

    protected function casts(): array
    {
        return [
            'valoracion' => AreaEncryptedFieldCast::class,
            'accion_preventiva' => AreaEncryptedFieldCast::class,
            'proxima_revision' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new AreaScope);
        static::updating(static function (): never {
            throw new LogicException('Preventive follow-ups are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Preventive follow-ups are append-only.');
        });
    }

    /** @param array<string, mixed> $data */
    public function transformAudit(array $data): array
    {
        foreach (['old_values', 'new_values'] as $bucket) {
            foreach (['valoracion', 'accion_preventiva'] as $field) {
                if (isset($data[$bucket][$field])) {
                    $data[$bucket][$field] = '[CIFRADO]';
                }
            }
        }

        return $data;
    }

    public function alerta(): BelongsTo
    {
        return $this->belongsTo(AlertaPreventiva::class, 'alerta_preventiva_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'responsable_profesional_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'registrado_por_profesional_id');
    }
}
