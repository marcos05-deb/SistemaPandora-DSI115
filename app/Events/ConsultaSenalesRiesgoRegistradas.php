<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Consulta;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConsultaSenalesRiesgoRegistradas implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Consulta $consulta) {}
}
