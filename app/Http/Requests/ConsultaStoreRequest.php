<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Consulta;
use App\Models\Expediente;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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

    public const TIPOS_REFERENCIA = ['hospital', 'clinica', 'especialista_externo', 'otra'];

    public const PRIORIDADES_REFERENCIA = ['urgente', 'no_urgente'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
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
            'fecha_consulta' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.now()->subDays((int) config('limites.consulta_antiguedad_max_dias'))->toDateString()],
            'evaluacion_inicial' => [$isFirstConsulta ? 'required' : 'nullable', 'array'],
            'crisis_psicologica' => ['required', 'boolean'],
            'indicadores_tendencia_suicida' => ['present', 'array', 'max:50'],
            'indicadores_tendencia_suicida.*' => ['required', 'string', 'max:120', 'distinct'],
        ];

        $conReferencia = $this->boolean('referencia_externa');
        $rules['referencia_externa'] = ['sometimes', 'boolean'];
        $rules['institucion_destino'] = $conReferencia ? ['required', 'string', 'max:150'] : ['prohibited'];
        $rules['motivo_referencia'] = $conReferencia ? ['required', 'string', 'min:10', 'max:2000'] : ['prohibited'];
        $rules['tipo_referencia'] = $conReferencia ? ['required', Rule::in(self::TIPOS_REFERENCIA)] : ['prohibited'];
        $rules['prioridad_referencia'] = $conReferencia ? ['required', Rule::in(self::PRIORIDADES_REFERENCIA)] : ['prohibited'];

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
        $messages = [
            'motivo_consulta.required' => 'El motivo de la consulta es obligatorio.',
            'tecnica_utilizada.required' => 'La técnica utilizada es obligatoria.',
            'plan_atencion.required' => 'El plan de atención es obligatorio.',
            'plan_atencion.min' => 'El plan de atención debe tener al menos :min caracteres.',
            'plan_atencion.max' => 'El plan de atención no puede superar :max caracteres.',
            'fecha_consulta.required' => 'La fecha de la consulta es obligatoria.',
            'fecha_consulta.before_or_equal' => 'La fecha de la consulta no puede ser futura.',
            'fecha_consulta.after_or_equal' => 'La fecha de la consulta no puede tener más de '.config('limites.consulta_antiguedad_max_dias').' días de antigüedad.',
            'evaluacion_inicial.required' => 'La evaluación inicial es obligatoria en la primera consulta.',
            'crisis_psicologica.required' => 'Indica si se registró una crisis psicológica.',
            'indicadores_tendencia_suicida.present' => 'Registra los indicadores de tendencia suicida o indica que no hay ninguno.',
        ];

        $messages += [
            'referencia_externa.boolean' => 'Indica si la consulta incluye una referencia externa.',
            'institucion_destino.required' => 'La institución de destino es obligatoria.',
            'institucion_destino.max' => 'La institución de destino no puede superar :max caracteres.',
            'motivo_referencia.required' => 'El motivo de la referencia es obligatorio.',
            'motivo_referencia.min' => 'El motivo de la referencia debe tener al menos :min caracteres.',
            'motivo_referencia.max' => 'El motivo de la referencia no puede superar :max caracteres.',
            'tipo_referencia.required' => 'El tipo de referencia es obligatorio.',
            'tipo_referencia.in' => 'El tipo de referencia no es válido.',
            'prioridad_referencia.required' => 'La prioridad de la referencia es obligatoria.',
            'prioridad_referencia.in' => 'La prioridad de la referencia no es válida.',
            'institucion_destino.prohibited' => 'Los datos de referencia solo aplican si hay referencia externa.',
            'motivo_referencia.prohibited' => 'Los datos de referencia solo aplican si hay referencia externa.',
            'tipo_referencia.prohibited' => 'Los datos de referencia solo aplican si hay referencia externa.',
            'prioridad_referencia.prohibited' => 'Los datos de referencia solo aplican si hay referencia externa.',
        ];

        foreach (self::EVALUACION_CAMPOS as $campo) {
            $messages["evaluacion_inicial.{$campo}.required"] = 'Completa este campo de la evaluación inicial.';
        }

        return $messages;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $diagnostico = trim((string) $this->input('diagnostico', ''));
            $observacion = trim((string) $this->input('notas_clinicas', ''));

            if ($diagnostico === '' && $observacion === '') {
                $validator->errors()->add(
                    'diagnostico',
                    'Completa el diagnóstico o las notas clínicas; al menos uno es obligatorio.'
                );
                $validator->errors()->add(
                    'notas_clinicas',
                    'Completa el diagnóstico o las notas clínicas; al menos uno es obligatorio.'
                );
            }
        });
    }
}
