<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RevisarSolicitudCorreccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('sysadmin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nota_admin' => ['nullable', 'string', 'max:500'],
        ];
    }
}
