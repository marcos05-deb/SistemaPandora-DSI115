<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AlertaPreventiva;
use App\Models\Especialista;
use App\Models\SeguimientoPreventivo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AlertaSeguimientoService
{
    /** @param array{valoracion: string, accion_preventiva: string, responsable_profesional_id: string, proxima_revision: string} $validated */
    public function registrarSeguimiento(
        AlertaPreventiva $alerta,
        Especialista $user,
        array $validated,
    ): SeguimientoPreventivo {
        return DB::transaction(function () use ($alerta, $user, $validated): SeguimientoPreventivo {
            $alerta = $this->lockAlert($alerta);

            if (! in_array($alerta->estado, AlertaPreventiva::ESTADOS_ACTIVOS, true)) {
                throw ValidationException::withMessages([
                    'estado' => 'No se puede registrar seguimiento sobre una alerta cerrada o descartada.',
                ]);
            }

            $profesional = $user->profesionalParaArea($alerta->area_id);
            if ($profesional === null) {
                throw ValidationException::withMessages([
                    'responsable_profesional_id' => 'No se encontró un perfil profesional activo en el área de la alerta.',
                ]);
            }

            $seguimiento = new SeguimientoPreventivo;
            $seguimiento->area_id = $alerta->area_id;
            $seguimiento->alerta_preventiva_id = $alerta->getKey();
            $seguimiento->responsable_profesional_id = $profesional->getKey();
            $seguimiento->registrado_por_profesional_id = $profesional->getKey();
            $seguimiento->valoracion = trim($validated['valoracion']);
            $seguimiento->accion_preventiva = trim($validated['accion_preventiva']);
            $seguimiento->proxima_revision = $validated['proxima_revision'];
            $seguimiento->save();

            if ($alerta->estado === 'activa') {
                $alerta->estado = 'en_seguimiento';
                $alerta->save();
            }

            return $seguimiento;
        });
    }

    /** @param array{resultado: string} $validated */
    public function cerrar(AlertaPreventiva $alerta, Especialista $user, array $validated): AlertaPreventiva
    {
        return DB::transaction(function () use ($alerta, $user, $validated): AlertaPreventiva {
            $alerta = $this->lockAlert($alerta);

            if (! in_array($alerta->estado, AlertaPreventiva::ESTADOS_ACTIVOS, true)) {
                throw ValidationException::withMessages([
                    'resultado' => 'Solo se pueden cerrar alertas activas o en seguimiento.',
                ]);
            }

            $profesional = $user->profesionalParaArea($alerta->area_id);
            if ($profesional === null) {
                throw ValidationException::withMessages([
                    'resultado' => 'No se encontró un perfil profesional activo en el área de la alerta.',
                ]);
            }

            $alerta->resultado_cierre = trim($validated['resultado']);
            $alerta->estado = 'cerrada';
            $alerta->cerrada_en = now();
            $alerta->cerrada_por_profesional_id = $profesional->getKey();
            $alerta->save();

            return $alerta;
        });
    }

    /** @param array{justificacion: string} $validated */
    public function descartar(AlertaPreventiva $alerta, Especialista $user, array $validated): AlertaPreventiva
    {
        return DB::transaction(function () use ($alerta, $user, $validated): AlertaPreventiva {
            $alerta = $this->lockAlert($alerta);

            if (! in_array($alerta->estado, AlertaPreventiva::ESTADOS_ACTIVOS, true)) {
                throw ValidationException::withMessages([
                    'justificacion' => 'Solo se pueden descartar alertas activas o en seguimiento.',
                ]);
            }

            $profesional = $user->profesionalParaArea($alerta->area_id);
            if ($profesional === null) {
                throw ValidationException::withMessages([
                    'justificacion' => 'No se encontró un perfil profesional activo en el área de la alerta.',
                ]);
            }

            $alerta->justificacion_descarte = trim($validated['justificacion']);
            $alerta->estado = 'descartada';
            $alerta->descartada_en = now();
            $alerta->descartada_por_profesional_id = $profesional->getKey();
            $alerta->save();

            return $alerta;
        });
    }

    private function lockAlert(AlertaPreventiva $alerta): AlertaPreventiva
    {
        return AlertaPreventiva::query()
            ->whereKey($alerta->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
