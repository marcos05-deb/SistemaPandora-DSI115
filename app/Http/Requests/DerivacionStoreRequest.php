<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Expediente;
use App\Models\Paciente;
use Illuminate\Foundation\Http\FormRequest;

class DerivacionStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $paciente = $this->route('paciente');

        if (! $paciente instanceof Paciente) {
            return false;
        }

        // Revalida en backend: rol + responsabilidad/autorización sobre el paciente.
        return $this->user()->can('derivar', [Expediente::class, $paciente]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'motivo_derivacion' => [
                'required',
                'string',
                'min:'.Expediente::MOTIVO_DERIVACION_MIN,
                'max:'.Expediente::MOTIVO_DERIVACION_MAX,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo_derivacion.required' => 'El motivo de derivación es obligatorio.',
            'motivo_derivacion.min' => 'El motivo de derivación debe tener al menos :min caracteres.',
            'motivo_derivacion.max' => 'El motivo de derivación no puede superar :max caracteres.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if ($this->area_id) {
                    $paciente = $this->route('paciente');
                    $pacienteId = $paciente instanceof Paciente ? $paciente->codigo : $paciente;

                    // Bypass seguro (ESTANDARES.md Sec 4): solo AreaScope; exists() sin hidratar PHI.
                    // Activo = abierto | en_atencion (un expediente en atención también bloquea).
                    $exists = Expediente::existeActivoPara($pacienteId, (int) $this->area_id);

                    if ($exists) {
                        $validator->errors()->add(
                            'area_id',
                            Expediente::MENSAJE_EXPEDIENTE_ACTIVO_DUPLICADO
                        );
                    }
                }
            },
        ];
    }
}
