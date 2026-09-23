<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContactoPaciente;
use App\Models\Especialista;
use App\Models\Paciente;
use App\Models\PacienteCorreccionAuditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PacienteDatosCorreccionService
{
    /** @var list<string> */
    private const CAMPOS_CIFRADOS = [
        'nombre_completo',
        'direccion',
        'fecha_nacimiento',
        'profesion_ocupacion',
        'referido_por',
        'llevado_por',
    ];

    /** @var list<string> */
    private const CAMPOS_PACIENTE = [
        'carnet',
        'nombre_completo',
        'direccion',
        'fecha_nacimiento',
        'sexo',
        'estado_civil',
        'carrera_id',
        'profesion_ocupacion',
        'referido_por',
        'llevado_por',
    ];

    /**
     * @param  array<string, mixed>  $validated
     */
    public function corregir(Paciente $paciente, Especialista $usuario, array $validated, ?string $ip = null): Paciente
    {
        if (! $usuario->can('update', $paciente)) {
            abort(403);
        }

        $profesional = $usuario->profesional;
        abort_unless($profesional, 403);

        $motivo = trim((string) $validated['motivo_correccion']);
        unset($validated['motivo_correccion']);

        return DB::transaction(function () use ($paciente, $usuario, $profesional, $validated, $motivo, $ip) {
            $paciente = Paciente::query()->whereKey($paciente->codigo)->lockForUpdate()->firstOrFail();

            // Revalidar autorización dentro de la transacción
            if (
                ! $usuario->hasRole('psychosocial_referent')
                || $paciente->creado_por_profesional_id !== $profesional->id
            ) {
                abort(403);
            }

            $codigoOriginal = $paciente->codigo;
            $carnetAnterior = $paciente->carnet;

            $cambiosPaciente = $this->diffPaciente($paciente, $validated);
            $cambiosContactos = $this->diffYAplicarContactos($paciente, $validated);

            $todosLosCambios = array_merge($cambiosPaciente, $cambiosContactos);

            if ($todosLosCambios === []) {
                throw ValidationException::withMessages([
                    'motivo_correccion' => 'No se detectaron cambios en los datos. Corrija al menos un campo o cancele la operación.',
                ]);
            }

            $carnetCambio = array_key_exists('carnet', $cambiosPaciente);
            $carnetNuevo = $carnetCambio
                ? (string) $cambiosPaciente['carnet']['nuevo']
                : $carnetAnterior;

            if ($cambiosPaciente !== []) {
                $payload = [];
                foreach ($cambiosPaciente as $campo => $diff) {
                    $payload[$campo] = $diff['nuevo'];
                }
                $payload['ultima_accion'] = 'Corrección de datos generales';
                $paciente->update($payload);
            } else {
                $paciente->update(['ultima_accion' => 'Corrección de datos generales']);
            }

            $camposModificados = array_keys($todosLosCambios);
            $valoresAnteriores = [];
            $valoresNuevos = [];
            foreach ($todosLosCambios as $campo => $diff) {
                $valoresAnteriores[$campo] = $this->valorAuditoria($campo, $diff['anterior']);
                $valoresNuevos[$campo] = $this->valorAuditoria($campo, $diff['nuevo']);
            }

            $rol = 'psychosocial_referent';
            foreach ($usuario->roles as $role) {
                if ($role->slug === 'psychosocial_referent') {
                    $rol = $role->slug;
                    break;
                }
            }

            PacienteCorreccionAuditoria::create([
                'paciente_id' => $codigoOriginal,
                'carnet_anterior' => $carnetAnterior,
                'carnet_nuevo' => $carnetNuevo,
                'usuario_id' => $usuario->id,
                'profesional_id' => $profesional->id,
                'rol_usuario' => $rol,
                'motivo' => $motivo,
                'campos_modificados' => $camposModificados,
                'valores_anteriores' => $valoresAnteriores,
                'valores_nuevos' => $valoresNuevos,
                'ip_address' => $ip,
                'tipo_evento' => $carnetCambio
                    ? PacienteCorreccionAuditoria::TIPO_CORRECCION_CARNET
                    : PacienteCorreccionAuditoria::TIPO_ACTUALIZACION,
                'nivel_evento' => $carnetCambio
                    ? PacienteCorreccionAuditoria::NIVEL_SENSIBLE
                    : PacienteCorreccionAuditoria::NIVEL_NORMAL,
                'aviso_sensible' => $carnetCambio,
            ]);

            $paciente->refresh();

            // Garantizar integridad del UUID
            if ($paciente->codigo !== $codigoOriginal) {
                throw new \RuntimeException('El UUID del paciente no debe cambiar durante la corrección.');
            }

            return $paciente;
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, array{anterior: mixed, nuevo: mixed}>
     */
    private function diffPaciente(Paciente $paciente, array $validated): array
    {
        $cambios = [];

        foreach (self::CAMPOS_PACIENTE as $campo) {
            if (! array_key_exists($campo, $validated)) {
                continue;
            }

            $nuevo = $validated[$campo];
            $anterior = $paciente->{$campo};

            if ($campo === 'carnet') {
                $nuevo = strtoupper((string) $nuevo);
                $anterior = strtoupper((string) $anterior);
            }

            if ($campo === 'fecha_nacimiento') {
                $nuevo = $nuevo ? substr((string) $nuevo, 0, 10) : null;
                $anterior = $anterior ? substr((string) $anterior, 0, 10) : null;
            }

            if ($campo === 'carrera_id') {
                $nuevo = (string) $nuevo;
                $anterior = (string) $anterior;
            }

            $nuevoNorm = $this->normalizarComparable($nuevo);
            $anteriorNorm = $this->normalizarComparable($anterior);

            if ($nuevoNorm !== $anteriorNorm) {
                $cambios[$campo] = [
                    'anterior' => $anterior,
                    'nuevo' => $campo === 'carnet' ? strtoupper((string) $nuevo) : $nuevo,
                ];
            }
        }

        return $cambios;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, array{anterior: mixed, nuevo: mixed}>
     */
    private function diffYAplicarContactos(Paciente $paciente, array $validated): array
    {
        $cambios = [];
        $contactos = $paciente->contactos()->get()->keyBy('parentesco');

        $padreNombre = $validated['padre_nombre'] ?? null;
        $padreTelefono = $validated['padre_telefono'] ?? null;
        $madreNombre = $validated['madre_nombre'] ?? null;
        $madreTelefono = $validated['madre_telefono'] ?? null;
        $parentescoResp = $validated['responsable_parentesco'];
        $respNombre = $validated['responsable_nombre'] ?? null;
        $respTelefono = $validated['responsable_telefono'];
        $respDireccion = $validated['responsable_direccion'];

        // Padre
        if (! empty($padreNombre) || $parentescoResp === 'Padre') {
            $esResp = $parentescoResp === 'Padre';
            $data = [
                'nombre_completo' => $padreNombre ?: ($esResp ? $respNombre : null),
                'telefono_personal' => $esResp ? $respTelefono : ($padreTelefono ?: '00000000'),
                'direccion' => $esResp ? $respDireccion : null,
                'es_responsable' => $esResp,
                'parentesco' => 'Padre',
            ];
            if (! empty($data['nombre_completo'])) {
                $cambios = array_merge($cambios, $this->upsertContacto($paciente, $contactos->get('Padre'), $data, 'padre'));
            }
        }

        // Madre
        if (! empty($madreNombre) || $parentescoResp === 'Madre') {
            $esResp = $parentescoResp === 'Madre';
            $data = [
                'nombre_completo' => $madreNombre ?: ($esResp ? $respNombre : null),
                'telefono_personal' => $esResp ? $respTelefono : ($madreTelefono ?: '00000000'),
                'direccion' => $esResp ? $respDireccion : null,
                'es_responsable' => $esResp,
                'parentesco' => 'Madre',
            ];
            if (! empty($data['nombre_completo'])) {
                $cambios = array_merge($cambios, $this->upsertContacto($paciente, $contactos->get('Madre'), $data, 'madre'));
            }
        }

        // Otro responsable
        if ($parentescoResp === 'Otro') {
            $data = [
                'nombre_completo' => $respNombre,
                'telefono_personal' => $respTelefono,
                'direccion' => $respDireccion,
                'es_responsable' => true,
                'parentesco' => 'Otro',
            ];
            $cambios = array_merge($cambios, $this->upsertContacto($paciente, $contactos->get('Otro'), $data, 'otro'));
        }

        // Asegurar un único responsable activo
        $paciente->contactos()->get()->each(function (ContactoPaciente $c) use ($parentescoResp) {
            $debeSer = $c->parentesco === $parentescoResp;
            if ((bool) $c->es_responsable !== $debeSer) {
                $c->update(['es_responsable' => $debeSer]);
            }
        });

        return $cambios;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array{anterior: mixed, nuevo: mixed}>
     */
    private function upsertContacto(Paciente $paciente, ?ContactoPaciente $contacto, array $data, string $prefijo): array
    {
        $cambios = [];
        $campos = ['nombre_completo', 'telefono_personal', 'direccion', 'es_responsable'];

        if (! $contacto) {
            ContactoPaciente::create([
                'paciente_id' => $paciente->codigo,
                'nombre_completo' => $data['nombre_completo'],
                'parentesco' => $data['parentesco'],
                'telefono_personal' => $data['telefono_personal'],
                'direccion' => $data['direccion'],
                'es_responsable' => $data['es_responsable'],
            ]);

            foreach ($campos as $campo) {
                $cambios["contacto_{$prefijo}.{$campo}"] = [
                    'anterior' => null,
                    'nuevo' => $data[$campo],
                ];
            }

            return $cambios;
        }

        foreach ($campos as $campo) {
            $anterior = $contacto->{$campo};
            $nuevo = $data[$campo];
            if ($campo === 'es_responsable') {
                $anterior = (bool) $anterior;
                $nuevo = (bool) $nuevo;
            }
            if ($this->normalizarComparable($anterior) !== $this->normalizarComparable($nuevo)) {
                $cambios["contacto_{$prefijo}.{$campo}"] = [
                    'anterior' => $anterior,
                    'nuevo' => $nuevo,
                ];
            }
        }

        if ($cambios !== []) {
            $contacto->update([
                'nombre_completo' => $data['nombre_completo'],
                'telefono_personal' => $data['telefono_personal'],
                'direccion' => $data['direccion'],
                'es_responsable' => $data['es_responsable'],
            ]);
        }

        return $cambios;
    }

    private function valorAuditoria(string $campo, mixed $valor): mixed
    {
        $base = str_contains($campo, '.') ? explode('.', $campo)[1] : $campo;

        // Carnet y datos personales: nunca quedan en texto plano en la auditoría.
        if ($base === 'carnet'
            || in_array($base, self::CAMPOS_CIFRADOS, true)
            || in_array($base, ['telefono_personal', 'telefono_casa', 'direccion', 'nombre_completo'], true)
        ) {
            if ($valor === null || $valor === '') {
                return null;
            }

            return '[CIFRADO]';
        }

        return $valor;
    }

    private function normalizarComparable(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }
        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }

        return trim((string) $valor);
    }
}
