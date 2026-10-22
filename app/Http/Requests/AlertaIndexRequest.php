<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\AlertaPreventiva;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AlertaIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AlertaPreventiva::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'estado' => ['nullable', 'string', Rule::in(AlertaPreventiva::ESTADOS)],
        ];
    }
}
