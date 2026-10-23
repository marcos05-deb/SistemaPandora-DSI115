<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class RubricaVersion extends Model implements AuditableContract
{
    use AppendOnly;
    use Auditable;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'rubrica_versiones';

    protected $fillable = [
        'rubrica_id',
        'numero_version',
        'vigente_desde',
        'umbral_puntaje_global',
        'justificacion_cambio',
        'validada_en',
        'referencia_validacion',
        'es_demo',
        'creada_por_profesional_id',
    ];

    protected function casts(): array
    {
        return [
            'vigente_desde' => 'datetime',
            'umbral_puntaje_global' => 'decimal:2',
            'validada_en' => 'date',
            'es_demo' => 'boolean',
        ];
    }

    public function rubrica(): BelongsTo
    {
        return $this->belongsTo(Rubrica::class, 'rubrica_id');
    }

    public function variables(): HasMany
    {
        return $this->hasMany(RubricaVariable::class, 'rubrica_version_id');
    }

    public function creadaPor(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'creada_por_profesional_id')->withTrashed();
    }
}
