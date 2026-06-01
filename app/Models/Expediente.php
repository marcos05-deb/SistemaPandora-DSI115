<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Scopes\AreaScope;

class Expediente extends Model
{
    use SoftDeletes, HasUuids;

    protected $fillable = [
        'paciente_id',
        'area_id',
        'motivo_consulta',
        'notas_clinicas',
        'diagnostico',
    ];

    protected $casts = [
        'motivo_consulta' => \App\Models\Casts\EncryptedFieldCast::class,
        'notas_clinicas'  => \App\Models\Casts\EncryptedFieldCast::class,
        'diagnostico'     => \App\Models\Casts\EncryptedFieldCast::class,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new AreaScope());
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id', 'codigo');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
