<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\ParametrosReporte;
use App\Enums\AgrupacionReporte;
use App\Enums\TipoIndicador;
use App\Models\Especialista;
use App\Policies\ReportePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ReporteGenerarRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Especialista && app(ReportePolicy::class)->verFormulario($user);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->query->has('agrupar_por')) {
            $this->merge(['agrupar_por' => [AgrupacionReporte::Indicador->value]]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'area' => ['required', 'string'],
            'indicadores' => ['required', 'array', 'min:1', 'max:'.count(TipoIndicador::cases())],
            'indicadores.*' => ['required', 'string', Rule::in(TipoIndicador::valores()), 'distinct'],
            'agrupar_por' => ['required', 'array', 'min:1', 'max:2'],
            'agrupar_por.*' => ['required', 'string', Rule::in(AgrupacionReporte::valores()), 'distinct'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $desde = CarbonImmutable::parse($this->validated('desde'), config('app.timezone'));
            $hasta = CarbonImmutable::parse($this->validated('hasta'), config('app.timezone'));
            if ($desde->diffInDays($hasta) > 365) {
                $validator->errors()->add('hasta', 'El período no puede exceder 366 días.');

                return;
            }

            $user = $this->user();
            if (! $user instanceof Especialista) {
                throw new AuthorizationException;
            }

            $area = $this->validated('area');
            if ($area === 'todas') {
                if (! app(ReportePolicy::class)->generar($user, null, true)) {
                    throw new AuthorizationException;
                }

                return;
            }

            $areaId = filter_var($area, FILTER_VALIDATE_INT);
            if ($areaId === false || $areaId < 1) {
                $validator->errors()->add('area', 'Selecciona un área válida.');

                return;
            }

            if (! DB::table('areas')->where('id', $areaId)->exists()) {
                $validator->errors()->add('area', 'El área seleccionada no existe.');

                return;
            }

            if (! app(ReportePolicy::class)->generar($user, $areaId, false)) {
                throw new AuthorizationException;
            }
        });
    }

    public function parametros(): ParametrosReporte
    {
        $validated = $this->validated();
        $allAreas = $validated['area'] === 'todas';

        return new ParametrosReporte(
            desde: $validated['desde'],
            hasta: $validated['hasta'],
            areaId: $allAreas ? null : (int) $validated['area'],
            todasAreas: $allAreas,
            indicadores: $validated['indicadores'],
            agruparPor: $validated['agrupar_por'],
        );
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'desde.required' => 'El período requiere una fecha inicial.',
            'hasta.required' => 'El período requiere una fecha final.',
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'area.required' => 'Selecciona un área antes de generar el reporte.',
            'indicadores.required' => 'Selecciona al menos un indicador.',
            'indicadores.min' => 'Selecciona al menos un indicador.',
            'indicadores.*.in' => 'Uno de los indicadores seleccionados no existe.',
            'agrupar_por.array' => 'Selecciona una forma de agrupación válida.',
            'agrupar_por.min' => 'Selecciona al menos una forma de agrupación.',
            'agrupar_por.max' => 'Puedes seleccionar como máximo dos agrupaciones.',
            'agrupar_por.*.in' => 'Una de las agrupaciones seleccionadas no es válida.',
        ];
    }
}
