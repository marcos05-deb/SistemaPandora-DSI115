<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ExpedienteUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expediente = $this->route('expediente');

        return $expediente instanceof Expediente
            && $this->user()->can('update', $expediente);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motivo_consulta' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'notas_clinicas' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'diagnostico' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'motivo_cambio' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo_cambio.required' => 'Debe indicar el motivo del cambio.',
            'motivo_cambio.min' => 'El motivo del cambio debe tener al menos :min caracteres.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Expediente|null $expediente */
            $expediente = $this->route('expediente');

            if ($expediente && $expediente->estado === Expediente::ESTADO_CERRADO) {
                $validator->errors()->add(
                    'estado',
                    'No se puede actualizar un expediente cerrado.'
                );
            }

            $campos = ['motivo_consulta', 'notas_clinicas', 'diagnostico'];
            $tieneCambio = false;
            foreach ($campos as $campo) {
                if ($this->exists($campo)) {
                    $tieneCambio = true;
                    break;
                }
            }

            if (! $tieneCambio) {
                $validator->errors()->add(
                    'motivo_consulta',
                    'Debe modificar al menos un campo permitido del expediente.'
                );
            }
        });
    }
}
