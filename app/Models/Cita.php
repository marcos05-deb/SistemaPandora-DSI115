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
        'fecha_registro_asistencia',
        'registrado_por_profesional_id',
        'motivo_cancelacion',
        'cita_origen_id',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'fecha_registro_asistencia' => 'datetime',
        'motivo'     => \App\Models\Casts\EncryptedFieldCast::class,
        'motivo_cancelacion' => \App\Models\Casts\EncryptedFieldCast::class,
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

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'registrado_por_profesional_id');
    }

    public function registrarAsistencia(string $estado, string $profesionalId): void
    {
        $this->estado = $estado;
        $this->registrado_por_profesional_id = $profesionalId;
        $this->fecha_registro_asistencia = now();
        $this->save();
    }

    public function citaOrigen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cita_origen_id');
    }
}
