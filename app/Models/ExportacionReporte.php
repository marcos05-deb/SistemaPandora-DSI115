<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class ExportacionReporte extends Model implements Auditable
{
    use AppendOnly;
    use AuditableTrait;
    use HasUuids;

    protected $table = 'exportaciones_reporte';

    protected $fillable = [
        'usuario_id',
        'formato',
        'parametros',
        'sha256',
        'generado_en',
    ];

    protected $casts = [
        'parametros' => 'array',
        'generado_en' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Especialista::class, 'usuario_id');
    }
}
