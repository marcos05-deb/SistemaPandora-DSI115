<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\CarbonImmutable;

class IndexCitasRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole('sysadmin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed() && $this->has('start') && $this->has('end')) {
                $start = CarbonImmutable::createFromFormat('Y-m-d', $this->start);
                $end = CarbonImmutable::createFromFormat('Y-m-d', $this->end);

                if ($start->diffInDays($end) > 45) {
                    $validator->errors()->add('end', 'El rango de fechas no puede exceder los 45 días.');
                }
            }
        });
    }
}
