<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsultaStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorización manejada en la Policy
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
        $isFirstConsulta = $expediente && !$expediente->consultas()->exists();

        $rules = [
            'motivo_consulta' => ['required', 'string'],
            'notas_clinicas'  => ['required', 'string'],
            'diagnostico'     => ['required', 'string'],
            'tecnica_utilizada' => ['required', 'string'],
            'evaluacion_inicial' => [$isFirstConsulta ? 'required' : 'nullable', 'array'],
        ];

        if ($isFirstConsulta) {
            foreach (self::EVALUACION_CAMPOS as $campo) {
                $rules["evaluacion_inicial.{$campo}"] = ['required', 'string'];
            }
        }

        return $rules;
    }
}
