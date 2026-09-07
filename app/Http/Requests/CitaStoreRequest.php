<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CitaStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorized via middleware/policy
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
            'motivo'     => ['required', 'string', 'max:5000'],
        ];
    }
    
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $expediente = $this->route('expediente');
            
            if ($expediente && $expediente->estado === 'cerrado') {
                $validator->errors()->add('expediente', 'No se pueden agendar citas en un expediente cerrado.');
            }
        });
    }
}
