<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Especialista;
use App\Models\Expediente;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ExpedienteActualizacionService
{
    public function __construct(
        private readonly SolicitudCorreccionExpedienteService $solicitudes
    ) {}

    /**
     * @param  array<string, mixed>  $validated  campos clínicos + motivo_cambio
     */
    public function actualizar(Expediente $expediente, Especialista $usuario, array $validated): Expediente
    {
        if (! $usuario->can('update', $expediente)) {
            abort(403);
        }

        $motivoCambio = trim((string) $validated['motivo_cambio']);
        unset($validated['motivo_cambio']);

        return DB::transaction(function () use ($expediente, $usuario, $validated, $motivoCambio) {
            $expediente = Expediente::query()->whereKey($expediente->id)->lockForUpdate()->firstOrFail();

            if ($expediente->estado === Expediente::ESTADO_CERRADO) {
                throw ValidationException::withMessages([
                    'estado' => 'No se puede actualizar un expediente cerrado.',
                ]);
            }

            $permiso = null;
            $esPrimera = $expediente->actualizado_clinicamente_en === null;

            if (! $esPrimera) {
                $permiso = $this->solicitudes->permisoClinicoVigente($expediente);
                if (! $permiso) {
                    throw ValidationException::withMessages([
                        'motivo_cambio' => 'Este expediente ya fue actualizado una vez. Solicite permiso al administrador para una nueva actualización.',
                    ]);
                }
            }

            $payload = [];
            foreach (['motivo_consulta', 'notas_clinicas', 'diagnostico'] as $campo) {
                if (array_key_exists($campo, $validated)) {
                    $payload[$campo] = $validated[$campo];
                }
            }

            if ($payload === []) {
                throw ValidationException::withMessages([
                    'motivo_consulta' => 'Debe modificar al menos un campo permitido del expediente.',
                ]);
            }

            if ($permiso) {
                $autorizados = $permiso->camposAutorizados();
                $fuera = array_diff(array_keys($payload), $autorizados);
                if ($fuera !== []) {
                    throw ValidationException::withMessages([
                        'motivo_cambio' => 'Solo puede modificar los campos autorizados: '.implode(', ', $autorizados).'.',
                    ]);
                }
            }

            if ($esPrimera) {
                $payload['actualizado_clinicamente_en'] = now();
                $payload['actualizado_clinicamente_por_usuario_id'] = $usuario->id;
            }

            $expediente->auditMotivoCambio = $motivoCambio;
            $expediente->update($payload);

            $expediente->paciente->update(['ultima_accion' => 'Actualización de expediente clínico']);

            if ($permiso) {
                $permiso->consumir();
            }

            return $expediente->refresh();
        });
    }
}
