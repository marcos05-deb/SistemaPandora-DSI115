<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EstadoCita;
use App\Models\Cita;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CitaCancelarRequest extends FormRequest
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
            'motivo_cancelacion' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo_cancelacion.required' => 'El motivo de cancelación es obligatorio.',
            'motivo_cancelacion.min' => 'El motivo de cancelación debe tener al menos :min caracteres.',
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
                    'Solo se pueden cancelar citas que estén en estado programada.'
                );

                return;
            }

            if (! $cita->fecha_hora->isFuture()) {
                $validator->errors()->add(
                    'fecha_hora',
                    'Solo se pueden cancelar citas futuras cuya hora aún no haya llegado.'
                );
            }
        });
    }
}
