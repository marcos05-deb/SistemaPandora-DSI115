<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Scopes\AreaScope;
use OwenIt\Auditing\Contracts\Auditable;

class Expediente extends Model implements Auditable
{
    use HasFactory, SoftDeletes, HasUuids, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'paciente_id',
        'area_id',
        'motivo_consulta',
        'notas_clinicas',
        'diagnostico',
        'estado',
        'derivado_por_profesional_id',
        'fecha_derivacion',
        'motivo_cierre',
        'fecha_cierre',
        'cerrado_por_profesional_id',
    ];

    protected $casts = [
        'motivo_consulta' => \App\Models\Casts\EncryptedFieldCast::class,
        'notas_clinicas'  => \App\Models\Casts\EncryptedFieldCast::class,
        'diagnostico'     => \App\Models\Casts\EncryptedFieldCast::class,
        'fecha_derivacion'=> 'datetime',
        'motivo_cierre'   => \App\Models\Casts\EncryptedFieldCast::class,
        'fecha_cierre'    => 'datetime',
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

    public function derivadoPor(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'derivado_por_profesional_id');
    }

    public function consultas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Consulta::class);
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'cerrado_por_profesional_id');
    }

    public function citas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Cita::class);
    }
}
