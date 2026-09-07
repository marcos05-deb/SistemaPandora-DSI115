<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Cita extends Model implements Auditable
{
    use HasFactory, SoftDeletes, HasUuids, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'expediente_id',
        'profesional_id',
        'area_id',
        'fecha_hora',
        'motivo',
        'estado',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'motivo'     => \App\Models\Casts\EncryptedFieldCast::class,
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
