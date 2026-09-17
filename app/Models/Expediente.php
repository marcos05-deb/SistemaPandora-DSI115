<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\AreaEncryptedFieldCast;
use App\Models\Scopes\AreaScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Expediente extends Model implements Auditable
{
    use HasFactory, SoftDeletes, HasUuids, \OwenIt\Auditing\Auditable;

    /**
     * Motivo del cambio clínico a incluir en el INSERT de auditoría (RP-04).
     */
    public ?string $auditMotivoCambio = null;

    /**
     * Longitud mínima del motivo de derivación (caracteres).
     */
    public const MOTIVO_DERIVACION_MIN = 10;

    /**
     * Longitud máxima del motivo de derivación (caracteres).
     */
    public const MOTIVO_DERIVACION_MAX = 1000;

    public const ESTADO_ABIERTO = 'abierto';

    public const ESTADO_EN_ATENCION = 'en_atencion';

    public const ESTADO_CERRADO = 'cerrado';

    /**
     * Estados que impiden crear otro expediente en la misma área.
     *
     * @var list<string>
     */
    public const ESTADOS_ACTIVOS = [
        self::ESTADO_ABIERTO,
        self::ESTADO_EN_ATENCION,
    ];

    public const MENSAJE_EXPEDIENTE_ACTIVO_DUPLICADO = 'El paciente ya tiene un expediente activo en esta área.';

    protected $fillable = [
        'paciente_id',
        'area_id',
        'motivo_consulta',
        'notas_clinicas',
        'diagnostico',
        'estado',
        'derivado_por_profesional_id',
        'fecha_derivacion',
        'motivo_derivacion',
        'motivo_cierre',
        'resultado_final',
        'fecha_cierre',
        'cerrado_por_profesional_id',
    ];

    protected $casts = [
        'motivo_consulta' => AreaEncryptedFieldCast::class,
        'notas_clinicas'  => AreaEncryptedFieldCast::class,
        'diagnostico'     => AreaEncryptedFieldCast::class,
        'fecha_derivacion'=> 'datetime',
        'motivo_derivacion' => AreaEncryptedFieldCast::class,
        'motivo_cierre'   => AreaEncryptedFieldCast::class,
        'resultado_final' => AreaEncryptedFieldCast::class,
        'fecha_cierre'    => 'datetime',
    ];

    /**
     * Evita dejar campos clínicos en texto plano en el log de auditoría.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function transformAudit(array $data): array
    {
        foreach (['old_values', 'new_values'] as $bucket) {
            foreach (['motivo_derivacion', 'resultado_final', 'motivo_cierre', 'motivo_consulta', 'notas_clinicas', 'diagnostico'] as $campo) {
                if (isset($data[$bucket][$campo])) {
                    $data[$bucket][$campo] = '[CIFRADO]';
                }
            }
        }

        if ($this->auditMotivoCambio) {
            $data['new_values']['motivo_cambio'] = $this->auditMotivoCambio;
        }

        return $data;
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new AreaScope());
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id', 'codigo');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function derivadoPor(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'derivado_por_profesional_id');
    }

    public function consultas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Consulta::class);
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'cerrado_por_profesional_id');
    }

    public function citas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Cita::class);
    }

    /**
     * ¿Existe un expediente activo (abierto o en_atencion) para paciente + área?
     * Omite AreaScope; conserva SoftDeletes. Opcionalmente bloquea filas (FOR UPDATE).
     */
    public static function existeActivoPara(string $pacienteId, int $areaId, bool $forUpdate = false): bool
    {
        $query = static::withoutGlobalScope(AreaScope::class)
            ->where('paciente_id', $pacienteId)
            ->where('area_id', $areaId)
            ->whereIn('estado', self::ESTADOS_ACTIVOS);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query->exists();
    }
}
