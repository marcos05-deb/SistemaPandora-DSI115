<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EstadoCita;
use App\Models\Cita;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CitaReprogramarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha_hora' => ['required', 'date', 'after:now'],
            'motivo_reprogramacion' => ['required', 'string', 'min:10', 'max:2000'],
            'acordada_con_paciente' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_hora.after' => 'La nueva fecha de la cita debe ser futura.',
            'motivo_reprogramacion.required' => 'El motivo de reprogramación es obligatorio.',
            'motivo_reprogramacion.min' => 'El motivo de reprogramación debe tener al menos :min caracteres.',
            'acordada_con_paciente.accepted' => 'Debe confirmar que la nueva fecha fue acordada con el paciente.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Cita|null $cita */
            $cita = $this->route('cita');

            if (! $cita) {
                return;
            }

            if ($cita->estado !== EstadoCita::Programada->value) {
                $validator->errors()->add(
                    'estado',
                    'Solo se pueden reprogramar citas que estén en estado programada.'
                );

                return;
            }

            if (! $cita->fecha_hora->isFuture()) {
                $validator->errors()->add(
                    'fecha_hora',
                    'Solo se pueden reprogramar citas futuras cuya hora aún no haya llegado.'
                );
            }
        });
    }
}
