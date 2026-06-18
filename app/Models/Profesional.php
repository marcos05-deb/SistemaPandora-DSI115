<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Profesional — Perfil extendido del Especialista.
 *
 * Vincula al usuario autenticado (users) con su área de especialidad.
 * Usa UUID como primary key (definido en la DB existente).
 */
class Profesional extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'profesionales';

    protected $fillable = [
        'user_id',
        'area_id',
        'especialidad',
        'numero_registro',
    ];

    public function especialista(): BelongsTo
    {
        return $this->belongsTo(Especialista::class, 'user_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
