<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\AreaScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Cita extends Model implements Auditable
{
    use HasFactory, SoftDeletes, HasUuids, \OwenIt\Auditing\Auditable;

    /**
     * Aplica el AreaScope a nivel global para aislar las citas por área clínica.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new AreaScope);
    }

    protected $fillable = [
        'expediente_id',
        'consulta_id',
        'profesional_id',
        'area_id',
        'fecha_hora',
        'motivo',
        'estado',
        'fecha_registro_asistencia',
        'registrado_por_profesional_id',
        'motivo_cancelacion',
        'cita_origen_id',
        'motivo_reprogramacion',
        'reprogramado_por_profesional_id',
        'fecha_reprogramacion',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'fecha_registro_asistencia' => 'datetime',
        'fecha_reprogramacion' => 'datetime',
        'motivo' => \App\Casts\EncryptedFieldCast::class,
        'motivo_cancelacion' => \App\Casts\EncryptedFieldCast::class,
        'motivo_reprogramacion' => \App\Casts\EncryptedFieldCast::class,
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class);
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'registrado_por_profesional_id');
    }

    public function registrarAsistencia(string $estado, string $profesionalId): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($estado, $profesionalId) {
            /** @var self $cita */
            $cita = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($cita->estado !== \App\Enums\EstadoCita::Programada->value) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'estado' => 'Solo se puede registrar asistencia de citas programadas.',
                ]);
            }

            if ($cita->fecha_hora->isFuture()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'fecha_hora' => 'No se puede registrar asistencia antes de la hora programada de la cita.',
                ]);
            }

            $cita->estado = $estado;
            $cita->registrado_por_profesional_id = $profesionalId;
            $cita->fecha_registro_asistencia = now();
            $cita->save();

            $this->refresh();

            if ($estado === \App\Enums\EstadoCita::Ausente->value) {
                \App\Events\CitaAusenciaRegistrada::dispatch($cita);
            }
        });
    }

    public function citaOrigen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cita_origen_id');
    }
}
