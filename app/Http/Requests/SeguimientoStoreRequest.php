<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\AlertaPreventiva;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SeguimientoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $alerta = $this->route('alerta');

        return $alerta instanceof AlertaPreventiva
            && ($this->user()?->can('registrarSeguimiento', $alerta) ?? false);
    }

    protected function prepareForValidation(): void
    {
        foreach (['valoracion', 'accion_preventiva'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $this->merge([$field => trim($value)]);
            }
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $today = Carbon::today(config('app.timezone'));
        $alerta = $this->route('alerta');
        $responsableId = $alerta instanceof AlertaPreventiva
            ? $this->user()?->profesionalParaArea($alerta->area_id)?->getKey()
            : null;

        return [
            'valoracion' => ['required', 'string', 'min:10', 'max:5000'],
            'accion_preventiva' => ['required', 'string', 'min:10', 'max:5000'],
            'responsable_profesional_id' => [
                'required',
                'uuid',
                Rule::in($responsableId === null ? [] : [(string) $responsableId]),
            ],
            'proxima_revision' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$today->toDateString(),
                'before_or_equal:'.$today->copy()->addDays(365)->toDateString(),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $alerta = $this->route('alerta');
            if ($alerta instanceof AlertaPreventiva
                && ! in_array($alerta->estado, AlertaPreventiva::ESTADOS_ACTIVOS, true)) {
                $validator->errors()->add('estado', 'No se puede registrar seguimiento sobre una alerta terminal.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'valoracion.required' => 'La valoración es obligatoria.',
            'valoracion.min' => 'La valoración debe tener al menos :min caracteres.',
            'accion_preventiva.required' => 'La acción preventiva es obligatoria.',
            'accion_preventiva.min' => 'La acción preventiva debe tener al menos :min caracteres.',
            'responsable_profesional_id.required' => 'El responsable del seguimiento es obligatorio.',
            'responsable_profesional_id.in' => 'El responsable debe ser el profesional que registra el seguimiento.',
            'proxima_revision.required' => 'La fecha de próxima revisión es obligatoria.',
            'proxima_revision.after_or_equal' => 'La próxima revisión no puede ser anterior a hoy.',
            'proxima_revision.before_or_equal' => 'La próxima revisión no puede exceder 365 días desde hoy.',
        ];
    }
}
