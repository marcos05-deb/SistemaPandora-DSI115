<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PacienteCorreccionAuditoria extends Model
{
    use HasUuids;

    public const TIPO_ACTUALIZACION = 'actualizacion_datos_paciente';

    public const TIPO_CORRECCION_CARNET = 'correccion_carnet_paciente';

    public const NIVEL_NORMAL = 'normal';

    public const NIVEL_SENSIBLE = 'sensible';

    protected $table = 'paciente_correcciones_auditoria';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'paciente_id',
        'carnet_anterior',
        'carnet_nuevo',
        'usuario_id',
        'profesional_id',
        'rol_usuario',
        'motivo',
        'campos_modificados',
        'valores_anteriores',
        'valores_nuevos',
        'ip_address',
        'tipo_evento',
        'nivel_evento',
        'aviso_sensible',
    ];

    protected $casts = [
        'campos_modificados' => 'array',
        'valores_anteriores' => 'array',
        'valores_nuevos' => 'array',
        'aviso_sensible' => 'boolean',
        'aviso_revisado' => 'boolean',
        'revisado_en' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            $allowed = ['aviso_revisado', 'revisado_en', 'revisado_por_usuario_id', 'updated_at'];
            $dirty = array_keys($model->getDirty());
            if (array_diff($dirty, $allowed) !== []) {
                throw new \RuntimeException('Los registros de auditoría de corrección son inmutables.');
            }
        });

        static::deleting(function (): void {
            throw new \RuntimeException('Los registros de auditoría de corrección no pueden eliminarse.');
        });
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id', 'codigo');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Especialista::class, 'usuario_id');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(Especialista::class, 'revisado_por_usuario_id');
    }

    public function marcarRevisado(Especialista $admin): void
    {
        if (! $this->aviso_sensible) {
            throw new \InvalidArgumentException('Solo los avisos sensibles pueden marcarse como revisados.');
        }

        $this->aviso_revisado = true;
        $this->revisado_en = now();
        $this->revisado_por_usuario_id = $admin->id;
        $this->save();
    }
}
