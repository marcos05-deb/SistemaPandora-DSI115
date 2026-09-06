<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\Casts\EncryptedFieldCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paciente extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'pacientes';
    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'codigo',
        'carnet',
        'carrera_id',
        'creado_por_profesional_id',
        'sexo',
        'estado_civil',
        'nombre_completo',
        'direccion',
        'fecha_nacimiento',
        'profesion_ocupacion',
        'fecha_primera_consulta',
        'referido_por',
        'llevado_por',
        'motivo_consulta'
    ];

    protected $casts = [
        'nombre_completo' => EncryptedFieldCast::class,
        'direccion' => EncryptedFieldCast::class,
        'fecha_nacimiento' => EncryptedFieldCast::class,
        'profesion_ocupacion' => EncryptedFieldCast::class,
        'referido_por' => EncryptedFieldCast::class,
        'llevado_por' => EncryptedFieldCast::class,
        'motivo_consulta' => EncryptedFieldCast::class,
    ];

    public function expedientes(): HasMany
    {
        return $this->hasMany(Expediente::class, 'paciente_id', 'codigo');
    }

    public function historialMultidisciplinario(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            Consulta::class,
            Expediente::class,
            'paciente_id',
            'expediente_id',
            'codigo',
            'id'
        )->whereHas('expediente')->orderBy('fecha_consulta', 'desc');
    }

    public function contactos(): HasMany
    {
        return $this->hasMany(ContactoPaciente::class, 'paciente_id', 'codigo');
    }

    public function carrera(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'carrera_id', 'id');
    }
}
