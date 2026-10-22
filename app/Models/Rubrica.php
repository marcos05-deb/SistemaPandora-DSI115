<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Rubrica extends Model implements AuditableContract
{
    use AppendOnly;
    use Auditable;
    use HasUuids;

    public const CODIGO_RIESGO_PREVENTIVO = 'riesgo_preventivo';

    protected $table = 'rubricas';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
    ];

    public function versiones(): HasMany
    {
        return $this->hasMany(RubricaVersion::class, 'rubrica_id');
    }
}
