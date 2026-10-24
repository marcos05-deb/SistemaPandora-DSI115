<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class ExportacionAuditoria extends Model implements Auditable
{
    use AppendOnly;
    use AuditableTrait;
    use HasUuids;

    protected $table = 'exportaciones_auditoria';

    protected $fillable = [
        'usuario_id',
        'filtros',
        'total_eventos',
        'sha256',
        'formato',
        'corte_en',
        'generada_en',
    ];

    protected $casts = [
        'filtros' => 'array',
        'corte_en' => 'datetime',
        'generada_en' => 'datetime',
    ];

    /** @return array<int, string> */
    public function getAuditExclude(): array
    {
        return [];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Especialista::class, 'usuario_id');
    }
}
