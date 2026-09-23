<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\SolicitudCorreccionExpediente;
use Illuminate\Foundation\Http\FormRequest;

class StoreSolicitudCorreccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $camposPermitidos = $this->route('expediente')
            ? SolicitudCorreccionExpediente::CAMPOS_CLINICO
            : SolicitudCorreccionExpediente::CAMPOS_DATOS;

        return [
            'campos' => ['required', 'array', 'min:1'],
            'campos.*' => ['required', 'string', 'in:'.implode(',', $camposPermitidos)],
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'campos.required' => 'Debe indicar al menos un campo.',
            'campos.min' => 'Debe indicar al menos un campo.',
            'motivo.required' => 'El motivo es obligatorio.',
            'motivo.min' => 'El motivo debe tener al menos 10 caracteres.',
        ];
    }
}
