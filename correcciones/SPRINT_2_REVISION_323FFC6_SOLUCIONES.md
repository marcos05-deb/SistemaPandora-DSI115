# Sprint 2 — revisión final de `correcciones-sprint-2`

**Fecha de revisión:** 12 de septiembre de 2026  
**Rama revisada:** `correcciones-sprint-2`  
**Commit revisado:** `323ffc6`  
**Comparación funcional:** Jira PAN-14 a PAN-21, equivalentes a HU-07 a HU-14  
**Alcance de este documento:** informar todo lo que todavía falta y proponer una solución implementable. Esta revisión no modifica el código de la aplicación.

## Estado de implementación (posterior a la revisión)

| ID | Estado código | Notas |
|---|---|---|
| RF-01 | **Implementado** | `profesional_user_id`, exclusión GiST por usuario, `hayConflictoHorario` y advisory lock por persona |
| RF-02 | **Implementado** | `ConcurrentExclusionTest` con dos procesos reales (sin `markTestSkipped`) |
| RF-03 | **Implementado** | índice único parcial `profesionales_usuario_area_activo_unique` |
| RF-04 | Pendiente proceso | Actualizar Jira con evidencia (fuera de código) |
| RF-05 | Pendiente opcional | `npm audit fix` en rama separada |

### Comandos de validación

```bash
docker compose exec app php artisan migrate --database=pgsql_admin --force
docker compose exec app php artisan test --filter='AgendaUsuarioMultiareaTest|ConcurrentExclusionTest|PerfilUsuarioAreaUnicoTest|ExclusionAgendaDbTest'
docker compose exec app php artisan test
```

---

## Conclusión ejecutiva

La rama está sincronizada con el remoto y contiene correcciones reales para los hallazgos del informe `5935126`: selección de expediente por área, uso del perfil profesional correcto, agenda multiperfil, trazabilidad explícita de cancelación y acceso paginado a agendas grandes.

Sin embargo, **todavía no debe declararse el Sprint 2 como 100 % cerrado**. Queda un incumplimiento funcional de prioridad alta en HU-11/PAN-18: una misma persona con perfiles profesionales en dos áreas puede recibir citas solapadas, porque las tres defensas actuales comparan el identificador del perfil del área y no la identidad de la persona. También sigue faltando una prueba de concurrencia real; el archivo agregado está marcado como omitido. Hay, además, una debilidad de integridad en `profesionales` y deuda de dependencias de desarrollo.

### Estado resumido

| ID | Prioridad | Historia | Estado | Qué falta |
|---|---:|---|---|---|
| RF-01 | **Alta / bloquea cierre completo** | HU-11 / PAN-18; repercute en HU-13 | Pendiente | Evitar citas simultáneas para la misma persona cuando trabaja en áreas distintas |
| RF-02 | **Media / evidencia necesaria** | HU-11 / PAN-18 y HU-13 / PAN-20 | Pendiente | Sustituir la prueba de concurrencia omitida por dos transacciones realmente simultáneas |
| RF-03 | **Media / integridad** | HU-08 a HU-14 multiárea | Pendiente | Impedir perfiles profesionales activos duplicados para el mismo usuario y área |
| RF-04 | **Administrativa / presentación** | PAN-14 a PAN-21 | Pendiente | Cerrar o mover en Jira las historias y subtareas solo después de adjuntar evidencia |
| RF-05 | **Baja / no bloquea el runtime MVP** | Transversal | Pendiente | Actualizar dependencias de desarrollo reportadas por `npm audit` |

## Resultado por historia de usuario

| Jira | Historia | Resultado técnico actual | Observación |
|---|---|---|---|
| PAN-14 | HU-07 — Derivar paciente | **Cumple funcionalmente** | Mantiene autorización, área y motivo; evita expediente activo duplicado por área |
| PAN-15 | HU-08 — Registrar consulta | **Cumple funcionalmente** | Ahora resuelve el perfil profesional correspondiente al área del expediente |
| PAN-16 | HU-09 — Historial multidisciplinario | **Cumple funcionalmente** | Conserva orden, filtros y aislamiento por permisos |
| PAN-17 | HU-10 — Actualizar/cerrar expediente | **Cumple funcionalmente** | El autor del cierre se resuelve según el área correcta |
| PAN-18 | HU-11 — Asignar próxima cita | **Cumple parcialmente** | La vinculación y la fecha futura están cubiertas; RF-01 impide afirmar que CA-2 cumple en multiárea |
| PAN-19 | HU-12 — Asistencia/ausencia | **Cumple funcionalmente** | Usa el perfil del área, registra autor/fecha y alimenta estadísticas/alerta clínica |
| PAN-20 | HU-13 — Reprogramar/cancelar | **Cumple funcionalmente con riesgo heredado** | La cancelación ya registra autor y fecha; la reprogramación hereda el riesgo de agenda de RF-01 |
| PAN-21 | HU-14 — Consultar citas | **Cumple funcionalmente** | Incluye todos los perfiles del especialista, paginación y acceso a resultados restantes |

## Correcciones anteriores que sí quedaron resueltas

1. **Perfil profesional por área:** `ConsultaController`, `ExpedienteController` y `CitaController` ya llaman `profesionalParaArea()`.
2. **Selección de expediente:** la ficha del paciente recibe `expedientesActivos`, acepta `expediente_id` y solo selecciona entre expedientes ya autorizados.
3. **Agenda multiperfil:** un especialista consulta las citas de todos sus perfiles profesionales.
4. **Alerta preventiva:** la decisión quedó explícita: la alerta clínica solo se calcula para especialista o coordinador, no para referente.
5. **Cancelación trazable:** existen `cancelado_por_profesional_id` y `fecha_cancelacion`, se guardan al cancelar y se exponen en el recurso.
6. **Agenda de más de 500 resultados:** la vista diaria/semanal tiene total real, páginas y enlace al mismo rango en vista de lista.
7. **Documentos históricos:** los informes anteriores fueron marcados como históricos y el README indica los comandos de validación.

---

## RF-01 — Conflictos de horario entre perfiles de la misma persona

### Criterio afectado

PAN-18 / HU-11, CA-2: el sistema debe evitar conflictos con otras citas ya asignadas al mismo profesional.

### Problema exacto

El modelo multiárea representa a una persona con una fila distinta en `profesionales` por cada área. Las defensas actuales usan `profesional_id`:

- `Cita::hayConflictoHorario()` filtra por un solo `profesional_id`.
- `CitaController::bloquearAgendaProfesional()` crea el bloqueo asesor usando ese mismo ID.
- La restricción PostgreSQL `citas_no_solapamiento_programada` excluye por `profesional_id` y rango horario.

Ejemplo:

1. Eduardo tiene perfil A en Psicología y perfil B en Medicina.
2. Se agenda una cita de Psicología a las 10:00 usando perfil A.
3. Se agenda otra cita de Medicina a las 10:00 usando perfil B.
4. Los IDs son distintos, por lo que la aplicación y PostgreSQL aceptan las dos citas aunque la persona no puede atender ambas.

### Solución recomendada

Conservar el perfil por área para autoría y autorización, pero agregar a la cita la identidad estable del usuario propietario de la agenda.

#### 1. Nueva columna y migración

Agregar `profesional_user_id` a `citas`:

```php
$table->foreignId('profesional_user_id')
    ->after('profesional_id')
    ->constrained('users')
    ->restrictOnDelete();
```

La migración debe realizar estas operaciones de forma controlada:

1. Crear la columna inicialmente como nullable.
2. Completarla con `profesionales.user_id` mediante un `UPDATE ... FROM profesionales`.
3. Detectar solapamientos existentes agrupados por `profesional_user_id` y detener la migración con un mensaje claro si existen.
4. Cambiar la columna a `NOT NULL`.
5. Reemplazar la exclusión actual por una exclusión sobre `profesional_user_id` y `horario_agenda`.

Esquema de la nueva restricción:

```sql
ALTER TABLE citas
ADD CONSTRAINT citas_no_solapamiento_usuario_programada
EXCLUDE USING gist (
    profesional_user_id WITH =,
    horario_agenda WITH &&
)
WHERE (estado = 'programada' AND deleted_at IS NULL);
```

#### 2. Guardado de citas

En creación y reprogramación:

```php
$cita->profesional_id = $perfilDelArea->id;
$cita->profesional_user_id = $perfilDelArea->user_id;
```

`profesional_id` sigue indicando el perfil/área responsable; `profesional_user_id` identifica la agenda física de la persona.

#### 3. Validación previa

Cambiar `hayConflictoHorario()` para recibir el ID de usuario o resolver todos sus perfiles:

```php
->where('profesional_user_id', $profesionalUserId)
```

#### 4. Bloqueo de concurrencia

El bloqueo asesor debe usar `profesional_user_id`:

```php
$lockKey = crc32('cita-agenda-usuario:'.$profesionalUserId);
DB::select('SELECT pg_advisory_xact_lock(?)', [$lockKey]);
```

#### 5. Pruebas obligatorias

- La misma persona, mismo perfil, rango solapado: rechazada.
- La misma persona, dos perfiles/áreas, rango solapado: rechazada.
- Personas distintas, misma hora: permitida.
- La misma persona, citas contiguas 10:00–11:00 y 11:00–12:00: permitidas.
- Reprogramación hacia un intervalo ocupado en otra área: rechazada.
- Inserciones realmente concurrentes en perfiles diferentes de la misma persona: solo una confirma.

### Archivos esperados

- `database/migrations/*_add_profesional_user_id_to_citas.php`
- `app/Models/Cita.php`
- `app/Http/Controllers/CitaController.php`
- `database/factories/CitaFactory.php`
- seeders que creen citas
- pruebas de asignación, reprogramación, exclusión y multiárea

### Condición de cierre

No cerrar PAN-18 hasta que una prueba automatizada demuestre que dos perfiles de la misma persona no pueden solaparse.

---

## RF-02 — La prueba “concurrente” continúa omitida

### Problema exacto

`tests/Feature/Cita/ConcurrentExclusionTest.php` contiene un único método que ejecuta `markTestSkipped()`. Por tanto:

- no abre dos conexiones simultáneas;
- no prueba carreras entre solicitudes;
- no comprueba que una transacción gane y la otra reciba SQLSTATE `23P01`;
- una suite verde puede ocultar que esta evidencia nunca se ejecutó.

La prueba `ExclusionAgendaDbTest` es valiosa porque comprueba la restricción, pero sus inserciones son secuenciales y no reemplazan una prueba concurrente.

### Solución recomendada

Crear un pequeño comando de prueba/worker que reciba:

- `profesional_user_id`;
- fecha y hora;
- una barrera compartida para iniciar ambos intentos al mismo tiempo.

Desde la prueba, lanzar dos procesos independientes con `Symfony\Component\Process\Process`. Ambos esperan la misma señal, inician transacción e intentan crear la cita. La aserción final debe exigir:

```text
procesos exitosos = 1
procesos rechazados por conflicto = 1
citas programadas en el intervalo = 1
```

Alternativa aceptable: dos conexiones PostgreSQL independientes coordinadas mediante una barrera o notificación; no ejecutar dos peticiones una después de la otra.

### Reglas para CI

- La prueba no debe llevar `markTestSkipped()`.
- Debe ejecutarse en PostgreSQL real, no SQLite.
- Debe tener timeout corto y limpieza garantizada.
- Debe fallar si el grupo `concurrent` no ejecuta ninguna aserción.

### Archivos esperados

- `tests/Feature/Cita/ConcurrentExclusionTest.php`
- opcionalmente `tests/Support/ConcurrentCitaWorker.php` o un comando solo disponible en testing
- configuración del job PostgreSQL en CI

---

## RF-03 — Perfil activo duplicado para el mismo usuario y área

### Problema exacto

La tabla `profesionales` no tiene una restricción única para `(user_id, area_id)`. `profesionalParaArea()` usa `first()`, por lo que datos duplicados harían ambigua la autoría, los permisos y la agenda seleccionada.

### Solución recomendada

Como la tabla usa borrado lógico, crear un índice único parcial para registros activos:

```sql
CREATE UNIQUE INDEX profesionales_usuario_area_activo_unique
ON profesionales (user_id, area_id)
WHERE deleted_at IS NULL;
```

Antes de crear el índice:

```sql
SELECT user_id, area_id, COUNT(*)
FROM profesionales
WHERE deleted_at IS NULL
GROUP BY user_id, area_id
HAVING COUNT(*) > 1;
```

Si hay duplicados, elegir el perfil canónico, reasignar sus referencias clínicas con un procedimiento revisado y archivar el duplicado. No eliminarlos automáticamente porque contienen trazabilidad.

Agregar pruebas que intenten crear dos perfiles activos iguales y esperen una violación de unicidad; también probar que se puede crear uno nuevo si el anterior está borrado lógicamente, si esa es la regla de negocio elegida.

### Archivos esperados

- `database/migrations/*_unique_profesionales_usuario_area.php`
- pruebas de integridad para `Profesional`

---

## RF-04 — Jira todavía no representa un Sprint terminado

### Evidencia observada

En la consulta de Jira del 12 de septiembre de 2026:

- PAN-14, PAN-18 y PAN-21 siguen en **Por hacer**.
- Sus subtareas visibles siguen en **Por hacer** y muestran 0 % completado.
- PAN-14, PAN-18 y PAN-21 están etiquetadas explícitamente como **Sprint 2**.
- Jira devolvió un error transitorio al abrir simultáneamente PAN-15, PAN-16, PAN-17, PAN-19 y PAN-20; la última lectura completa de esas historias también las mostraba en Por hacer. Se debe verificar cada estado manualmente antes de la presentación.

### Solución recomendada

Después de resolver RF-01 a RF-03 y ejecutar la validación final:

1. Adjuntar o enlazar evidencia por cada criterio de aceptación.
2. Mover las subtareas Backend, Frontend y QA según el flujo real del equipo.
3. Marcar como terminada una historia únicamente cuando sus tres criterios estén demostrados.
4. Mantener PAN-18 abierto mientras RF-01 o RF-02 sigan pendientes.
5. Actualizar el tablero antes de la presentación para evitar que el código y la gestión del sprint se contradigan.

No se modificó Jira durante esta revisión.

---

## RF-05 — Vulnerabilidades en herramientas de desarrollo

### Resultado

`npm audit` informa **7 paquetes afectados**: 1 aviso moderado y 6 altos. La comprobación `npm audit --omit=dev` informa **0 vulnerabilidades de producción**, por lo que esto no bloquea por sí solo la demostración del MVP compilado, pero no debe ocultarse.

Paquetes señalados directa o transitivamente:

- `axios` (< 1.18.0)
- `vite` (<= 8.0.15)
- `postcss` (<= 8.5.22)
- `browserslist` (<= 4.28.6)
- `nanoid` (< 3.3.18)
- `form-data` (< 4.0.6)
- `baseline-browser-mapping` (< 2.11.0)

### Solución recomendada

1. Crear una rama separada de actualización de dependencias.
2. Ejecutar primero `npm audit fix` sin `--force`.
3. Confirmar que el lockfile eleve al menos Vite a 8.0.16, Axios a 1.18.0 y las versiones corregidas de los transitivos.
4. Ejecutar `npm run build` y las pruebas de interfaz/flujo.
5. Usar `--force` únicamente tras revisar cambios mayores y no como corrección automática antes de la presentación.

---

## Validaciones realizadas en esta revisión

| Comprobación | Resultado |
|---|---|
| `git pull --ff-only origin correcciones-sprint-2` | **Correcto — Already up to date** |
| Rama/commit | `correcciones-sprint-2` / `323ffc6` |
| Sintaxis de los 16 archivos PHP cambiados | **Correcta** |
| `npm run build` | **Correcto — 935 módulos transformados** |
| `npm audit --omit=dev` | **Correcto — 0 vulnerabilidades de producción** |
| `npm audit` completo | **7 avisos de desarrollo: 1 moderado, 6 altos** |
| Suite PHP en PostgreSQL/Docker | **176 aprobadas, 726 aserciones, 0 fallos y 1 omitida; duración 188.04 s** |
| Migraciones en PostgreSQL | **Correcto — las 44 migraciones figuran como ejecutadas, incluida `2026_09_12_230000_add_cancelacion_trazabilidad_to_citas`** |
| Prueba concurrente | **Única prueba omitida de la suite: `ConcurrentExclusionTest` ejecuta `markTestSkipped()`** |

## Reparto de correcciones

### Eduardo — backend y base de datos

Responsable principal de RF-01 y RF-03:

1. Diseñar y migrar `citas.profesional_user_id`.
2. Hacer backfill y diagnóstico de solapamientos por usuario.
3. Cambiar restricción GiST, consulta preventiva y advisory lock para usar la identidad del usuario.
4. Mantener `profesional_id` como el perfil/área que atendió.
5. Agregar el índice único parcial de perfiles activos por usuario/área.
6. Actualizar factories y seeders.
7. Entregar a Marcos los escenarios y comandos reproducibles.

### Marcos — pruebas, evidencia y cierre

Responsable principal de RF-02, RF-04 y RF-05:

1. Implementar la prueba con dos procesos/transacciones simultáneas.
2. Cubrir el caso multiárea añadido por Eduardo y verificar que solo una inserción gane.
3. Ejecutar regresión HU-07 a HU-14 y guardar evidencia por criterio.
4. Verificar cada historia y subtarea en Jira; actualizar su estado únicamente cuando la evidencia esté completa.
5. Preparar la actualización segura de dependencias de desarrollo y repetir el build.
6. Preparar la demostración diaria/semanal, reprogramación, cancelación y alerta preventiva.

### Revisión cruzada

- Marcos revisa la migración y los casos límite de Eduardo.
- Eduardo revisa que la prueba de Marcos sea realmente simultánea y que falle al retirar la exclusión.
- Ninguno cierra PAN-18 sin la aprobación del otro y una ejecución verde en PostgreSQL.

## Orden recomendado

1. **Eduardo:** RF-03 para estabilizar la relación usuario/área.
2. **Eduardo:** RF-01, incluyendo migración y las tres capas de bloqueo.
3. **Marcos:** RF-02 contra la solución terminada.
4. **Ambos:** suite completa, migraciones desde base limpia y build.
5. **Marcos:** RF-05 en rama separada si no pone en riesgo la presentación.
6. **Ambos:** evidencia y actualización final de Jira (RF-04).

## Definición de terminado para la presentación

- [ ] No existen perfiles activos duplicados por usuario y área.
- [ ] Una persona multiárea no puede recibir citas solapadas entre áreas.
- [ ] La protección existe en aplicación, bloqueo transaccional y restricción PostgreSQL.
- [ ] Una prueba concurrente real confirma que solo una transacción gana.
- [x] Las migraciones y `RefreshDatabase` corren correctamente en PostgreSQL; las 44 migraciones están aplicadas en el entorno revisado.
- [x] Las 176 pruebas ejecutadas terminan sin fallos y completan 726 aserciones.
- [ ] No quedan pruebas relevantes omitidas: actualmente falta la única prueba concurrente.
- [x] El frontend compila para producción.
- [x] No hay vulnerabilidades npm clasificadas como dependencias de producción.
- [ ] Se adjunta evidencia para cada CA de PAN-14 a PAN-21.
- [ ] Jira refleja el estado real del Sprint 2.

## Veredicto

La rama mejoró de forma importante y resolvió los siete pendientes funcionales del informe `5935126`, excepto la evidencia concurrente. La corrección multiárea introdujo o hizo visible una regla más profunda: **la agenda debe pertenecer a la persona, no al perfil del área**. Hasta resolver RF-01 y comprobarla con RF-02, el Sprint 2 está muy cerca del MVP, pero no completamente cerrado frente al CA-2 de PAN-18.
