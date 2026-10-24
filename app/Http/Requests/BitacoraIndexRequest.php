<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\FiltrosBitacora;
use App\Enums\AccionBitacora;
use App\Models\Especialista;
use App\Policies\BitacoraPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class BitacoraIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Especialista && app(BitacoraPolicy::class)->viewAny($user);
    }

    protected function prepareForValidation(): void
    {
        $tz = (string) config('app.timezone');
        $defaults = [
            'desde' => CarbonImmutable::now($tz)->subDays(6)->toDateString(),
            'hasta' => CarbonImmutable::now($tz)->toDateString(),
        ];

        $this->merge([
            'desde' => $this->query('desde', $defaults['desde']),
            'hasta' => $this->query('hasta', $defaults['hasta']),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'usuario' => ['nullable', 'integer', 'exists:users,id'],
            'accion' => ['nullable', 'string', Rule::in(AccionBitacora::valores())],
            'registro' => ['nullable', 'string', 'max:64'],
            'tipo' => ['nullable', 'string', 'max:191'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $tz = (string) config('app.timezone');
            $desde = CarbonImmutable::parse($this->input('desde'), $tz);
            $hasta = CarbonImmutable::parse($this->input('hasta'), $tz);
            if ($desde->diffInDays($hasta) > 365) {
                $validator->errors()->add('hasta', 'El período no puede exceder 366 días.');
            }
        });
    }

    public function filtros(): FiltrosBitacora
    {
        $validated = $this->validated();

        return new FiltrosBitacora(
            desde: $validated['desde'],
            hasta: $validated['hasta'],
            usuarioId: isset($validated['usuario']) ? (int) $validated['usuario'] : null,
            accion: $validated['accion'] ?? null,
            registroId: $validated['registro'] ?? null,
            tipo: $validated['tipo'] ?? null,
            page: isset($validated['page']) ? (int) $validated['page'] : 1,
        );
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'desde.required' => 'Indica la fecha inicial.',
            'hasta.required' => 'Indica la fecha final.',
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'accion.in' => 'La acción seleccionada no es válida.',
            'usuario.exists' => 'El usuario seleccionado no existe.',
        ];
    }
}
