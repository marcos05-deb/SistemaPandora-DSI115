<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Rubrica;
use App\Models\RubricaVariable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RubricaVersionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Rubrica::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rubrica = Rubrica::query()->where('codigo', Rubrica::CODIGO_RIESGO_PREVENTIVO)->first();
        $hasVersions = $rubrica?->versiones()->exists() ?? false;

        return [
            'vigente_desde' => ['required', 'date'],
            'umbral_puntaje_global' => ['prohibited'],
            'justificacion_cambio' => $hasVersions
                ? ['required', 'string', 'min:1', 'max:2000']
                : ['nullable', 'string', 'max:2000'],
            'validada_en' => ['required', 'date', 'before_or_equal:today'],
            'referencia_validacion' => ['required', 'string', 'min:1', 'max:255'],
            'variables' => ['required', 'array', 'min:3'],
            'variables.*' => ['required', 'array:tipo_senal,clave_indicador,modo_calculo,ventana_dias,ponderacion,umbral'],
            'variables.*.tipo_senal' => ['required', 'string', Rule::in(RubricaVariable::TIPOS_SENAL)],
            'variables.*.clave_indicador' => ['nullable', 'string', 'max:120'],
            'variables.*.modo_calculo' => ['required', 'string', Rule::in(RubricaVariable::MODOS_CALCULO)],
            'variables.*.ventana_dias' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'variables.*.ponderacion' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999.99'],
            'variables.*.umbral' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999.99'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $effectiveDateInput = $this->input('vigente_desde');
            if (! $validator->errors()->has('vigente_desde') && is_string($effectiveDateInput)) {
                if (! preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:\d{2})?$/D', $effectiveDateInput)) {
                    $validator->errors()->add('vigente_desde', 'La fecha de vigencia debe incluir hora y minutos.');

                    return;
                }

                try {
                    $effectiveDate = Carbon::parse($effectiveDateInput, config('app.timezone'));
                } catch (\Throwable) {
                    $validator->errors()->add('vigente_desde', 'La fecha de vigencia debe ser una fecha y hora válida.');

                    return;
                }

                $rubrica = Rubrica::query()->where('codigo', Rubrica::CODIGO_RIESGO_PREVENTIVO)->first();
                $latestVersion = $rubrica?->versiones()->orderByDesc('numero_version')->first();

                if (! $effectiveDate->isAfter(now())) {
                    $validator->errors()->add('vigente_desde', 'La fecha de vigencia debe ser posterior a la fecha y hora actuales.');
                }

                if ($latestVersion && ! $effectiveDate->isAfter($latestVersion->vigente_desde)) {
                    $validator->errors()->add('vigente_desde', 'La fecha de vigencia debe ser posterior a la última versión.');
                }
            }

            $variables = $this->input('variables');
            if (! is_array($variables)) {
                return;
            }

            $types = [];
            $tuples = [];
            foreach ($variables as $index => $variable) {
                if (! is_array($variable)) {
                    continue;
                }

                $type = $variable['tipo_senal'] ?? null;
                $mode = $variable['modo_calculo'] ?? null;
                if (is_string($type)) {
                    $types[] = $type;
                }

                if (is_string($type) && is_string($mode)) {
                    $tuple = serialize([$type, $variable['clave_indicador'] ?? null, $mode]);
                    if (isset($tuples[$tuple])) {
                        $validator->errors()->add("variables.{$index}.tipo_senal", 'La combinación de señal, indicador y cálculo debe ser única.');
                    }
                    $tuples[$tuple] = true;
                }

                if ($mode === 'frecuencia' && ($variable['ventana_dias'] ?? null) === null) {
                    $validator->errors()->add("variables.{$index}.ventana_dias", 'La ventana de días es obligatoria para el cálculo por frecuencia.');
                }

                if ($type === 'tendencia_suicida') {
                    if (blank($variable['clave_indicador'] ?? null)) {
                        $validator->errors()->add("variables.{$index}.clave_indicador", 'La clave del indicador es obligatoria para tendencia suicida.');
                    }

                    if ($mode !== 'puntaje') {
                        $validator->errors()->add("variables.{$index}.modo_calculo", 'La tendencia suicida debe calcularse por puntaje.');
                    }

                    if (($variable['ventana_dias'] ?? null) !== null) {
                        $validator->errors()->add("variables.{$index}.ventana_dias", 'La tendencia suicida no usa una ventana de días.');
                    }
                } elseif (($variable['clave_indicador'] ?? null) !== null) {
                    $validator->errors()->add("variables.{$index}.clave_indicador", 'La clave del indicador solo aplica a tendencia suicida.');
                }
            }

            foreach (RubricaVariable::TIPOS_SENAL as $type) {
                if (! in_array($type, $types, true)) {
                    $validator->errors()->add('variables', 'La rúbrica debe incluir cada tipo de señal.');
                    break;
                }
            }
        });
    }
}
