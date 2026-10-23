<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\FiltrosIndicador;
use App\Enums\TipoIndicador;
use App\Models\Especialista;
use App\Policies\IndicadorPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IndicadoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof Especialista) {
            return false;
        }

        $areaId = $this->query('area_id');
        if (is_int($areaId) || is_string($areaId)) {
            $areaId = filter_var($areaId, FILTER_VALIDATE_INT);
            if ($areaId !== false && $areaId > 0
                && ! app(IndicadorPolicy::class)->ver($user, $areaId)) {
                return false;
            }
        }

        return app(IndicadorPolicy::class)->ver($user);
    }

    protected function prepareForValidation(): void
    {
        $today = CarbonImmutable::today(config('app.timezone'));
        $defaults = [];

        if (! $this->query->has('desde')) {
            $defaults['desde'] = $today->subDays(29)->toDateString();
        }
        if (! $this->query->has('hasta')) {
            $defaults['hasta'] = $today->toDateString();
        }
        if (! $this->query->has('indicadores')) {
            $defaults['indicadores'] = TipoIndicador::valores();
        }

        if ($defaults !== []) {
            $this->merge($defaults);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'area_id' => ['nullable', 'integer', 'min:1', Rule::exists('areas', 'id')],
            'indicadores' => ['required', 'array', 'min:1', 'max:'.count(TipoIndicador::cases())],
            'indicadores.*' => ['required', 'string', Rule::in(TipoIndicador::valores()), 'distinct'],
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
            }
        });
    }

    public function filtros(): FiltrosIndicador
    {
        $validated = $this->validated();

        return new FiltrosIndicador(
            desde: $validated['desde'],
            hasta: $validated['hasta'],
            areaId: isset($validated['area_id']) ? (int) $validated['area_id'] : null,
            indicadores: $validated['indicadores'],
        );
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'desde.required' => 'La fecha inicial es obligatoria.',
            'desde.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'hasta.required' => 'La fecha final es obligatoria.',
            'hasta.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'area_id.integer' => 'El área seleccionada no es válida.',
            'area_id.exists' => 'El área seleccionada no existe.',
            'indicadores.required' => 'Selecciona al menos un indicador.',
            'indicadores.array' => 'La selección de indicadores no es válida.',
            'indicadores.min' => 'Selecciona al menos un indicador.',
            'indicadores.*.in' => 'Uno de los indicadores seleccionados no existe.',
        ];
    }
}
