<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudCorreccionExpediente extends Model
{
    use HasUuids;

    public const TIPO_DATOS = 'datos_generales';

    public const TIPO_CLINICO = 'clinico';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_APROBADA = 'aprobada';

    public const ESTADO_RECHAZADA = 'rechazada';

    public const ESTADO_CONSUMIDA = 'consumida';

    /** @var list<string> */
    public const CAMPOS_DATOS = [
        'carnet',
        'nombre_completo',
        'direccion',
        'fecha_nacimiento',
        'sexo',
        'estado_civil',
        'carrera_id',
        'profesion_ocupacion',
        'referido_por',
        'llevado_por',
        'padre_nombre',
        'padre_telefono',
        'madre_nombre',
        'madre_telefono',
        'responsable_parentesco',
        'responsable_nombre',
        'responsable_telefono',
        'responsable_direccion',
    ];

    /** @var list<string> */
    public const CAMPOS_CLINICO = [
        'motivo_consulta',
        'notas_clinicas',
        'diagnostico',
    ];

    protected $table = 'solicitudes_correccion_expediente';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'paciente_id',
        'expediente_id',
        'tipo',
        'solicitante_usuario_id',
        'campos_solicitados',
        'motivo',
        'estado',
        'revisado_por_usuario_id',
        'revisado_en',
        'nota_admin',
    ];

    protected $casts = [
        'campos_solicitados' => 'array',
        'revisado_en' => 'datetime',
    ];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id', 'codigo');
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class, 'expediente_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Especialista::class, 'solicitante_usuario_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(Especialista::class, 'revisado_por_usuario_id');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', self::ESTADO_PENDIENTE);
    }

    public function scopeAprobadasVigentes($query)
    {
        return $query->where('estado', self::ESTADO_APROBADA);
    }

    public function esAprobadaVigente(): bool
    {
        return $this->estado === self::ESTADO_APROBADA;
    }

    public function aprobar(Especialista $admin, ?string $nota = null): void
    {
        if ($this->estado !== self::ESTADO_PENDIENTE) {
            throw new \InvalidArgumentException('Solo se pueden aprobar solicitudes pendientes.');
        }

        $this->estado = self::ESTADO_APROBADA;
        $this->revisado_por_usuario_id = $admin->id;
        $this->revisado_en = now();
        $this->nota_admin = $nota;
        $this->save();
    }

    public function rechazar(Especialista $admin, ?string $nota = null): void
    {
        if ($this->estado !== self::ESTADO_PENDIENTE) {
            throw new \InvalidArgumentException('Solo se pueden rechazar solicitudes pendientes.');
        }

        $this->estado = self::ESTADO_RECHAZADA;
        $this->revisado_por_usuario_id = $admin->id;
        $this->revisado_en = now();
        $this->nota_admin = $nota;
        $this->save();
    }

    public function consumir(): void
    {
        if ($this->estado !== self::ESTADO_APROBADA) {
            throw new \InvalidArgumentException('Solo se pueden consumir solicitudes aprobadas.');
        }

        $this->estado = self::ESTADO_CONSUMIDA;
        $this->save();
    }

    /**
     * @return list<string>
     */
    public function camposAutorizados(): array
    {
        return array_values(array_filter(
            $this->campos_solicitados ?? [],
            fn ($c) => is_string($c) && $c !== ''
        ));
    }
}
