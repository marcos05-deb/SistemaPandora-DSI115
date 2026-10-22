<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class RubricaVariable extends Model implements AuditableContract
{
    use AppendOnly;
    use Auditable;
    use HasUuids;

    public const UPDATED_AT = null;

    public const TIPOS_SENAL = [
        'crisis_psicologica',
        'ausencia_cita',
        'tendencia_suicida',
    ];

    public const MODOS_CALCULO = [
        'frecuencia',
        'acumulacion',
        'puntaje',
    ];

    protected $table = 'rubrica_variables';

    protected $fillable = [
        'tipo_senal',
        'clave_indicador',
        'modo_calculo',
        'ventana_dias',
        'ponderacion',
        'umbral',
    ];

    protected function casts(): array
    {
        return [
            'ventana_dias' => 'integer',
            'ponderacion' => 'decimal:2',
            'umbral' => 'decimal:2',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(RubricaVersion::class, 'rubrica_version_id');
    }
}
