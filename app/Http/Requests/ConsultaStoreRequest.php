<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Consulta;
use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConsultaStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $expediente = $this->route('expediente');

        if (! $expediente instanceof Expediente) {
            return false;
        }

        return $this->user()->can('create', [Consulta::class, $expediente]);
    }

    public const EVALUACION_CAMPOS = [
        'apariencia_externa',
        'voz',
        'patrones_habla',
        'expresiones_faciales',
        'ademanes',
        'actitudes_tratamiento',
        'impresion',
        'plan_tratamiento',
        'pronostico',
    ];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $expediente = $this->route('expediente');
        $isFirstConsulta = $expediente && ! $expediente->consultas()->exists();

        $rules = [
            'motivo_consulta' => ['required', 'string'],
            // Jira: diagnóstico u observación (notas_clinicas) — al menos uno, validado en withValidator.
            'notas_clinicas' => ['nullable', 'string'],
            'diagnostico' => ['nullable', 'string'],
            'plan_atencion' => ['required', 'string', 'min:10', 'max:1000'],
            'tecnica_utilizada' => ['required', 'string'],
            // Fecha clínica explícita (independiente de created_at).
            'fecha_consulta' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.now()->subYear()->toDateString()],
            'evaluacion_inicial' => [$isFirstConsulta ? 'required' : 'nullable', 'array'],
        ];

        if ($isFirstConsulta) {
            foreach (self::EVALUACION_CAMPOS as $campo) {
                $rules["evaluacion_inicial.{$campo}"] = ['required', 'string'];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo_consulta.required' => 'El motivo de la consulta es obligatorio.',
            'tecnica_utilizada.required' => 'La técnica utilizada es obligatoria.',
            'plan_atencion.required' => 'El plan de atención es obligatorio.',
            'plan_atencion.min' => 'El plan de atención debe tener al menos :min caracteres.',
            'plan_atencion.max' => 'El plan de atención no puede superar :max caracteres.',
            'fecha_consulta.required' => 'La fecha de la consulta es obligatoria.',
            'fecha_consulta.before_or_equal' => 'La fecha de la consulta no puede ser futura.',
            'fecha_consulta.after_or_equal' => 'La fecha de la consulta no puede ser anterior a un año.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $diagnostico = trim((string) $this->input('diagnostico', ''));
            $observacion = trim((string) $this->input('notas_clinicas', ''));

            if ($diagnostico === '' && $observacion === '') {
                $validator->errors()->add(
                    'diagnostico',
                    'Debe registrar un diagnóstico o una observación clínica.'
                );
                $validator->errors()->add(
                    'notas_clinicas',
                    'Debe registrar un diagnóstico o una observación clínica.'
                );
            }
        });
    }
}
