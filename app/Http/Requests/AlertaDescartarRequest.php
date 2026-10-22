<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\AlertaPreventiva;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AlertaDescartarRequest extends FormRequest
{
    public function authorize(): bool
    {
        $alerta = $this->route('alerta');

        return $alerta instanceof AlertaPreventiva
            && ($this->user()?->can('descartar', $alerta) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $justificacion = $this->input('justificacion');
        if (is_string($justificacion)) {
            $this->merge(['justificacion' => trim($justificacion)]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'justificacion' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $alerta = $this->route('alerta');
            if ($alerta instanceof AlertaPreventiva
                && ! in_array($alerta->estado, AlertaPreventiva::ESTADOS_ACTIVOS, true)) {
                $validator->errors()->add('justificacion', 'Solo se pueden descartar alertas activas o en seguimiento.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'justificacion.required' => 'La justificación del descarte es obligatoria.',
            'justificacion.min' => 'La justificación debe tener al menos :min caracteres.',
        ];
    }
}
