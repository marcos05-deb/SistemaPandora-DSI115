<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\AreaEncryptedFieldCast;
use App\Casts\EncryptedArrayCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Consulta extends Model implements AuditableContract
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;
    use Auditable;

    protected $table = 'consultas';

    protected $fillable = [
        'expediente_id',
        'profesional_id',
        'motivo_consulta',
        'notas_clinicas',
        'diagnostico',
        'fecha_consulta',
        'tecnica_utilizada',
        'evaluacion_inicial',
    ];

    protected $casts = [
        'fecha_consulta' => 'datetime',
        'motivo_consulta' => AreaEncryptedFieldCast::class,
        'notas_clinicas' => AreaEncryptedFieldCast::class,
        'diagnostico' => AreaEncryptedFieldCast::class,
        'tecnica_utilizada' => AreaEncryptedFieldCast::class,
        'evaluacion_inicial' => EncryptedArrayCast::class,
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class);
    }
}
