<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Expediente;
use App\Models\Paciente;

class DerivacionStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('derivar', Expediente::class);
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
        ];
    }
    
    public function after(): array
    {
        return [
            function ($validator) {
                if ($this->area_id) {
                    $paciente = $this->route('paciente');
                    $pacienteId = $paciente instanceof Paciente ? $paciente->codigo : $paciente;

                    $exists = Expediente::where('paciente_id', $pacienteId)
                        ->where('area_id', $this->area_id)
                        ->where('estado', 'abierto')
                        ->exists();

                    if ($exists) {
                        $validator->errors()->add(
                            'area_id',
                            'El paciente ya tiene un expediente abierto en esta área.'
                        );
                    }
                }
            }
        ];
    }
}
