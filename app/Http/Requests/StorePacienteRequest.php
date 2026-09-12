<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePacienteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // La autorización se maneja en el middleware
    }

    public const ETIQUETAS_VALIDAS = [
        'Violencia Familiar',
        'Violencia Docente',
        'Violencia Pareja',
        'Ideas Suicidas',
        'Duelo',
        'Problemas Académicos',
        'Dificultades Socioeconómicas',
        'Problemas de Adaptación',
        'Conflictos Interpersonales',
    ];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Paciente
            'carnet' => ['required', 'string', 'regex:/^[A-Za-z]{2}\d{5}$/', 'unique:pacientes,carnet'],
            'nombre_completo' => 'required|string|max:255',
            'direccion' => 'required|string',
            'carrera_id' => 'required|exists:carreras,id',
            'sexo' => 'required|in:M,F,Otro',
            'estado_civil' => 'required|in:Soltero,Casado,Divorciado,Viudo,Unión Libre',
            'fecha_nacimiento' => 'required|date|before_or_equal:today',
            'profesion_ocupacion' => 'nullable|string|max:255',
            'fecha_primera_consulta' => 'nullable|date|before_or_equal:today',
            'referido_por' => 'nullable|string|max:255',
            'llevado_por' => 'nullable|string|max:255',
            'motivo_consulta' => 'required|string',
            'etiquetas_motivo' => ['nullable', 'array'],
            'etiquetas_motivo.*' => ['string', \Illuminate\Validation\Rule::in(self::ETIQUETAS_VALIDAS)],

            // Padre / Madre
            'padre_nombre' => 'required_if:responsable_parentesco,Padre|nullable|string|max:255',
            'padre_telefono' => ['nullable', 'string', 'regex:/^\d{8}$/'],
            'madre_nombre' => 'required_if:responsable_parentesco,Madre|nullable|string|max:255',
            'madre_telefono' => ['nullable', 'string', 'regex:/^\d{8}$/'],

            // Responsable Principal
            'responsable_parentesco' => 'required|in:Padre,Madre,Otro',
            'responsable_nombre' => 'required_if:responsable_parentesco,Otro|nullable|string|max:255',
            'responsable_telefono' => ['required', 'string', 'regex:/^\d{8}$/'],
            'responsable_direccion' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'carnet.regex' => 'El carnet debe contener exactamente 2 letras seguidas de 5 números.',
            'carnet.unique' => 'Este carnet ya ha sido registrado.',
            'fecha_nacimiento.before_or_equal' => 'La fecha no puede estar en el futuro.',
            'padre_telefono.regex' => 'Debe tener exactamente 8 dígitos.',
            'madre_telefono.regex' => 'Debe tener exactamente 8 dígitos.',
            'responsable_telefono.regex' => 'Debe tener exactamente 8 dígitos numéricos.',
        ];
    }
}
