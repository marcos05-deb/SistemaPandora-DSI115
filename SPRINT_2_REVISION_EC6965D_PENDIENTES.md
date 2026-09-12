# Sprint 2 — Revisión posterior al pull y pendientes reales

## Datos de la revisión

- Fecha: 12 de septiembre de 2026
- Rama revisada: `correcciones-sprint-2`
- Commit revisado: `ec6965d`
- Comparación funcional: PAN-14 a PAN-21 en Jira
- Estado Git: sincronizado con `origin/correcciones-sprint-2`
- Pruebas automáticas: 147 aprobadas, 633 aserciones, 0 fallos y 0 incompletas
- Compilación de interfaz: exitosa
- Migraciones: aplicadas correctamente en el entorno local
- Sintaxis PHP: sin errores

## Conclusión ejecutiva

El Sprint 2 está muy cerca de estar completo, pero no debe declararse terminado todavía si se exige cumplimiento literal de Jira y robustez de seguridad.

Los cambios nuevos solucionaron correctamente:

- Uso obligatorio de la consulta activa para crear la próxima cita.
- Registro de consultas por especialistas multiárea.
- Incorporación de ausencias a una proyección estadística.
- Eliminación de pruebas incompletas de auditoría.
- Restricción PostgreSQL contra citas solapadas.
- Vista semanal con más de 30 citas.

Todavía quedan pendientes funcionales, de integridad y seguridad. Este documento los expone todos, incluyendo riesgos que no hacen fallar la suite actual.

## Resumen de pendientes

| ID | Prioridad | Historia | Pendiente |
|---|---:|---|---|
| RP-01 | Crítica | PAN-18 / HU-11 | Falta confirmar que la fecha de la cita inicial fue acordada con el paciente |
| RP-02 | Crítica | PAN-19 y PAN-20 | Las rutas anidadas no comprueban que la cita pertenezca al expediente de la URL |
| RP-03 | Alta | PAN-20 / HU-13 | El coordinador multiárea solo puede reprogramar/cancelar en su área principal |
| RP-04 | Alta | PAN-17 / HU-10 | La auditoría no es inmutable a nivel de base de datos |
| RP-05 | Alta | PAN-19 / HU-12 | Hay estadísticas de ausencias, pero no existe todavía una alerta preventiva consumible o demostrable |
| RP-06 | Media | Auditoría clínica transversal | La consulta del historial y la lectura de varios expedientes no quedan auditadas completamente |
| RP-07 | Media | PAN-18 y PAN-20 | La prueba llamada “concurrente” ejecuta solicitudes secuenciales |
| RP-08 | Media | Despliegue | La migración de exclusión puede fallar si ya existen citas programadas solapadas |
| RP-09 | Baja | PAN-21 / HU-14 | La vista semanal carga todos los registros en memoria sin límite |
| RP-10 | Gestión | Sprint 2 | Jira continúa mostrando historias y subtareas como `Por hacer` |

---

## RP-01 — Confirmación de cita acordada con el paciente

### Evidencia de Jira

PAN-18 / HU-11, CA-2 exige que el profesional registre una fecha y hora futura **acordada con el paciente**.

### Situación actual

La creación inicial de la cita solicita:

- `consulta_id`
- `fecha_hora`
- `motivo`

No existe un campo de confirmación en el modal ni una validación equivalente en el servidor. En la reprogramación sí existe `acordada_con_paciente`, por lo que ambos flujos son inconsistentes.

### Archivos involucrados

- `resources/js/Components/Expediente/AgendarCitaModal.vue`
- `app/Http/Requests/CitaStoreRequest.php`
- `app/Http/Controllers/CitaController.php`
- `tests/Feature/Cita/AsignarProximaCitaTest.php`

### Implementación recomendada

1. Agregar al formulario:

```js
acordada_con_paciente: false,
```

2. Mostrar una casilla obligatoria:

```vue
<input
    id="acordada_con_paciente"
    v-model="form.acordada_con_paciente"
    type="checkbox"
    required
>
<label for="acordada_con_paciente">
    Confirmo que la fecha y hora fueron acordadas con el paciente
</label>
```

3. Validar en `CitaStoreRequest`:

```php
'acordada_con_paciente' => ['accepted'],
```

4. Agregar mensaje específico:

```php
'acordada_con_paciente.accepted' =>
    'Debe confirmar que la fecha y hora fueron acordadas con el paciente.',
```

No es indispensable guardar el booleano si solo funciona como confirmación del acto de creación; la auditoría de la cita debe conservar autor y fecha. Si se requiere evidencia permanente, agregar una columna booleana con valor predeterminado `false`.

### Pruebas requeridas

- Rechaza una cita sin la confirmación.
- Rechaza `false`, `0` o un valor no aceptado.
- Acepta la cita con la confirmación verdadera.
- Conserva todos los controles de consulta activa, fecha futura y conflictos.

### Criterio de terminado

El usuario no puede crear una cita inicial sin confirmar que la fecha y hora fueron acordadas con el paciente, ni desde la interfaz ni mediante una petición directa.

---

## RP-02 — Comprobar relación entre cita y expediente en rutas anidadas

### Historias afectadas

- PAN-19 / HU-12 — Registrar asistencia o ausencia.
- PAN-20 / HU-13 — Reprogramar o cancelar una cita.

### Situación actual

Las rutas reciben simultáneamente `{expediente}` y `{cita}`, pero no usan `scopeBindings()` ni realizan una comparación explícita entre:

```php
$cita->expediente_id
```

y:

```php
$expediente->id
```

En la reprogramación el problema es especialmente importante: la nueva cita usa el expediente recibido en la URL, pero conserva la consulta de origen de la cita seleccionada. Una petición manipulada podría mezclar una cita de un expediente con otro expediente autorizado de la misma área.

### Archivos involucrados

- `routes/web.php`
- `app/Http/Controllers/CitaController.php`
- `app/Http/Requests/CitaAsistenciaRequest.php`
- `app/Http/Requests/CitaReprogramarRequest.php`
- `app/Http/Requests/CitaCancelarRequest.php`
- Pruebas de asistencia, reprogramación y cancelación

### Implementación recomendada

Opción recomendada: habilitar enlaces de modelos con alcance en las rutas y definir la relación necesaria.

```php
Route::scopeBindings()->group(function () {
    Route::patch(
        '/expedientes/{expediente}/citas/{cita}/asistencia',
        [CitaController::class, 'actualizarAsistencia']
    );

    Route::patch(
        '/expedientes/{expediente}/citas/{cita}/reprogramar',
        [CitaController::class, 'reprogramar']
    );

    Route::patch(
        '/expedientes/{expediente}/citas/{cita}/cancelar',
        [CitaController::class, 'cancelar']
    );
});
```

Además, aplicar defensa en profundidad en el controlador o Form Request:

```php
if ($cita->expediente_id !== $expediente->id) {
    abort(404);
}
```

En `reprogramar`, la nueva cita debe tomar el expediente desde la cita bloqueada, no desde un parámetro independiente:

```php
$nuevaCita->expediente_id = $citaBloqueada->expediente_id;
```

### Pruebas requeridas

- Expediente A con cita A: la operación normal funciona.
- URL de expediente B con cita A: asistencia responde 404.
- URL de expediente B con cita A: reprogramación responde 404 y no crea registros.
- URL de expediente B con cita A: cancelación responde 404 y no modifica la cita.
- Confirmar que no pueda quedar `consulta_id` de un expediente y `expediente_id` de otro.

### Criterio de terminado

Ninguna operación de cita acepta una combinación inconsistente entre el expediente de la URL y la cita seleccionada.

---

## RP-03 — Coordinador multiárea al reprogramar o cancelar

### Historia afectada

PAN-20 / HU-13.

### Situación actual

`CitaPolicy::reprogramar()` compara el área de la cita con:

```php
$user->profesional->area_id
```

Esto representa solamente el primer perfil o área principal. En cambio, otras partes del sistema ya utilizan `user->areas()` para especialistas y coordinadores multiárea.

### Archivo principal

- `app/Policies/CitaPolicy.php`

### Implementación recomendada

```php
$esCoordinadorAutorizado = $user->hasRole('area_coordinator')
    && $user->areas()
        ->where('areas.id', $cita->area_id)
        ->exists();

return $esEspecialistaAsignado || $esCoordinadorAutorizado;
```

La política de cancelación puede seguir reutilizando la autorización de reprogramación.

### Pruebas requeridas

- Coordinador multiárea puede reprogramar en su primera área.
- Puede reprogramar en su segunda área.
- Puede cancelar en ambas áreas.
- No puede operar sobre una tercera área no autorizada.
- Un especialista continúa limitado a sus propias citas.

### Criterio de terminado

El coordinador puede gestionar citas de cualquiera de sus áreas autorizadas, sin obtener acceso a áreas ajenas.

---

## RP-04 — Inmutabilidad real de auditorías

### Historia afectada

PAN-17 / HU-10 y trazabilidad transversal.

### Situación actual

`AppServiceProvider` impide actualizar o eliminar auditorías cuando se utiliza el modelo `Audit`. Esto hace pasar las pruebas actuales, pero no protege la tabla frente a SQL directo.

Se confirmó que `pandora_app` tiene permisos de `UPDATE` y `DELETE` sobre `audits`. Además, `ExpedienteController` utiliza `DB::table(...)->update(...)` para modificar una auditoría después de crearla.

Por tanto, la auditoría no es técnicamente inmutable; solamente tiene una protección en una capa específica de la aplicación.

### Archivos involucrados

- `app/Providers/AppServiceProvider.php`
- `app/Http/Controllers/ExpedienteController.php`
- `database/migrations/2026_09_01_110725_create_audits_table.php`
- `tests/Feature/Compliance/AuditLoggingTest.php`

### Implementación recomendada

1. Evitar modificar la auditoría después de guardarla.
2. Hacer que `motivo_cambio` forme parte de la auditoría original mediante un resolver, evento de auditoría o metadato preparado antes del `update()` del expediente.
3. Revocar permisos directos:

```sql
REVOKE UPDATE, DELETE ON TABLE audits FROM pandora_app;
```

4. Si el paquete necesita insertar auditorías, conservar únicamente:

```sql
GRANT SELECT, INSERT ON TABLE audits TO pandora_app;
```

5. Opcionalmente agregar un trigger que rechace `UPDATE` y `DELETE`, incluso si otro rol recibe accidentalmente esos permisos.

6. Crear la protección mediante migración y documentar el rol autorizado para mantenimiento administrativo.

### Pruebas requeridas

- `Audit::update()` falla.
- `Audit::delete()` falla.
- `DB::table('audits')->update()` usando `pgsql` falla.
- `DB::table('audits')->delete()` usando `pgsql` falla.
- La creación de auditorías continúa funcionando.
- Actualizar un expediente guarda autor, fecha, motivo, valores anteriores y valores nuevos enmascarados desde el primer `INSERT` de auditoría.

### Criterio de terminado

La cuenta de aplicación puede insertar y consultar auditorías, pero no puede modificarlas ni eliminarlas por Eloquent ni por SQL directo.

---

## RP-05 — Completar el flujo de alertas preventivas

### Historia afectada

PAN-19 / HU-12, CA-3.

### Situación actual

La ausencia ya se incorpora correctamente a:

- `estadisticas_preventivas_ausencias`
- `EstadisticasPreventivasService`

Sin embargo, no existe un componente que convierta esas estadísticas en una alerta preventiva visible o consumible. El criterio de Jira puede interpretarse como cumplido si el alcance termina en alimentar estadísticas; para una demostración estricta de extremo a extremo, todavía falta la alerta.

### Implementación mínima recomendada para el MVP

Definir una regla simple y configurable, por ejemplo:

- Generar alerta cuando un paciente acumule 2 ausencias dentro de 30 días.

Crear un servicio:

```php
final class AlertasPreventivasService
{
    public function requiereAlerta(
        Especialista $especialista,
        string $pacienteId
    ): bool {
        $desde = now()->subDays(30);

        return app(EstadisticasPreventivasService::class)
            ->ausencias($especialista, $pacienteId, $desde)['total'] >= 2;
    }
}
```

La alerta puede exponerse como una propiedad en el detalle del paciente o en un panel clínico. Debe respetar el área autorizada.

### Pruebas requeridas

- Una ausencia no activa la alerta si el umbral es 2.
- Dos ausencias dentro del período activan la alerta.
- Una ausencia antigua fuera del período no cuenta.
- Otra área no puede consultar ni generar la alerta.
- Una cita asistida no incrementa el resultado.

### Criterio de terminado

Durante la presentación puede demostrarse que registrar una ausencia modifica la estadística y, al alcanzar el umbral, produce una alerta preventiva verificable.

---

## RP-06 — Auditoría completa de accesos clínicos

### Situación actual

`PacienteController::show()` registra:

- Acceso al paciente.
- Acceso al primer expediente de la colección.

La pantalla puede cargar más de un expediente, especialmente para un referente o usuario multiárea. Los expedientes restantes no reciben un registro individual. Tampoco se encontró el uso de `ClinicalAccessAuditor` en `HistorialController`.

### Implementación recomendada

Registrar todos los recursos clínicos efectivamente entregados:

```php
foreach ($paciente->expedientes as $expediente) {
    $auditor->record($user, $expediente, 'view_expediente');
}
```

En el historial, registrar un acceso al paciente o un evento agregado de historial, evitando generar un número excesivo de filas si se muestran muchas consultas.

Una alternativa más limpia es implementar middleware o un servicio de auditoría de lectura común para endpoints clínicos.

### Pruebas requeridas

- Visualizar dos expedientes genera evidencia de ambos accesos.
- Consultar el historial genera un evento de acceso clínico.
- Una solicitud rechazada por autorización no genera un registro como si hubiera visto los datos.
- Los logs no contienen notas, diagnóstico ni datos sensibles en texto plano.

---

## RP-07 — Prueba de concurrencia real

### Situación actual

La restricción GiST de PostgreSQL es adecuada para impedir solapamientos. No obstante, la prueba denominada “concurrent overlapping intervals” utiliza un ciclo y envía las solicitudes una después de la otra.

La prueba demuestra detección secuencial de solapamientos, no concurrencia real.

### Implementación recomendada

Crear una prueba de integración que utilice dos conexiones independientes y transacciones coordinadas. Ambas deben intentar insertar intervalos solapados antes de confirmar.

El resultado esperado es:

- Una transacción confirma.
- La otra recibe SQLSTATE `23P01`.
- Solo queda una cita programada.

También se debe probar el manejo del error en el controlador para asegurar que se muestre el mensaje de horario ocupado.

### Criterio de terminado

Existe evidencia automática de que la base de datos resuelve correctamente dos escrituras realmente simultáneas.

---

## RP-08 — Prevalidación de solapamientos antes de migrar

### Situación actual

La migración agrega una restricción de exclusión a todos los datos existentes. Si la base de otro integrante o del entorno de presentación contiene dos citas programadas solapadas, `ALTER TABLE ... ADD CONSTRAINT` fallará.

### Implementación recomendada

Antes de agregar la restricción, ejecutar una consulta que detecte conflictos existentes:

```sql
SELECT
    a.id AS cita_a,
    b.id AS cita_b,
    a.profesional_id
FROM citas a
JOIN citas b
  ON a.profesional_id = b.profesional_id
 AND a.id < b.id
 AND tstzrange(a.fecha_hora, a.fecha_hora + interval '60 minutes', '[)')
     &&
     tstzrange(b.fecha_hora, b.fecha_hora + interval '60 minutes', '[)')
WHERE a.estado = 'programada'
  AND b.estado = 'programada'
  AND a.deleted_at IS NULL
  AND b.deleted_at IS NULL;
```

Si devuelve filas, la migración debe detenerse con un mensaje claro o debe existir un procedimiento previo de limpieza manual. No se recomienda cancelar citas automáticamente sin revisión humana.

### Pruebas requeridas

- Migración sobre una base sin conflictos: funciona.
- Migración con citas solapadas existentes: entrega un diagnóstico claro.
- La migración no elimina ni cambia citas automáticamente.

---

## RP-09 — Volumen de la vista semanal

### Situación actual

Para no truncar la semana en 30 resultados, la vista diaria y semanal ahora utiliza `get()` y carga todo el rango en memoria.

Para el MVP es aceptable, pero con un volumen elevado puede aumentar el consumo de memoria y el tiempo de respuesta.

### Implementación futura sugerida

- Definir un máximo operativo razonable.
- Seleccionar únicamente las columnas necesarias.
- Agrupar por día desde la base de datos o paginar por día.
- Medir el tiempo de respuesta con 500, 1,000 y 5,000 citas semanales.

No debe revertirse a una paginación que oculte resultados sin indicarlo.

---

## RP-10 — Actualizar evidencia y estados en Jira

### Situación actual

Durante la revisión directa de Jira, las historias y subtareas observadas continúan en estado `Por hacer`, aunque el código y las pruebas ya existen.

Esto no es un error del código, pero afecta la percepción de que el Sprint 2 está terminado.

### Procedimiento recomendado

Para cada historia PAN-14 a PAN-21:

1. Adjuntar o enlazar evidencia de la prueba correspondiente.
2. Confirmar Backend, Frontend y QA.
3. Mover cada subtarea al estado que corresponda.
4. Mover la historia únicamente cuando todos sus criterios hayan sido demostrados.
5. No cerrar PAN-18 hasta resolver RP-01.
6. No marcar QA terminado únicamente porque la suite general esté verde; ejecutar también el flujo manual de presentación.

---

## Orden recomendado de trabajo

### Antes de declarar completo el MVP

1. RP-02 — Integridad entre cita y expediente.
2. RP-01 — Confirmación de cita acordada con el paciente.
3. RP-03 — Coordinador multiárea en reprogramación/cancelación.
4. RP-05 — Definir y demostrar el alcance de alertas preventivas.

### Antes de producción o entrega formal

5. RP-04 — Inmutabilidad real de auditorías.
6. RP-06 — Cobertura completa de auditoría de lectura.
7. RP-07 — Prueba de concurrencia real.
8. RP-08 — Prevalidación de datos antes de la migración.

### Seguimiento

9. RP-09 — Rendimiento de la vista semanal.
10. RP-10 — Evidencia y estados de Jira.

## Flujo manual mínimo para aprobar el Sprint 2

1. Derivar un paciente propio o autorizado.
2. Intentar una segunda derivación activa a la misma área y comprobar el rechazo.
3. Registrar consulta con fecha, notas, diagnóstico u observación y plan.
4. Registrar consulta en una segunda área autorizada con un especialista multiárea.
5. Crear próxima cita desde la consulta activa, confirmando acuerdo con el paciente.
6. Intentar crearla desde una consulta anterior y comprobar el rechazo.
7. Crear una cita solapada y comprobar el rechazo.
8. Probar una URL manipulada con expediente y cita diferentes.
9. Registrar asistencia únicamente después de la hora programada.
10. Registrar una ausencia y demostrar su estadística y alerta preventiva.
11. Reprogramar una cita futura, verificando que el horario anterior quede libre.
12. Cancelar una cita futura con motivo.
13. Actualizar un expediente y revisar versión anterior, autor, fecha y motivo.
14. Cerrar el expediente con resultado final.
15. Intentar editarlo después del cierre y comprobar el rechazo.
16. Consultar el historial con filtros de período, área y tipo.
17. Consultar vistas diaria y semanal, incluyendo una semana con más de 30 citas.
18. Ingresar con un usuario de otra área y verificar que no vea información ajena.

## Definición final de terminado

El Sprint 2 se considerará completo cuando:

- RP-01, RP-02 y RP-03 estén corregidos y probados.
- El equipo acuerde si RP-05 requiere una alerta visible en el MVP y deje evidencia de la decisión.
- Ninguna petición directa pueda saltarse las reglas mostradas por la interfaz.
- Todas las pruebas existentes y nuevas terminen en verde.
- Se complete el flujo manual de aceptación.
- Jira contenga evidencia y estados coherentes con el código entregado.

