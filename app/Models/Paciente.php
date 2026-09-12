<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedFieldCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'motivo_consulta',
        'etiquetas_motivo',
        'ultima_accion'
    ];

    protected $casts = [
        'nombre_completo' => EncryptedFieldCast::class,
        'direccion' => EncryptedFieldCast::class,
        'fecha_nacimiento' => EncryptedFieldCast::class,
        'profesion_ocupacion' => EncryptedFieldCast::class,
        'referido_por' => EncryptedFieldCast::class,
        'llevado_por' => EncryptedFieldCast::class,
        'motivo_consulta' => EncryptedFieldCast::class,
        'etiquetas_motivo' => \App\Casts\EncryptedJsonFieldCast::class,
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

    /**
     * Indica si el paciente está bajo responsabilidad del profesional autenticado
     * o si existe una autorización vigente para actuar sobre él.
     *
     * Hoy la responsabilidad se define por creación (`creado_por_profesional_id`).
     * La autorización vigente queda como punto de extensión cuando exista el dominio formal.
     */
    public function puedeSerDerivadoPor(Especialista $especialista): bool
    {
        if (! $especialista->profesional) {
            return false;
        }

        if ($this->creado_por_profesional_id === $especialista->profesional->id) {
            return true;
        }

        return $this->tieneAutorizacionVigentePara($especialista);
    }

    /**
     * Autorizaciones temporales entre referentes (HU-07).
     */
    public function tieneAutorizacionVigentePara(Especialista $especialista): bool
    {
        if (! $especialista->profesional) {
            return false;
        }

        return AutorizacionPaciente::query()
            ->where('paciente_id', $this->codigo)
            ->where('profesional_id', $especialista->profesional->id)
            ->vigentes()
            ->exists();
    }
}
