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
            'motivo_cierre' => 'required|string|min:10',
            'confirmacion_irreversible' => 'accepted',
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
