<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EstadoCita;
use App\Models\Cita;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CitaAsistenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cita = $this->route('cita');

        return $cita instanceof Cita && $this->user()->can('update', $cita);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', Rule::in(EstadoCita::asistenciaValues())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.in' => 'El resultado debe ser Asistió o Ausente.',
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
                    'Solo se puede registrar asistencia de citas programadas.'
                );

                return;
            }

            // HU-12: comparar timestamp completo (hora de inicio de la cita).
            if ($cita->fecha_hora->isFuture()) {
                $validator->errors()->add(
                    'fecha_hora',
                    'No se puede registrar asistencia antes de la hora programada de la cita.'
                );
            }
        });
    }
}
