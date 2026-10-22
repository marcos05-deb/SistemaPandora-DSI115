<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\AreaEncryptedFieldCast;
use App\Models\Concerns\AppendOnly;
use App\Models\Scopes\AreaScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class EvaluacionRiesgo extends Model implements AuditableContract
{
    use AppendOnly;
    use Auditable;
    use HasUuids;

    protected $table = 'evaluaciones_riesgo';

    protected $fillable = [
        'paciente_id',
        'area_id',
        'rubrica_version_id',
        'disparador',
        'puntaje',
        'detalle',
        'estado_evaluacion',
        'evento_origen_tipo',
        'evento_origen_id',
        'evaluada_en',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'detalle' => AreaEncryptedFieldCast::class,
            'puntaje' => 'decimal:2',
            'evaluada_en' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new AreaScope());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function transformAudit(array $data): array
    {
        foreach (['old_values', 'new_values'] as $bucket) {
            foreach (['paciente_id', 'rubrica_version_id', 'disparador', 'puntaje', 'detalle', 'evento_origen_tipo', 'evento_origen_id'] as $field) {
                if (isset($data[$bucket][$field])) {
                    $data[$bucket][$field] = '[CIFRADO]';
                }
            }
        }

        return $data;
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id', 'codigo');
    }

    public function versionRubrica(): BelongsTo
    {
        return $this->belongsTo(RubricaVersion::class, 'rubrica_version_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

}
