<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\AreaEncryptedFieldCast;
use App\Models\Scopes\AreaScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class AlertaPreventiva extends Model implements AuditableContract
{
    use Auditable;
    use HasUuids;

    public const ESTADOS = ['activa', 'en_seguimiento', 'cerrada', 'descartada'];

    public const ESTADOS_ACTIVOS = ['activa', 'en_seguimiento'];

    public const SENALES = ['crisis_psicologica', 'ausencia_cita', 'tendencia_suicida'];

    protected $table = 'alertas_preventivas';

    protected $fillable = [
        'paciente_id',
        'area_id',
        'evaluacion_riesgo_id',
        'rubrica_version_id',
        'senal',
        'valor_calculado',
        'umbral',
        'estado',
        'responsable_profesional_id',
        'generada_en',
        'resultado_cierre',
        'justificacion_descarte',
        'cerrada_en',
        'descartada_en',
        'cerrada_por_profesional_id',
        'descartada_por_profesional_id',
    ];

    protected function casts(): array
    {
        return [
            'valor_calculado' => AreaEncryptedFieldCast::class,
            'umbral' => 'decimal:2',
            'generada_en' => 'datetime',
            'resultado_cierre' => AreaEncryptedFieldCast::class,
            'justificacion_descarte' => AreaEncryptedFieldCast::class,
            'cerrada_en' => 'datetime',
            'descartada_en' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new AreaScope);
    }

    /** @param array<string, mixed> $data */
    public function transformAudit(array $data): array
    {
        foreach (['old_values', 'new_values'] as $bucket) {
            foreach ([
                'paciente_id',
                'area_id',
                'evaluacion_riesgo_id',
                'rubrica_version_id',
                'senal',
                'valor_calculado',
                'umbral',
                'responsable_profesional_id',
                'cerrada_por_profesional_id',
                'descartada_por_profesional_id',
                'resultado_cierre',
                'justificacion_descarte',
            ] as $field) {
                if (isset($data[$bucket][$field])) {
                    $data[$bucket][$field] = '[CIFRADO]';
                }
            }
        }

        return $data;
    }

    public static function labelForSignal(string $signal): string
    {
        return match ($signal) {
            'crisis_psicologica' => 'Riesgo por crisis psicológica',
            'ausencia_cita' => 'Ausencias en citas',
            'tendencia_suicida' => 'Riesgo de tendencia suicida',
            default => 'Alerta preventiva',
        };
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id', 'codigo');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(EvaluacionRiesgo::class, 'evaluacion_riesgo_id');
    }

    public function versionRubrica(): BelongsTo
    {
        return $this->belongsTo(RubricaVersion::class, 'rubrica_version_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'responsable_profesional_id');
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(SeguimientoPreventivo::class, 'alerta_preventiva_id')->orderBy('created_at');
    }

    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'cerrada_por_profesional_id');
    }

    public function descartadaPor(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'descartada_por_profesional_id');
    }
}
