<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class CitaCancelarRequest extends FormRequest
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
            'motivo_cancelacion' => ['required', 'string', 'max:5000'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $cita = $this->route('cita');
            
            if (!$cita || $cita->estado !== 'programada') {
                $validator->errors()->add('estado', 'Solo se pueden cancelar citas que estén en estado programada.');
            }
        });
    }
}
