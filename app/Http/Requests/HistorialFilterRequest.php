<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class HistorialFilterRequest extends FormRequest
{
    public const TIPOS_ATENCION = ['consulta'];

    public const RANGO_MAXIMO_DIAS = 730;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'tipo_atencion' => ['nullable', 'string', Rule::in(self::TIPOS_ATENCION)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_hasta.after_or_equal' => 'La fecha final debe ser posterior o igual a la fecha inicial.',
            'tipo_atencion.in' => 'El tipo de atención seleccionado no es válido.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $desde = $this->input('fecha_desde');
            $hasta = $this->input('fecha_hasta');

            if ($desde && $hasta) {
                $dias = \Illuminate\Support\Carbon::parse($desde)
                    ->diffInDays(\Illuminate\Support\Carbon::parse($hasta));

                if ($dias > self::RANGO_MAXIMO_DIAS) {
                    $validator->errors()->add(
                        'fecha_hasta',
                        'El rango máximo del historial es de '.self::RANGO_MAXIMO_DIAS.' días.'
                    );
                }
            }

            $areaId = $this->input('area_id');
            if (! $areaId) {
                return;
            }

            $user = $this->user();
            $autorizadas = $user?->areas()->pluck('areas.id') ?? collect();

            if ($autorizadas->isEmpty() && $user?->profesional?->area_id) {
                $autorizadas = collect([$user->profesional->area_id]);
            }

            if (! $autorizadas->contains($areaId)) {
                $validator->errors()->add(
                    'area_id',
                    'No tiene autorización para filtrar por el área indicada.'
                );
            }
        });
    }
}
