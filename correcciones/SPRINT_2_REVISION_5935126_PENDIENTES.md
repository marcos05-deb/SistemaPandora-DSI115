# Sprint 2 — Revisión del commit 5935126 y pendientes restantes

## Comandos operativos (tras aplicar estos cambios)

```bash
# Migraciones (incluye trazabilidad de cancelación)
docker compose exec app php artisan migrate --database=pgsql_admin --force

# Pruebas del soporte multiárea y agenda
docker compose exec app php artisan test \
  tests/Feature/Multiarea/PerfilProfesionalPorAreaTest.php \
  tests/Feature/Citas/ConsultarCitasTest.php \
  tests/Feature/Cita/ReprogramarCancelarCitaTest.php
```

## Información de la revisión

- Fecha: 12 de septiembre de 2026
- Rama: `correcciones-sprint-2`
- Commit anterior revisado: `ec6965d`
- Commit actual después del pull: `5935126`
- Estado respecto al remoto: sincronizado
- Alcance Jira: PAN-14 a PAN-21, correspondientes a HU-07 a HU-14
- Suite automática: 168 pruebas aprobadas, 682 aserciones, 0 fallos y 0 incompletas
- Compilación de producción: exitosa
- Migraciones: aplicadas en el entorno local
- Permisos de auditoría comprobados: `pandora_app` conserva únicamente `SELECT` e `INSERT` sobre `audits`

## Resultado general

Las correcciones incorporadas desde `ec6965d` solucionan los pendientes principales del informe anterior:

- La cita inicial exige confirmar que la fecha y hora fueron acordadas con el paciente.
- Las operaciones de asistencia, reprogramación y cancelación validan la relación cita–expediente.
- Los coordinadores multiárea pueden reprogramar y cancelar dentro de cualquiera de sus áreas autorizadas.
- La cuenta de aplicación ya no puede actualizar ni eliminar auditorías mediante SQL directo.
- El motivo de actualización del expediente se incluye en el registro original de auditoría.
- Se audita la consulta del historial y cada expediente entregado en la vista del paciente.
- Las ausencias generan una alerta preventiva visible al alcanzar el umbral.
- La migración detecta solapamientos existentes antes de crear la restricción.
- Existe una prueba directa de la restricción PostgreSQL y del SQLSTATE `23P01`.
- La vista semanal tiene un límite explícito y avisa cuando existen más resultados.

Con el flujo normal de un profesional de una sola área, PAN-14 a PAN-21 cumplen los criterios de Jira para un MVP. Aun así, quedan problemas estructurales y de evidencia que deben resolverse para afirmar que el soporte multiárea y la trazabilidad están completos.

## Resumen de nuevos pendientes

| ID | Prioridad | Historias afectadas | Pendiente |
|---|---:|---|---|
| R593-01 | Crítica | PAN-15, PAN-18, PAN-19 y PAN-21 | Los registros clínicos de usuarios multiárea se asocian siempre al primer perfil profesional |
| R593-02 | Alta | PAN-15 y PAN-18 | La interfaz usa únicamente el primer expediente activo y no permite elegir el área de trabajo |
| R593-03 | Alta | PAN-21 | La agenda del especialista consulta únicamente las citas de su primer perfil profesional |
| R593-04 | Media | PAN-19 | La alerta puede quedar invisible para un referente responsable porque se filtra por sus áreas clínicas |
| R593-05 | Media | PAN-20 | La cancelación no tiene campos explícitos de autor y fecha; depende únicamente de la auditoría |
| R593-06 | Media | PAN-21 | La vista semanal vuelve a ocultar resultados cuando supera 500 citas |
| R593-07 | Baja | PAN-18 y PAN-20 | La prueba de exclusión valida la base de datos, pero no ejecuta dos transacciones realmente simultáneas |
| R593-08 | Gestión | Sprint 2 | Los documentos anteriores y los estados de Jira pueden indicar pendientes que ya fueron resueltos |

---

## R593-01 — Resolver correctamente el perfil profesional según el área

### Problema

El sistema representa las áreas autorizadas de un usuario mediante varios registros en la tabla `profesionales`. Cada registro tiene un `id` diferente y un `area_id` diferente.

Sin embargo, `Especialista::profesional()` es una relación `HasOne`. Por eso, expresiones como esta siempre devuelven un solo perfil, normalmente el primero:

```php
$request->user()->profesional->id
```

Ese identificador se utiliza al:

- Registrar una consulta.
- Crear una cita.
- Registrar asistencia.
- Registrar quién reprogramó.
- Cerrar un expediente.
- Filtrar la agenda del especialista.

Ejemplo del error:

1. El usuario tiene un perfil profesional para Psicología y otro para Medicina.
2. El expediente pertenece a Medicina.
3. La autorización multiárea permite registrar la consulta.
4. La consulta queda asociada al perfil de Psicología porque es el primero devuelto por `profesional()`.
5. El `area_id` del expediente y el área del profesional autor quedan inconsistentes.

Las pruebas actuales verifican que un usuario multiárea pueda crear registros, pero no verifican que el `profesional_id` guardado corresponda al área del expediente.

### Archivos relacionados

- `app/Models/Especialista.php`
- `app/Models/Profesional.php`
- `app/Http/Controllers/ConsultaController.php`
- `app/Http/Controllers/CitaController.php`
- `app/Http/Controllers/ExpedienteController.php`
- `app/Policies/CitaPolicy.php`
- `app/Services/ConsultaService.php`

### Implementación recomendada

Agregar una relación plural explícita:

```php
public function perfilesProfesionales(): HasMany
{
    return $this->hasMany(Profesional::class, 'user_id');
}
```

Agregar un método que resuelva el perfil correspondiente al área:

```php
public function profesionalParaArea(int $areaId): ?Profesional
{
    return $this->perfilesProfesionales()
        ->where('area_id', $areaId)
        ->first();
}
```

En cada operación clínica, resolver el perfil utilizando el área del recurso:

```php
$profesional = $request->user()->profesionalParaArea($expediente->area_id);

abort_unless($profesional, 403);
```

Después usar:

```php
$profesional->id
```

en lugar de:

```php
$request->user()->profesional->id
```

Para una cita existente, el área debe resolverse desde `$cita->area_id`. Para el cierre o consulta, desde `$expediente->area_id`.

### Pruebas necesarias

- Usuario con perfiles A y B registra una consulta en A; `profesional_id` pertenece a A.
- Registra una consulta en B; `profesional_id` pertenece a B.
- Crea citas en ambas áreas y cada cita conserva el profesional correcto.
- La agenda muestra las citas de ambos perfiles.
- La asistencia registra como autor el perfil correspondiente al área de la cita.
- El cierre registra como autor el perfil correspondiente al área del expediente.
- El usuario no puede forzar un perfil de un área no autorizada.

### Criterio de terminado

Todo registro clínico guarda un `profesional_id` cuyo `area_id` coincide con el área del expediente o cita correspondiente.

---

## R593-02 — Permitir seleccionar el expediente o área activa en la interfaz

### Problema

`PacienteController::show()` y `Pacientes/Show.vue` seleccionan el primer expediente no cerrado:

```php
$expedienteActivo = $paciente->expedientes
    ->where('estado', '!=', 'cerrado')
    ->first();
```

Un paciente puede tener expedientes activos en varias áreas y un especialista multiárea puede estar autorizado para más de uno. En ese escenario, la interfaz permite trabajar únicamente con el primer expediente encontrado.

El backend puede aceptar una ruta directa hacia otro expediente, pero el usuario no dispone de un flujo claro en la pantalla para elegirlo.

### Implementación recomendada

1. Enviar todos los expedientes activos autorizados como una colección explícita.
2. Mostrar un selector de área o expediente cuando haya más de uno.
3. Mantener en la URL o en el estado de la página el expediente seleccionado.
4. Calcular permisos, consulta activa y citas pendientes para el expediente seleccionado.

Ejemplo de propiedad:

```php
'expedientesActivos' => $paciente->expedientes
    ->where('estado', '!=', 'cerrado')
    ->values(),
```

La selección debe resolverse nuevamente en el servidor; no se debe confiar solo en el valor enviado desde Vue.

### Pruebas necesarias

- Paciente con dos expedientes activos autorizados muestra ambas áreas.
- Seleccionar el expediente A muestra sus consultas y citas.
- Seleccionar el expediente B muestra únicamente sus consultas y citas.
- Un expediente de un área no autorizada no aparece ni puede seleccionarse manualmente.

### Criterio de terminado

Un especialista multiárea puede identificar y operar sobre cualquiera de los expedientes activos para los que tenga autorización.

---

## R593-03 — Consultar agenda de todos los perfiles del especialista

### Problema

En la vista de citas, cuando el usuario no es coordinador, la consulta utiliza:

```php
$query->where('profesional_id', $user->profesional->id);
```

Esto muestra únicamente las citas asociadas al primer perfil profesional. Si se corrige R593-01 y las citas de la segunda área se guardan con el segundo perfil, esas citas desaparecerán de la agenda personal.

### Implementación recomendada

Obtener todos los perfiles autorizados del usuario:

```php
$profesionalIds = $user->perfilesProfesionales()->pluck('id');
$query->whereIn('profesional_id', $profesionalIds);
```

La autorización de asistencia para un especialista también debe comprobar que la cita pertenezca a uno de sus perfiles profesionales, no únicamente al primero:

```php
return $user->hasRole('specialist')
    && $user->perfilesProfesionales()
        ->whereKey($cita->profesional_id)
        ->exists();
```

### Pruebas necesarias

- Especialista multiárea ve sus citas de ambas áreas.
- Puede registrar asistencia en sus citas de ambas áreas.
- No ve ni modifica citas de otro especialista de las mismas áreas.
- Los filtros de la agenda continúan respetando `AreaScope`.

### Criterio de terminado

La agenda personal incluye todas las citas asignadas a cualquiera de los perfiles profesionales pertenecientes al usuario autenticado.

---

## R593-04 — Definir quién debe recibir la alerta preventiva

### Problema

`PacienteController::show()` calcula la alerta para todos los roles autorizados a abrir el paciente. El servicio de estadísticas filtra las ausencias por las áreas del usuario.

Un referente psicosocial responsable puede abrir al paciente y ver sus expedientes derivados, pero normalmente no pertenece al área clínica donde se registró la ausencia. En consecuencia, su alerta puede devolver cero aunque el paciente tenga múltiples ausencias clínicas.

### Decisión funcional necesaria

Definir una de estas alternativas:

1. La alerta es únicamente para especialistas y coordinadores del área clínica.
2. El referente responsable también debe ver una alerta consolidada de las áreas a las que derivó al paciente.

### Implementación sugerida

Si la alerta es clínica, no calcularla para el referente y mostrarla solo a especialistas/coordinadores.

Si el referente debe recibirla, crear un método separado que valide responsabilidad o autorización vigente sobre el paciente y agregue las ausencias de sus expedientes, sin permitir consultas arbitrarias de pacientes ajenos.

### Pruebas necesarias

- Especialista ve alerta únicamente de sus áreas.
- Coordinador ve alerta de cualquiera de sus áreas autorizadas.
- Referente responsable obtiene el comportamiento definido.
- Referente no responsable no recibe datos estadísticos del paciente.

---

## R593-05 — Trazabilidad explícita de cancelación

### Problema

La reprogramación conserva campos explícitos:

- `reprogramado_por_profesional_id`
- `fecha_reprogramacion`

La cancelación conserva únicamente `motivo_cancelacion` y depende de la tabla general de auditoría para conocer autor y fecha.

Esto puede cumplir técnicamente el historial, pero dificulta consultar o demostrar el CA-3 de PAN-20 en la interfaz y crea una diferencia innecesaria entre reprogramación y cancelación.

### Implementación recomendada

Agregar mediante migración:

```php
$table->foreignUuid('cancelado_por_profesional_id')
    ->nullable()
    ->constrained('profesionales');
$table->timestampTz('fecha_cancelacion')->nullable();
```

Al cancelar:

```php
$profesional = $request->user()->profesionalParaArea($cita->area_id);
$cita->cancelado_por_profesional_id = $profesional->id;
$cita->fecha_cancelacion = now();
```

Conservar también la auditoría general; los campos no la sustituyen.

### Pruebas necesarias

- Cancelación almacena motivo, autor y fecha.
- Autor pertenece a un perfil autorizado para el área.
- Los datos se muestran en el historial de cambios o detalle de cita.
- Una cancelación rechazada no llena esos campos.

---

## R593-06 — Evitar que el límite de 500 oculte la semana

### Problema

La vista diaria/semanal ahora limita a 500 citas. Cuando se supera el límite muestra una advertencia, pero no ofrece paginación ni una forma de ver directamente los registros restantes dentro de esa misma vista.

Esto protege memoria, pero el CA-1 de PAN-21 exige consultar las citas asignadas en vista diaria y semanal. Una advertencia no reemplaza el acceso a los resultados restantes.

### Implementación recomendada

Para el MVP se puede mantener el límite, pero agregar una de estas soluciones:

- Paginación real conservando la agrupación por día.
- Paginación por día de la semana.
- Botón “Ver resultados restantes” que cambie a vista de lista conservando exactamente el rango semanal.

La propiedad `total` del paginador debe representar `$totalRango`, no únicamente `$items->count()`.

### Pruebas necesarias

- Con 501 citas se muestra la advertencia.
- Las 501 citas siguen siendo accesibles mediante paginación o navegación.
- La agrupación y filtros no se pierden.
- El usuario no puede acceder a citas fuera de su área.

---

## R593-07 — Concurrencia real en la prueba

### Situación actual

La nueva prueba valida directamente que PostgreSQL rechaza un solapamiento con SQLSTATE `23P01`. Esto demuestra correctamente la restricción de base de datos.

Todavía no se ejecutan dos transacciones simultáneas desde conexiones diferentes. La restricción hace que el riesgo funcional sea bajo, pero una prueba concurrente daría evidencia adicional sobre bloqueos y manejo HTTP.

### Implementación futura sugerida

- Abrir dos conexiones PostgreSQL.
- Iniciar una transacción en cada conexión.
- Intentar insertar intervalos solapados antes de confirmar ambas.
- Confirmar que solamente una puede finalizar.
- Verificar que la aplicación convierte `23P01` en el mensaje de horario ocupado.

Este punto no bloquea el MVP porque la restricción GiST ya está activa y probada.

---

## R593-08 — Sincronizar documentación y Jira

### Problema

Los Markdown anteriores describen tareas que ya fueron resueltas en `5935126`. Deben conservarse como historial, pero no utilizarse como estado actual.

Jira también puede continuar mostrando historias o subtareas en `Por hacer` aunque el código esté implementado.

### Procedimiento recomendado

1. Usar este documento como revisión técnica más reciente.
2. Marcar los informes anteriores como históricos o añadirles un enlace hacia este archivo.
3. Adjuntar evidencia de las 168 pruebas a las subtareas QA.
4. Ejecutar el flujo manual de presentación.
5. Actualizar los estados de Jira únicamente después de comprobar cada criterio.

---

## Estado actualizado por historia

| Jira | Historia | Estado técnico actual |
|---|---|---|
| PAN-14 | HU-07 Derivar paciente | Cumple |
| PAN-15 | HU-08 Registrar consulta | Cumple en área única; pendiente consistencia del autor multiárea |
| PAN-16 | HU-09 Historial multidisciplinario | Cumple |
| PAN-17 | HU-10 Actualizar/cerrar expediente | Cumple; mejorar resolución del autor multiárea |
| PAN-18 | HU-11 Próxima cita | Cumple en área única; pendiente perfil profesional multiárea |
| PAN-19 | HU-12 Asistencia/ausencia | Cumple en área única; falta decisión sobre alerta para referentes |
| PAN-20 | HU-13 Reprogramar/cancelar | Cumple; recomendable trazabilidad explícita de cancelación |
| PAN-21 | HU-14 Consultar citas | Cumple para volumen MVP; pendiente agenda multiperfil y acceso tras 500 resultados |

## Prioridad recomendada

### Antes de afirmar soporte multiárea completo

1. R593-01 — Resolver perfil profesional por área.
2. R593-02 — Selector de expediente o área activa.
3. R593-03 — Agenda con todos los perfiles profesionales.

### Antes de la demostración final

4. R593-04 — Definir quién recibe alertas.
5. R593-05 — Mostrar trazabilidad de cancelación o demostrarla mediante auditoría.
6. R593-08 — Actualizar evidencia y Jira.

### Mejoras de robustez

7. R593-06 — Acceso a resultados posteriores al límite de 500.
8. R593-07 — Prueba con concurrencia real.

## Flujo manual mínimo de regresión

1. Derivar paciente propio o con autorización vigente.
2. Rechazar expediente activo duplicado en la misma área.
3. Registrar consulta con todos los campos exigidos.
4. Crear cita desde la consulta activa y confirmar acuerdo con paciente.
5. Rechazar cita desde consulta anterior.
6. Rechazar horario solapado.
7. Rechazar combinación de expediente y cita diferentes.
8. Registrar asistencia después de la hora programada.
9. Registrar dos ausencias y verificar la alerta preventiva.
10. Reprogramar una cita y verificar liberación del horario original.
11. Cancelar una cita y revisar motivo, usuario y fecha en auditoría.
12. Actualizar expediente y comprobar versión anterior, motivo, autor y fecha.
13. Cerrar expediente y comprobar modo de consulta.
14. Consultar historial con filtros y probar aislamiento entre áreas.
15. Probar todo el flujo con un usuario de dos áreas y verificar los `profesional_id` guardados.

## Definición de terminado recomendada

El Sprint 2 puede declararse completamente terminado cuando:

- Todas las pruebas actuales siguen aprobando.
- Las pruebas nuevas de perfiles profesionales por área están aprobadas.
- La interfaz permite elegir el expediente autorizado correcto.
- La agenda muestra todas las citas pertenecientes a todos los perfiles del usuario.
- Cada consulta, cita, asistencia y cierre queda asociado al profesional del área correcta.
- Se define y prueba quién recibe las alertas preventivas.
- La trazabilidad de cancelación puede demostrarse con autor y fecha.
- Todos los resultados semanales permanecen accesibles aun cuando se alcance el límite operativo.
- La evidencia manual está adjunta y los estados de Jira reflejan la realidad del código.
