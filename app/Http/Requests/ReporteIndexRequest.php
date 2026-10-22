<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Especialista;
use App\Policies\ReportePolicy;
use Illuminate\Foundation\Http\FormRequest;

final class ReporteIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Especialista && app(ReportePolicy::class)->verFormulario($user);
    }
}
