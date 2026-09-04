<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedFieldCast;
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
    ];

    protected $casts = [
        'fecha_consulta' => 'datetime',
        'motivo_consulta' => EncryptedFieldCast::class,
        'notas_clinicas' => EncryptedFieldCast::class,
        'diagnostico' => EncryptedFieldCast::class,
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
