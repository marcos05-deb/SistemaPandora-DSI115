<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Especialista;
use App\Models\Expediente;
use App\Models\Paciente;
use App\Models\SolicitudCorreccionExpediente;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SolicitudCorreccionExpedienteService
{
    /**
     * @param  list<string>  $campos
     */
    public function solicitarDatos(
        Paciente $paciente,
        Especialista $solicitante,
        array $campos,
        string $motivo
    ): SolicitudCorreccionExpediente {
        if (! $this->puedeSolicitarDatos($solicitante, $paciente)) {
            abort(403);
        }

        if (! $paciente->datos_corregidos_en) {
            throw ValidationException::withMessages([
                'motivo' => 'Aún no ha usado la corrección libre. No necesita solicitar permiso.',
            ]);
        }

        if ($this->permisoDatosVigente($paciente)) {
            throw ValidationException::withMessages([
                'motivo' => 'Ya existe un permiso aprobado vigente. Úselo antes de solicitar otro.',
            ]);
        }

        if ($this->solicitudPendienteDatos($paciente)) {
            throw ValidationException::withMessages([
                'motivo' => 'Ya hay una solicitud pendiente de autorización para este paciente.',
            ]);
        }

        $campos = $this->validarCampos($campos, SolicitudCorreccionExpediente::CAMPOS_DATOS);

        return SolicitudCorreccionExpediente::create([
            'paciente_id' => $paciente->codigo,
            'expediente_id' => null,
            'tipo' => SolicitudCorreccionExpediente::TIPO_DATOS,
            'solicitante_usuario_id' => $solicitante->id,
            'campos_solicitados' => $campos,
            'motivo' => trim($motivo),
            'estado' => SolicitudCorreccionExpediente::ESTADO_PENDIENTE,
        ]);
    }

    /**
     * @param  list<string>  $campos
     */
    public function solicitarClinico(
        Expediente $expediente,
        Especialista $solicitante,
        array $campos,
        string $motivo
    ): SolicitudCorreccionExpediente {
        if (! $this->puedeSolicitarClinico($solicitante, $expediente)) {
            abort(403);
        }

        if (! $expediente->actualizado_clinicamente_en) {
            throw ValidationException::withMessages([
                'motivo' => 'Aún no ha usado la actualización clínica libre. No necesita solicitar permiso.',
            ]);
        }

        if ($this->permisoClinicoVigente($expediente)) {
            throw ValidationException::withMessages([
                'motivo' => 'Ya existe un permiso aprobado vigente. Úselo antes de solicitar otro.',
            ]);
        }

        if ($this->solicitudPendienteClinico($expediente)) {
            throw ValidationException::withMessages([
                'motivo' => 'Ya hay una solicitud pendiente de autorización para este expediente.',
            ]);
        }

        $campos = $this->validarCampos($campos, SolicitudCorreccionExpediente::CAMPOS_CLINICO);

        return SolicitudCorreccionExpediente::create([
            'paciente_id' => $expediente->paciente_id,
            'expediente_id' => $expediente->id,
            'tipo' => SolicitudCorreccionExpediente::TIPO_CLINICO,
            'solicitante_usuario_id' => $solicitante->id,
            'campos_solicitados' => $campos,
            'motivo' => trim($motivo),
            'estado' => SolicitudCorreccionExpediente::ESTADO_PENDIENTE,
        ]);
    }

    public function aprobar(SolicitudCorreccionExpediente $solicitud, Especialista $admin, ?string $nota = null): void
    {
        abort_unless($admin->hasRole('sysadmin'), 403);

        DB::transaction(function () use ($solicitud, $admin, $nota) {
            $solicitud = SolicitudCorreccionExpediente::query()
                ->whereKey($solicitud->id)
                ->lockForUpdate()
                ->firstOrFail();

            $solicitud->aprobar($admin, $nota);
        });
    }

    public function rechazar(SolicitudCorreccionExpediente $solicitud, Especialista $admin, ?string $nota = null): void
    {
        abort_unless($admin->hasRole('sysadmin'), 403);

        DB::transaction(function () use ($solicitud, $admin, $nota) {
            $solicitud = SolicitudCorreccionExpediente::query()
                ->whereKey($solicitud->id)
                ->lockForUpdate()
                ->firstOrFail();

            $solicitud->rechazar($admin, $nota);
        });
    }

    public function permisoDatosVigente(Paciente $paciente): ?SolicitudCorreccionExpediente
    {
        return SolicitudCorreccionExpediente::query()
            ->where('paciente_id', $paciente->codigo)
            ->where('tipo', SolicitudCorreccionExpediente::TIPO_DATOS)
            ->where('estado', SolicitudCorreccionExpediente::ESTADO_APROBADA)
            ->orderByDesc('revisado_en')
            ->first();
    }

    public function permisoClinicoVigente(Expediente $expediente): ?SolicitudCorreccionExpediente
    {
        return SolicitudCorreccionExpediente::query()
            ->where('expediente_id', $expediente->id)
            ->where('tipo', SolicitudCorreccionExpediente::TIPO_CLINICO)
            ->where('estado', SolicitudCorreccionExpediente::ESTADO_APROBADA)
            ->orderByDesc('revisado_en')
            ->first();
    }

    public function solicitudPendienteDatos(Paciente $paciente): ?SolicitudCorreccionExpediente
    {
        return SolicitudCorreccionExpediente::query()
            ->where('paciente_id', $paciente->codigo)
            ->where('tipo', SolicitudCorreccionExpediente::TIPO_DATOS)
            ->where('estado', SolicitudCorreccionExpediente::ESTADO_PENDIENTE)
            ->first();
    }

    public function solicitudPendienteClinico(Expediente $expediente): ?SolicitudCorreccionExpediente
    {
        return SolicitudCorreccionExpediente::query()
            ->where('expediente_id', $expediente->id)
            ->where('tipo', SolicitudCorreccionExpediente::TIPO_CLINICO)
            ->where('estado', SolicitudCorreccionExpediente::ESTADO_PENDIENTE)
            ->first();
    }

    public function puedeCorregirDatos(Paciente $paciente, Especialista $usuario): bool
    {
        if (! $usuario->hasRole('psychosocial_referent') || ! $usuario->profesional) {
            return false;
        }

        if ($paciente->creado_por_profesional_id !== $usuario->profesional->id) {
            return false;
        }

        if (! $paciente->datos_corregidos_en) {
            return true;
        }

        return $this->permisoDatosVigente($paciente) !== null;
    }

    public function puedeActualizarClinico(Expediente $expediente, Especialista $usuario): bool
    {
        if ($expediente->estado === Expediente::ESTADO_CERRADO) {
            return false;
        }

        // Base policy role/area check is done by caller; here only free-or-permiso
        if (! $expediente->actualizado_clinicamente_en) {
            return true;
        }

        return $this->permisoClinicoVigente($expediente) !== null;
    }

    /**
     * @return array{puede_editar: bool, necesita_permiso: bool, permiso_aprobado: ?array, solicitud_pendiente: ?array, ya_corregido: bool, corregido_por: ?string, corregido_en: ?string}
     */
    public function estadoDatosParaUi(Paciente $paciente, Especialista $usuario): array
    {
        $puedeBase = $usuario->hasRole('psychosocial_referent')
            && $usuario->profesional
            && $paciente->creado_por_profesional_id === $usuario->profesional->id;

        $permiso = $this->permisoDatosVigente($paciente);
        $pendiente = $this->solicitudPendienteDatos($paciente);
        $yaCorregido = (bool) $paciente->datos_corregidos_en;

        return [
            'puede_editar' => $puedeBase && (! $yaCorregido || $permiso !== null),
            'necesita_permiso' => $puedeBase && $yaCorregido && $permiso === null && $pendiente === null,
            'permiso_aprobado' => $permiso ? $this->mapSolicitudResumen($permiso) : null,
            'solicitud_pendiente' => $pendiente ? $this->mapSolicitudResumen($pendiente) : null,
            'ya_corregido' => $yaCorregido,
            'corregido_por' => $paciente->datosCorregidosPor?->name,
            'corregido_en' => $paciente->datos_corregidos_en?->format('Y-m-d H:i:s'),
            'campos_autorizados' => $permiso?->camposAutorizados() ?? [],
        ];
    }

    /**
     * @return array{puede_editar: bool, necesita_permiso: bool, permiso_aprobado: ?array, solicitud_pendiente: ?array, ya_actualizado: bool, actualizado_por: ?string, actualizado_en: ?string, campos_autorizados: list<string>}
     */
    public function estadoClinicoParaUi(Expediente $expediente, Especialista $usuario): array
    {
        $puedeBase = $usuario->can('close', $expediente); // same role/area as update
        $permiso = $this->permisoClinicoVigente($expediente);
        $pendiente = $this->solicitudPendienteClinico($expediente);
        $ya = (bool) $expediente->actualizado_clinicamente_en;

        return [
            'puede_editar' => $puedeBase && (! $ya || $permiso !== null),
            'necesita_permiso' => $puedeBase && $ya && $permiso === null && $pendiente === null,
            'permiso_aprobado' => $permiso ? $this->mapSolicitudResumen($permiso) : null,
            'solicitud_pendiente' => $pendiente ? $this->mapSolicitudResumen($pendiente) : null,
            'ya_actualizado' => $ya,
            'actualizado_por' => $expediente->actualizadoClinicamentePor?->name,
            'actualizado_en' => $expediente->actualizado_clinicamente_en?->format('Y-m-d H:i:s'),
            'campos_autorizados' => $permiso?->camposAutorizados() ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapSolicitudResumen(SolicitudCorreccionExpediente $s): array
    {
        return [
            'id' => $s->id,
            'tipo' => $s->tipo,
            'paciente_id' => $s->paciente_id,
            'expediente_id' => $s->expediente_id,
            'campos_solicitados' => $s->camposAutorizados(),
            'motivo' => $s->motivo,
            'estado' => $s->estado,
            'solicitante' => $s->solicitante?->name,
            'nota_admin' => $s->nota_admin,
            'revisado_en' => $s->revisado_en?->format('Y-m-d H:i:s'),
            'created_at' => $s->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function puedeSolicitarDatos(Especialista $usuario, Paciente $paciente): bool
    {
        return $usuario->hasRole('psychosocial_referent')
            && $usuario->profesional
            && $paciente->creado_por_profesional_id === $usuario->profesional->id;
    }

    private function puedeSolicitarClinico(Especialista $usuario, Expediente $expediente): bool
    {
        return $usuario->can('close', $expediente);
    }

    /**
     * @param  list<string>  $campos
     * @param  list<string>  $permitidos
     * @return list<string>
     */
    private function validarCampos(array $campos, array $permitidos): array
    {
        $campos = array_values(array_unique(array_filter($campos, fn ($c) => is_string($c) && $c !== '')));

        if ($campos === []) {
            throw ValidationException::withMessages([
                'campos' => 'Debe indicar al menos un campo a corregir.',
            ]);
        }

        $invalidos = array_diff($campos, $permitidos);
        if ($invalidos !== []) {
            throw ValidationException::withMessages([
                'campos' => 'Campos no permitidos: '.implode(', ', $invalidos),
            ]);
        }

        return $campos;
    }
}
