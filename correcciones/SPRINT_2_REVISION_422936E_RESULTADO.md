# Sprint 2 — cumplimiento técnico de las historias de usuario

**Fecha de revisión:** 12 de septiembre de 2026  
**Rama:** `correcciones-sprint-2`  
**Commit revisado:** `422936e`  
**Alcance funcional:** PAN-14 a PAN-21, equivalentes a HU-07 a HU-14  
**Fuente:** código, migraciones y pruebas automatizadas del repositorio

Este documento evalúa únicamente si la implementación satisface los criterios funcionales de las historias de usuario. No evalúa estados, porcentajes, subtareas ni actualizaciones administrativas del tablero.

## Validación técnica ejecutada

| Verificación | Resultado |
|---|---|
| Suite completa con Docker y PostgreSQL | **184 pruebas aprobadas** |
| Aserciones | **743** |
| Fallos | **0** |
| Pruebas omitidas | **0** |
| Migraciones aplicadas | **46** |
| Sintaxis de los archivos PHP modificados | Correcta |
| Prueba de concurrencia de agenda | Aprobada con dos procesos independientes |

## Matriz de cumplimiento

| Jira | HU | Estado según código y pruebas |
|---|---|---|
| PAN-14 | HU-07 — Derivar un paciente | **Cumple** |
| PAN-15 | HU-08 — Registrar consulta | **Cumple** |
| PAN-16 | HU-09 — Historial multidisciplinario | **Cumple** |
| PAN-17 | HU-10 — Actualizar o cerrar expediente | **Cumple** |
| PAN-18 | HU-11 — Asignar próxima cita | **Cumple** |
| PAN-19 | HU-12 — Registrar asistencia o ausencia | **Cumple** |
| PAN-20 | HU-13 — Reprogramar o cancelar cita | **Cumple** |
| PAN-21 | HU-14 — Consultar citas e historial | **Cumple** |

## PAN-14 / HU-07 — Derivar un paciente a un área clínica

### Criterios comprobados

1. El referente solo deriva pacientes bajo su responsabilidad o con autorización vigente.
2. La derivación exige un área clínica y un motivo.
3. Se crea un único expediente activo para el paciente y el área.

### Evidencia en código y pruebas

- `routes/web.php` restringe la derivación al rol `psychosocial_referent`.
- `DerivacionStoreRequest` valida los datos de entrada.
- `DerivacionService` valida responsabilidad o autorización y crea el expediente en una transacción.
- `AutorizacionPaciente` representa las autorizaciones explícitas.
- `Expediente` cifra `motivo_derivacion` mediante `AreaEncryptedFieldCast`.
- La migración `2026_09_12_132500_add_unique_active_expediente_index.php` impide en PostgreSQL duplicar un expediente activo por paciente y área.
- `tests/Feature/Expediente/DerivacionTest.php` cubre permisos, autorización, motivo, cifrado y duplicados.
- `tests/Feature/Flujo/FlujoClinicoIntegralTest.php` comprueba su integración con el flujo clínico.

**Estado técnico:** Cumple los tres criterios.

## PAN-15 / HU-08 — Registrar una consulta clínica

### Criterios comprobados

1. El especialista registra consultas únicamente en su área y en expedientes autorizados.
2. Se registran fecha, notas clínicas, diagnóstico u observación y plan de atención.
3. Los datos sensibles quedan cifrados y asociados al autor y la fecha.

### Evidencia en código y pruebas

- `ConsultaController` valida el expediente y resuelve el perfil profesional del área correcta.
- `ConsultaStoreRequest` aplica las reglas de los campos clínicos.
- `ConsultaPolicy` y `ExpedientePolicy` controlan rol, área y autorización.
- `ConsultaService` crea la consulta y cambia el expediente a `en_atencion`.
- `Consulta` cifra notas, diagnóstico u observación, plan de atención y demás información sensible.
- La migración `2026_09_12_135100_add_plan_atencion_to_consultas_table.php` incorpora el plan por consulta.
- `tests/Feature/Consulta/ConsultaTest.php` cubre registro, campos, estado y autorización.
- `tests/Feature/Consulta/ConsultaCryptoTest.php` comprueba el cifrado.
- `tests/Feature/Multiarea/PerfilProfesionalPorAreaTest.php` verifica el perfil correcto en escenarios multiárea.

**Estado técnico:** Cumple los tres criterios, incluido el escenario multiárea.

## PAN-16 / HU-09 — Consultar el historial multidisciplinario

### Criterios comprobados

1. Las atenciones aparecen en orden cronológico e identifican fecha, área y profesional.
2. El historial permite filtrar por período, área clínica y tipo de atención.
3. Se ocultan áreas y datos no autorizados.

### Evidencia en código y pruebas

- `HistorialController` construye el historial autorizado y aplica los filtros.
- `HistorialFilterRequest` valida período, área y tipo.
- `HistorialResource` presenta fecha, área, profesional y contenido permitido.
- `AreaScope` y `EnforceAreaScope` limitan los datos por área.
- `resources/js/Pages/Historial/Show.vue` presenta la línea de tiempo y los filtros.
- `tests/Feature/Historial/ConsultarHistorialTest.php` cubre orden y filtros individuales y combinados.
- `tests/Feature/Security/AreaScopeLeakTest.php` comprueba que un área manipulada no revela datos.

**Estado técnico:** Cumple los tres criterios.

## PAN-17 / HU-10 — Actualizar o cerrar un expediente clínico

### Criterios comprobados

1. Solo un profesional autorizado del área puede modificar el expediente.
2. Cada actualización conserva valores anteriores y registra autor, fecha y motivo.
3. El cierre exige resultado final y fecha; después queda en modo consulta.

### Evidencia en código y pruebas

- `ExpedienteController` implementa actualización y cierre.
- `ExpedienteUpdateRequest` y `ExpedienteCloseRequest` validan campos y motivos.
- `ExpedientePolicy` aplica las reglas de área, rol y autorización.
- `Expediente` cifra los datos clínicos y prepara una auditoría segura.
- La migración `2026_09_12_150000_add_resultado_final_to_expedientes_table.php` agrega el resultado final.
- El cierre registra profesional, fecha y resultado; las rutas de escritura rechazan expedientes cerrados.
- La tabla `audits` conserva valores anteriores y nuevos, con protección contra modificación y eliminación.
- `tests/Feature/Expediente/CerrarExpedienteTest.php` cubre actualización, cierre, resultado, cifrado y modo consulta.
- `AuditLoggingTest` y `PrivilegesTest` comprueban contenido e inmutabilidad de auditoría.

**Estado técnico:** Cumple los tres criterios.

## PAN-18 / HU-11 — Asignar la próxima cita durante la atención

### Criterios comprobados

1. La cita se asigna desde una consulta activa del paciente.
2. La fecha y hora son futuras y no se solapan con la agenda del profesional.
3. La cita queda vinculada al paciente, profesional y consulta origen, en estado `programada`.

### Evidencia en código y pruebas

- `CitaController` valida consulta origen, expediente, área, fecha futura y perfil profesional.
- `CitaStoreRequest` valida los datos de agendamiento.
- `Cita::hayConflictoHorario()` busca conflictos por la identidad del usuario profesional.
- `consulta_id` conserva la consulta que originó la cita.
- `profesional_user_id` unifica la agenda de una persona con perfiles en varias áreas.
- La creación usa transacción y advisory lock por usuario.
- La migración `2026_09_12_241000_add_profesional_user_id_to_citas.php` establece una exclusión GiST por usuario y rango de 60 minutos.
- La migración `2026_09_12_240000_unique_profesionales_usuario_area.php` impide perfiles activos duplicados por usuario y área.
- `AsignarProximaCitaTest` cubre origen, pertenencia, fecha, estado y autorización.
- `AgendaUsuarioMultiareaTest` rechaza solapes entre áreas de la misma persona.
- `ExclusionAgendaDbTest` comprueba directamente la exclusión de PostgreSQL.
- `ConcurrentExclusionTest` inicia dos procesos reales: uno confirma y el otro recibe el conflicto `23P01`.

**Estado técnico:** Cumple los tres criterios, incluidos multiárea y concurrencia real.

## PAN-19 / HU-12 — Registrar asistencia o ausencia

### Criterios comprobados

1. Solo se registra cuando la fecha y hora de la cita ya llegaron.
2. Se guarda `Asistió` o `Ausente`, autor, fecha y hora.
3. Una ausencia alimenta las estadísticas utilizadas por alertas preventivas.

### Evidencia en código y pruebas

- `CitaAsistenciaRequest` limita los resultados aceptados.
- `Cita` valida el timestamp completo, bloquea la fila, exige estado `programada` y registra autor y momento.
- `EstadoCita` centraliza los estados.
- `CitaAusenciaRegistrada` publica el evento de ausencia.
- `RegistrarAusenciaEnEstadisticasPreventivas` persiste el dato preventivo.
- `EstadisticasPreventivasService` consulta y cuenta ausencias con aislamiento por área.
- `tests/Feature/Cita/AsistenciaTest.php` cubre hora pasada, hora futura, autorización, doble registro, auditoría y estadísticas.
- `AlertasPreventivasTest` comprueba el uso preventivo del dato.
- `CitaExpedienteScopeTest` impide registrar asistencia mediante otro expediente.

**Estado técnico:** Cumple los tres criterios.

## PAN-20 / HU-13 — Reprogramar o cancelar una cita

### Criterios comprobados

1. Solo se modifican citas futuras en estado permitido.
2. Ambas operaciones exigen motivo; reprogramar exige nueva fecha futura acordada.
3. El horario anterior se libera y se conserva usuario, fecha e historial.

### Evidencia en código y pruebas

- `CitaController` ejecuta ambas operaciones en transacciones y bloquea la cita original.
- `CitaReprogramarRequest` valida nueva fecha, motivo y confirmación con el paciente.
- `CitaCancelarRequest` exige el motivo de cancelación.
- La reprogramación marca la anterior como `reprogramada`, crea la nueva como `programada` y conserva `cita_origen_id`.
- Se registran los motivos, profesionales y fechas de ambas operaciones.
- La exclusión solo considera citas `programada`, por lo cual la anterior libera el horario.
- El conflicto se comprueba por `profesional_user_id`, incluso entre diferentes áreas de la misma persona.
- `ReprogramarCancelarCitaTest` cubre motivos, fechas, autorización, estados, trazabilidad y conflictos.
- `AgendaUsuarioMultiareaTest` comprueba el conflicto de reprogramación contra otra área.
- `CoordinadorMultiareaCitaTest` y `CitaExpedienteScopeTest` comprueban alcance y pertenencia.

**Estado técnico:** Cumple los tres criterios.

## PAN-21 / HU-14 — Consultar citas asignadas e historial

### Criterios comprobados

1. Hay vistas diaria y semanal con fecha, hora, paciente y estado.
2. Se filtra por período, paciente, profesional y resultado de asistencia.
3. Los resultados se limitan al rol, áreas y permisos del usuario.

### Evidencia en código y pruebas

- `CitaController` construye la agenda paginada considerando todos los perfiles autorizados.
- `IndexCitasRequest` valida vista, período, paciente, profesional y estado.
- `CitaPolicy`, `AreaScope` y `EnforceAreaScope` protegen el acceso.
- `CitaResource` y `CitaGridResource` limitan y estructuran los datos.
- `resources/js/Pages/Citas/Index.vue` implementa agenda diaria, semanal, navegación, filtros y paginación.
- Los parámetros permanecen al navegar y cambiar página.
- `tests/Feature/Citas/ConsultarCitasTest.php` cubre vistas, agrupación, campos, filtros, navegación y paginación.
- `AgendaUsuarioMultiareaTest` comprueba que se reúnen las citas de todos los perfiles de la persona.
- `CoordinadorMultiareaCitaTest` y `AreaScopeLeakTest` comprueban permisos y aislamiento.

**Estado técnico:** Cumple los tres criterios.

## Hallazgo técnico pendiente detectado en el código

### Integridad del par profesional y usuario de la cita

`citas.profesional_id` referencia un perfil y `citas.profesional_user_id` referencia un usuario mediante claves foráneas independientes. PostgreSQL todavía no garantiza que el usuario sea el propietario del perfil indicado.

Los controladores derivan correctamente ambos valores y las peticiones HTTP no permiten elegir `profesional_user_id`; por eso los flujos de las historias cumplen. Aun así, una importación o proceso interno futuro podría insertar un par inconsistente y aplicar la exclusión a la agenda equivocada.

### Forma recomendada de corregirlo

1. Crear una restricción única sobre `profesionales (id, user_id)`.
2. Crear una clave foránea compuesta desde `citas (profesional_id, profesional_user_id)` hacia `profesionales (id, user_id)`.
3. Retirar `profesional_user_id` de `$fillable` en `Cita.php`.
4. Derivar siempre el usuario desde el perfil durante `saving`, o rechazar cualquier valor diferente.
5. Probar inserción y actualización con un perfil de un usuario y el identificador de otro.

### Estado de implementación

| Paso | Estado |
|---|---|
| Índice único `profesionales (id, user_id)` | **Implementado** — `2026_09_12_250000_citas_profesional_user_consistency.php` |
| FK compuesta `citas_profesional_id_user_id_fk` | **Implementado** |
| `profesional_user_id` fuera de `$fillable` | **Implementado** |
| Derivación/rechazo en `saving` | **Implementado** |
| Pruebas de inconsistencia | **Implementado** — `ProfesionalUserConsistencyTest` |

```bash
docker compose exec app php artisan migrate --database=pgsql_admin --force
docker compose exec app php artisan test --filter=ProfesionalUserConsistencyTest
```

## Evidencia reproducible

```powershell
docker compose exec -T app php artisan test
docker compose exec -T app php artisan migrate:status --database=pgsql_admin
```

Resultados esperados para `422936e`: `184 passed`, `743 assertions`, `0 failed`, `0 skipped` y 46 migraciones en estado `Ran`.
Tras la corrección de integridad del par profesional/usuario: al menos **187** pruebas con la nueva migración aplicada.
