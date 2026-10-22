<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Profesional;
use App\Models\Rubrica;
use App\Models\RubricaVersion;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RubricaVersionService
{
    public function rubrica(): Rubrica
    {
        return Rubrica::query()
            ->where('codigo', Rubrica::CODIGO_RIESGO_PREVENTIVO)
            ->firstOrFail();
    }

    public function versiones(Rubrica $rubrica): LengthAwarePaginator
    {
        return $rubrica->versiones()
            ->with(['variables', 'creadaPor.especialista'])
            ->orderByDesc('vigente_desde')
            ->paginate(15)
            ->withQueryString();
    }

    public function ultimaVersion(Rubrica $rubrica): ?RubricaVersion
    {
        return $rubrica->versiones()
            ->with(['variables', 'creadaPor.especialista'])
            ->orderByDesc('numero_version')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crearVersion(Rubrica $r, array $datos, Profesional $autor): RubricaVersion
    {
        try {
            return DB::transaction(function () use ($r, $datos, $autor): RubricaVersion {
                // The app role cannot issue FOR UPDATE on append-only tables.
                DB::selectOne('SELECT public.lock_rubrica_for_versioning(?)', [$r->getKey()]);
                $rubrica = Rubrica::query()->whereKey($r->getKey())->firstOrFail();
                $ultimaVersion = $rubrica->versiones()
                    ->orderByDesc('numero_version')
                    ->first();
                $numeroVersion = ($ultimaVersion?->numero_version ?? 0) + 1;
                $vigenteDesde = Carbon::parse($datos['vigente_desde'], config('app.timezone'));

                if (! $vigenteDesde->isAfter(now())
                    || ($ultimaVersion && ! $vigenteDesde->isAfter($ultimaVersion->vigente_desde))) {
                    throw ValidationException::withMessages([
                        'vigente_desde' => 'La fecha de vigencia debe ser posterior a la fecha y hora actuales y a la última versión.',
                    ]);
                }

                if ($numeroVersion > 1 && blank($datos['justificacion_cambio'] ?? null)) {
                    throw ValidationException::withMessages([
                        'justificacion_cambio' => 'La justificación del cambio es obligatoria desde la segunda versión.',
                    ]);
                }

                $version = $rubrica->versiones()->create([
                    'numero_version' => $numeroVersion,
                    'vigente_desde' => $vigenteDesde,
                    'umbral_puntaje_global' => null,
                    'justificacion_cambio' => $datos['justificacion_cambio'] ?? null,
                    'validada_en' => $datos['validada_en'],
                    'referencia_validacion' => $datos['referencia_validacion'],
                    'creada_por_profesional_id' => $autor->getKey(),
                    'es_demo' => false,
                ]);

                foreach ($datos['variables'] as $variable) {
                    $version->variables()->create([
                        'tipo_senal' => $variable['tipo_senal'],
                        'clave_indicador' => $variable['clave_indicador'] ?? null,
                        'modo_calculo' => $variable['modo_calculo'],
                        'ventana_dias' => $variable['ventana_dias'] ?? null,
                        'ponderacion' => $variable['ponderacion'],
                        'umbral' => $variable['umbral'],
                    ]);
                }

                return $version->load('variables');
            });
        } catch (QueryException $exception) {
            $state = $exception->errorInfo[0] ?? null;
            $message = $exception->getMessage();

            if ($state === '23505' && str_contains($message, 'rubrica_versiones_rubrica_id_vigente_desde_unique')) {
                throw ValidationException::withMessages([
                    'vigente_desde' => 'Otra versión ya utiliza esta fecha de vigencia.',
                ]);
            }

            if ($state === '23505' && str_contains($message, 'rubrica_variables_tuple_key_')) {
                throw ValidationException::withMessages([
                    'variables' => 'La combinación de señal, indicador y cálculo debe ser única.',
                ]);
            }

            throw $exception;
        }
    }
}
