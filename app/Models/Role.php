<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Role — Roadmap §HU-03.
 *
 * Roles del sistema con slug y nivel jerárquico:
 *   sysadmin (100), area_coordinator (50), specialist (10)
 */
class Role extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'slug',
        'nivel',
    ];

    protected function casts(): array
    {
        return [
            'nivel' => 'integer',
        ];
    }
}
