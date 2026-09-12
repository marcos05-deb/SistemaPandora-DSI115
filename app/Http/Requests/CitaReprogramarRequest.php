<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class CitaReprogramarRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Autenticación/Autorización en Policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha_hora' => ['required', 'date', 'after:now'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $cita = $this->route('cita');
            
            if (!$cita || $cita->estado !== 'programada') {
                $validator->errors()->add('estado', 'Solo se pueden reprogramar citas que estén en estado programada.');
            }
        });
    }
}
