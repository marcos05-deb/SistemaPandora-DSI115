<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Area — Roadmap §1.
 *
 * Especialidad clínica (Psicología, Medicina General, Fisioterapia, Nutrición).
 * Define el scope de acceso para RBAC en HU-03.
 */
class Area extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'requiere_aprobacion_estricta',
    ];

    protected function casts(): array
    {
        return [
            'requiere_aprobacion_estricta' => 'boolean',
        ];
    }

    public function profesionales(): HasMany
    {
        return $this->hasMany(Profesional::class);
    }
}
