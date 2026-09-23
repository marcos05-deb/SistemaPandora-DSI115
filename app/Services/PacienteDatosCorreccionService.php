<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContactoPaciente;
use App\Models\Especialista;
use App\Models\Paciente;
use App\Models\PacienteCorreccionAuditoria;
use App\Models\SolicitudCorreccionExpediente;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PacienteDatosCorreccionService
{
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

    public function __construct(
        private readonly SolicitudCorreccionExpedienteService $solicitudes
    ) {}

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

            if (
                ! $usuario->hasRole('psychosocial_referent')
                || $paciente->creado_por_profesional_id !== $profesional->id
            ) {
                abort(403);
            }

            $permiso = null;
            $esPrimera = $paciente->datos_corregidos_en === null;

            if (! $esPrimera) {
                $permiso = $this->solicitudes->permisoDatosVigente($paciente);
                if (! $permiso) {
                    throw ValidationException::withMessages([
                        'motivo_correccion' => 'Este paciente ya fue corregido una vez. Solicite permiso al administrador para una nueva corrección.',
                    ]);
                }
            }

            $codigoOriginal = $paciente->codigo;

            $cambiosPaciente = $this->diffPaciente($paciente, $validated);
            $cambiosContactos = $this->diffYAplicarContactos($paciente, $validated, aplicar: true);

            $todosLosCambios = array_merge($cambiosPaciente, $cambiosContactos);

            if ($todosLosCambios === []) {
                throw ValidationException::withMessages([
                    'motivo_correccion' => 'No se detectaron cambios en los datos. Corrija al menos un campo o cancele la operación.',
                ]);
            }

            if ($permiso) {
                $this->asegurarCamposDentroDePermiso($todosLosCambios, $permiso);
            }

            $carnetCambio = array_key_exists('carnet', $cambiosPaciente);

            if ($cambiosPaciente !== []) {
                $payload = [];
                foreach ($cambiosPaciente as $campo => $diff) {
                    $payload[$campo] = $diff['nuevo'];
                }
                $payload['ultima_accion'] = 'Corrección de datos generales';
                if ($esPrimera) {
                    $payload['datos_corregidos_en'] = now();
                    $payload['datos_corregidos_por_usuario_id'] = $usuario->id;
                }
                $paciente->update($payload);
            } else {
                $payload = ['ultima_accion' => 'Corrección de datos generales'];
                if ($esPrimera) {
                    $payload['datos_corregidos_en'] = now();
                    $payload['datos_corregidos_por_usuario_id'] = $usuario->id;
                }
                $paciente->update($payload);
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
                'carnet_anterior' => '[CIFRADO]',
                'carnet_nuevo' => '[CIFRADO]',
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

            if ($permiso) {
                $permiso->consumir();
            }

            $paciente->refresh();

            if ($paciente->codigo !== $codigoOriginal) {
                throw new \RuntimeException('El UUID del paciente no debe cambiar durante la corrección.');
            }

            return $paciente;
        });
    }

    /**
     * @param  array<string, array{anterior: mixed, nuevo: mixed}>  $cambios
     */
    private function asegurarCamposDentroDePermiso(array $cambios, SolicitudCorreccionExpediente $permiso): void
    {
        $autorizados = $permiso->camposAutorizados();
        $modificados = array_keys($cambios);

        // Mapear claves de contacto (contacto_padre.nombre_completo) a campos de solicitud
        $mapeo = [
            'contacto_padre.nombre_completo' => 'padre_nombre',
            'contacto_padre.telefono_personal' => 'padre_telefono',
            'contacto_madre.nombre_completo' => 'madre_nombre',
            'contacto_madre.telefono_personal' => 'madre_telefono',
            'contacto_otro.nombre_completo' => 'responsable_nombre',
            'contacto_otro.telefono_personal' => 'responsable_telefono',
            'contacto_otro.direccion' => 'responsable_direccion',
            'contacto_padre.direccion' => 'responsable_direccion',
            'contacto_madre.direccion' => 'responsable_direccion',
            'contacto_padre.es_responsable' => 'responsable_parentesco',
            'contacto_madre.es_responsable' => 'responsable_parentesco',
            'contacto_otro.es_responsable' => 'responsable_parentesco',
        ];

        $fuera = [];
        foreach ($modificados as $campo) {
            $clave = $mapeo[$campo] ?? $campo;
            if (! in_array($clave, $autorizados, true) && ! in_array($campo, $autorizados, true)) {
                $fuera[] = $campo;
            }
        }

        if ($fuera !== []) {
            throw ValidationException::withMessages([
                'motivo_correccion' => 'Solo puede modificar los campos autorizados por el administrador: '.implode(', ', $autorizados).'.',
            ]);
        }
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
    private function diffYAplicarContactos(Paciente $paciente, array $validated, bool $aplicar = true): array
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
                $cambios = array_merge($cambios, $this->upsertContacto($paciente, $contactos->get('Padre'), $data, 'padre', $aplicar));
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
                $cambios = array_merge($cambios, $this->upsertContacto($paciente, $contactos->get('Madre'), $data, 'madre', $aplicar));
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
            $cambios = array_merge($cambios, $this->upsertContacto($paciente, $contactos->get('Otro'), $data, 'otro', $aplicar));
        }

        if ($aplicar) {
            // Asegurar un único responsable activo
            $paciente->contactos()->get()->each(function (ContactoPaciente $c) use ($parentescoResp) {
                $debeSer = $c->parentesco === $parentescoResp;
                if ((bool) $c->es_responsable !== $debeSer) {
                    $c->update(['es_responsable' => $debeSer]);
                }
            });
        }

        return $cambios;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array{anterior: mixed, nuevo: mixed}>
     */
    private function upsertContacto(Paciente $paciente, ?ContactoPaciente $contacto, array $data, string $prefijo, bool $aplicar = true): array
    {
        $cambios = [];
        $campos = ['nombre_completo', 'telefono_personal', 'direccion', 'es_responsable'];

        if (! $contacto) {
            if ($aplicar) {
                ContactoPaciente::create([
                    'paciente_id' => $paciente->codigo,
                    'nombre_completo' => $data['nombre_completo'],
                    'parentesco' => $data['parentesco'],
                    'telefono_personal' => $data['telefono_personal'],
                    'direccion' => $data['direccion'],
                    'es_responsable' => $data['es_responsable'],
                ]);
            }

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

        if ($aplicar && $cambios !== []) {
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
        if ($valor === null || $valor === '') {
            return null;
        }

        // Privacidad: la auditoría nunca guarda valores en claro (ni carnet, ni nombre, ni demográficos).
        return '[CIFRADO]';
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
