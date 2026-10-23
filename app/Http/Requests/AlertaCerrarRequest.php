<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\AlertaPreventiva;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AlertaCerrarRequest extends FormRequest
{
    public function authorize(): bool
    {
        $alerta = $this->route('alerta');

        return $alerta instanceof AlertaPreventiva
            && ($this->user()?->can('cerrar', $alerta) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $resultado = $this->input('resultado');
        if (is_string($resultado)) {
            $this->merge(['resultado' => trim($resultado)]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'resultado' => ['required', 'string', 'min:10', 'max:5000'],
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
                $validator->errors()->add('resultado', 'Solo se pueden cerrar alertas activas o en seguimiento.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'resultado.required' => 'El resultado documentado es obligatorio para cerrar la alerta.',
            'resultado.min' => 'El resultado debe tener al menos :min caracteres.',
        ];
    }
}
