<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Carrera extends Model
{
    protected $table = 'carreras';
    public $timestamps = false;

    protected $fillable = [
        'facultad_id',
        'nombre',
    ];

    public function facultad(): BelongsTo
    {
        return $this->belongsTo(Facultad::class, 'facultad_id', 'id');
    }
}
