<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorización vía PacientePolicy en el controlador
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('carnet') && is_string($this->input('carnet'))) {
            $this->merge([
                'carnet' => strtoupper(trim($this->input('carnet'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $paciente = $this->route('paciente');

        return [
            'carnet' => [
                'required',
                'string',
                'regex:/^[A-Z]{2}\d{5}$/',
                Rule::unique('pacientes', 'carnet')->ignore($paciente?->codigo, 'codigo'),
            ],
            'nombre_completo' => 'required|string|max:255',
            'direccion' => 'required|string',
            'carrera_id' => 'required|exists:carreras,id',
            'sexo' => 'required|in:M,F,Otro',
            'estado_civil' => 'required|in:Soltero,Casado,Divorciado,Viudo,Unión Libre',
            'fecha_nacimiento' => 'required|date|before_or_equal:today',
            'profesion_ocupacion' => 'nullable|string|max:255',
            'referido_por' => 'nullable|string|max:255',
            'llevado_por' => 'nullable|string|max:255',
            'motivo_correccion' => 'required|string|min:10|max:500',

            'padre_nombre' => 'nullable|string|max:255',
            'padre_telefono' => ['nullable', 'string', 'regex:/^\d{8}$/'],
            'madre_nombre' => 'nullable|string|max:255',
            'madre_telefono' => ['nullable', 'string', 'regex:/^\d{8}$/'],

            'responsable_parentesco' => 'required|in:Padre,Madre,Otro',
            'responsable_nombre' => 'required_if:responsable_parentesco,Otro|nullable|string|max:255',
            'responsable_telefono' => ['required', 'string', 'regex:/^\d{8}$/'],
            'responsable_direccion' => 'required|string',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'carnet.regex' => 'El carnet debe contener exactamente 2 letras seguidas de 5 números (formato AA00000).',
            'carnet.unique' => 'Este carnet ya está registrado por otro paciente.',
            'motivo_correccion.required' => 'El motivo de la corrección es obligatorio.',
            'motivo_correccion.min' => 'El motivo de la corrección debe tener al menos 10 caracteres.',
            'motivo_correccion.max' => 'El motivo de la corrección no puede superar 500 caracteres.',
            'fecha_nacimiento.before_or_equal' => 'La fecha no puede estar en el futuro.',
            'padre_telefono.regex' => 'Debe tener exactamente 8 dígitos.',
            'madre_telefono.regex' => 'Debe tener exactamente 8 dígitos.',
            'responsable_telefono.regex' => 'Debe tener exactamente 8 dígitos numéricos.',
        ];
    }
}
