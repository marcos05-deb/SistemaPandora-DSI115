<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutorizacionPaciente extends Model
{
    use HasUuids;

    protected $table = 'autorizaciones_paciente';

    protected $fillable = [
        'paciente_id',
        'profesional_id',
        'otorgada_por_profesional_id',
        'vigente_desde',
        'vigente_hasta',
    ];

    protected $casts = [
        'vigente_desde' => 'datetime',
        'vigente_hasta' => 'datetime',
    ];

    public function scopeVigentes($query)
    {
        return $query->where('vigente_desde', '<=', now())
            ->where('vigente_hasta', '>=', now());
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id', 'codigo');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }
}
