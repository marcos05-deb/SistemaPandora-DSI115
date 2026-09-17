<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExpedienteCloseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resultado_final' => ['required', 'string', 'min:10', 'max:2000'],
            'motivo_cierre' => ['required', 'string', 'min:10', 'max:2000'],
            'confirmacion_irreversible' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resultado_final.required' => 'El resultado final es obligatorio para cerrar el expediente.',
            'resultado_final.min' => 'El resultado final debe tener al menos :min caracteres.',
            'motivo_cierre.required' => 'El motivo de cierre es obligatorio.',
            'confirmacion_irreversible.accepted' => 'Debe confirmar que el cierre es irreversible.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $expediente = $this->route('expediente');
            if ($expediente && $expediente->estado === 'cerrado') {
                abort(422, 'El expediente ya se encuentra cerrado.');
            }
        });
    }
}
