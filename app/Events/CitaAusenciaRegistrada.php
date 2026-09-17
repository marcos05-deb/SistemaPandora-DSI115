<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Cita;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado cuando se registra ausencia — alimenta estadísticas preventivas (HU-14/HU-12).
 */
class CitaAusenciaRegistrada
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Cita $cita) {}
}
