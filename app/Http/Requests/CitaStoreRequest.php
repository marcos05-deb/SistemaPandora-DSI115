<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Consulta;
use App\Models\Expediente;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CitaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expediente = $this->route('expediente');

        if (! $expediente instanceof Expediente) {
            return false;
        }

        return $this->user()->can('create', [\App\Models\Cita::class, $expediente]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'consulta_id' => ['required', 'uuid', 'exists:consultas,id'],
            'fecha_hora' => ['required', 'date', 'after:now'],
            'motivo' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consulta_id.required' => 'Debe agendar la cita desde una consulta activa.',
            'consulta_id.exists' => 'La consulta de origen no es válida.',
            'fecha_hora.after' => 'La fecha de la cita debe ser futura.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Expediente|null $expediente */
            $expediente = $this->route('expediente');

            if (! $expediente) {
                return;
            }

            if ($expediente->estado === Expediente::ESTADO_CERRADO) {
                $validator->errors()->add('expediente', 'No se pueden agendar citas en un expediente cerrado.');

                return;
            }

            $consultaId = $this->input('consulta_id');
            if (! $consultaId) {
                return;
            }

            $consulta = Consulta::query()->find($consultaId);

            if (! $consulta) {
                return;
            }

            if ($consulta->expediente_id !== $expediente->id) {
                $validator->errors()->add(
                    'consulta_id',
                    'La consulta de origen no pertenece a este expediente.'
                );
            }
        });
    }
}
