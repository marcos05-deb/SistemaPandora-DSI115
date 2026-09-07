<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CitaAsistenciaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorizations are handled in the controller via Policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', 'in:asistida,ausente'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $cita = $this->route('cita');
            
            if (!$cita || $cita->estado !== 'programada') {
                $validator->errors()->add('estado', 'Solo se puede registrar asistencia de citas programadas.');
                return;
            }
            
            // Evaluamos límite de fecha (startOfDay para permitir dentro del mismo día, antes de la hora)
            if (!$cita->fecha_hora->startOfDay()->lte(now()->startOfDay())) {
                $validator->errors()->add('fecha_hora', 'No se puede registrar asistencia de citas en el futuro.');
            }
        });
    }
}
