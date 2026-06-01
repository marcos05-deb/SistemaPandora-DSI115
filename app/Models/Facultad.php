<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facultad extends Model
{
    protected $table = 'facultades';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function carreras(): HasMany
    {
        return $this->hasMany(Carrera::class, 'facultad_id', 'id');
    }
}
