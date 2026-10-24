<?php

namespace App\Models;

use App\Casts\EncryptedFieldCast;
use App\Models\Concerns\MasksEncryptedAuditAttributes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class ContactoPaciente extends Model implements Auditable
{
    use AuditableTrait, HasFactory, HasUuids, MasksEncryptedAuditAttributes, SoftDeletes {
        MasksEncryptedAuditAttributes::transformAudit insteadof AuditableTrait;
    }

    protected $table = 'contactos_paciente';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'paciente_id',
        'nombre_completo',
        'parentesco',
        'telefono_personal',
        'telefono_casa',
        'direccion',
        'es_responsable',
    ];

    protected $casts = [
        'nombre_completo' => EncryptedFieldCast::class,
        'telefono_personal' => EncryptedFieldCast::class,
        'telefono_casa' => EncryptedFieldCast::class,
        'direccion' => EncryptedFieldCast::class,
        'es_responsable' => 'boolean',
    ];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id', 'codigo');
    }
}
