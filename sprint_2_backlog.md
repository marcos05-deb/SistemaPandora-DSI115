# Sprint 2 Backlog — Sistema PANDORA

**Objetivo del Sprint:** Habilitar la operación clínica del sistema mediante el expediente multidisciplinario y la gestión de citas.  
**Periodo:** 24 de agosto al 20 de septiembre de 2026  
**Épicas:** PAN-3 (Expediente multidisciplinario) · PAN-4 (Gestión de citas)  
**Story Points totales:** 57  
**Prerrequisito crítico:** La historia US-06b (Deuda C-09: Cifrado multi-especialista) debe completarse antes de iniciar las historias clínicas.

---

## Contexto y Dependencias

El Sprint 1 construyó los cimientos de seguridad: autenticación con KDF Argon2id, RBAC con AreaScope, cifrado XSalsa20-Poly1305 de campos clínicos, y búsqueda segura. Todas las HU del Sprint 2 dependen de que esa infraestructura esté operativa (HU-01 a HU-06: **Hecho**).

### Deuda C-09 — Cifrado Multi-Especialista

El ROADMAP §7 documenta que la clave simétrica actual se deriva de la contraseña individual del Especialista, lo que impide que otros especialistas de la misma área lean datos cifrados por un colega. **Debe resolverse antes de cualquier HU que implique lectura cross-especialista** (HU-09, HU-14).

**Solución adoptada (ROADMAP §7, Opción A):** Formalizada en la historia **US-06b**. La clave individual del Especialista solo se usa para autenticarlo, no para cifrar datos clínicos.

---

## Historias de Usuario

---

### US-06b: Migrar infraestructura criptográfica a Clave por Área (Deuda C-09)

**Épica:** PAN-3 · **Puntos:** 8 · **Prioridad:** Crítica (Bloqueante)

**Descripción:**  
"Como Administrador del Sistema, quiero migrar el mecanismo de cifrado de campos clínicos a un esquema de clave compartida por Área (AREA_KEY_SECRET), para que múltiples especialistas de una misma área puedan leer el expediente de un paciente sin comprometer el aislamiento entre áreas."

**Criterios de Aceptación:**

```gherkin
Dado que el sistema tiene configurado el AREA_KEY_SECRET en el entorno
Cuando un Especialista registra o lee información clínica (motivo_consulta, diagnostico, etc.)
Entonces el sistema utiliza el secreto del Área derivada para cifrar/descifrar
  Y el especialista ya no utiliza la clave derivada de su contraseña para datos clínicos

Dado que existen datos cifrados con el esquema anterior (si aplica en entorno de pruebas/producción)
Cuando se ejecuta el comando de migración criptográfica
Entonces los datos se descifran con la llave antigua y se recifran con la nueva AREA_KEY_SECRET
  Y la integridad de los datos se mantiene intacta
```

**Tareas Técnicas (Laravel):**

- [ ] **Configuración:** Agregar variable de entorno `AREA_KEY_SECRET` y validación en el bootstrap.
- [ ] **Casts (`EncryptedFieldCast`):** Refactorizar el cast para que dependa del ID del Área del contexto actual (o inyectar la clave correcta) en lugar del KDF del usuario.
- [ ] **Comando Artisan:** Crear un comando `crypto:migrate-area-keys` para migrar datos existentes (lectura con KDF antiguo, escritura con nueva llave de área).
- [ ] **Tests:** Unit tests verificando que dos usuarios de la misma área puedan descifrar el mismo campo, y usuarios de áreas distintas fallen.


---

### US-07: Derivar un paciente a un área clínica

**Épica:** PAN-3 · **Puntos:** 8 · **Prioridad:** Alta

**Descripción:**  
"Como Referente Psicosocial, quiero derivar a un paciente registrado hacia un área clínica específica, para que el especialista de dicha área pueda iniciar su atención."

**Criterios de Aceptación:**

```gherkin
Dado que existe un paciente registrado en el sistema con código de privacidad válido
  Y el Referente Psicosocial está autenticado y tiene sesión activa
Cuando el Referente selecciona al paciente y elige un área clínica destino
  Y confirma la derivación
Entonces se crea un nuevo expediente vinculado al paciente y al área destino
  Y el expediente queda en estado "abierto"
  Y el evento queda registrado en el audit log
  Y el expediente es visible para los especialistas del área destino (gracias al AreaScope)
  Y el expediente NO es visible para especialistas de otras áreas

Dado que ya existe un expediente abierto para el mismo paciente en la misma área
Cuando el Referente intenta derivar al paciente a esa misma área
Entonces el sistema rechaza la operación con un mensaje descriptivo
  Y no se crea un expediente duplicado

Dado que el usuario autenticado NO tiene el rol de Referente Psicosocial
Cuando intenta acceder a la funcionalidad de derivación
Entonces recibe HTTP 403 Forbidden
```

**Tareas Técnicas (Laravel):**

- [ ] **Migración:** Agregar columnas al esquema `expedientes`: `estado` (ENUM: `abierto`, `en_atencion`, `cerrado`), `derivado_por_profesional_id` (FK a `profesionales`), `fecha_derivacion` (TIMESTAMPTZ).
- [ ] **Modelo `Expediente`:** Actualizar relaciones: `belongsTo Paciente`, `belongsTo Area`, `belongsTo Profesional` (derivante). Agregar mutador para campo `estado`.
- [ ] **Controlador `DerivacionController`:** Método `store()` para crear expediente. Transacción DB para atomicidad. Registrar evento de auditoría usando el paquete `spatie/laravel-activitylog` (o el `AuditableController` base).
- [ ] **Form Request `DerivacionStoreRequest`:** Validar: `paciente_id` existe, `area_id` existe, no duplicar expediente abierto en misma área.
- [ ] **Policy `ExpedientePolicy`:** Método `derivar()`: solo rol `psychosocial_referent`.
- [ ] **Rutas:** `POST /derivaciones` protegida con middleware `auth` + policy.
- [ ] **Vista:** `Pages/Derivaciones/Create.vue` — formulario con selector de paciente y área destino, integrado a `ClinicalLayout`.
- [ ] **Tests:** Feature test cubriendo: derivación exitosa, rechazo de duplicado, acceso no autorizado (403).

---

### US-08: Registrar una consulta clínica

**Épica:** PAN-3 · **Puntos:** 8 · **Prioridad:** Alta

**Descripción:**  
"Como Especialista de un área clínica, quiero registrar una consulta dentro del expediente de un paciente derivado a mi área, para documentar la atención brindada."

**Criterios de Aceptación:**

```gherkin
Dado que el Especialista está autenticado
  Y existe un expediente abierto o en atención para un paciente en su área
Cuando el Especialista accede al expediente y registra una nueva consulta
  Y completa los campos obligatorios (motivo, notas clínicas, diagnóstico)
  Y confirma el registro
Entonces se persiste una nueva consulta vinculada al expediente
  Y los campos clínicos se almacenan cifrados en reposo
  Y el estado del expediente se actualiza a "en_atencion" si estaba "abierto"
  Y el evento queda registrado en el audit log

Dado que el Especialista NO pertenece al área del expediente
Cuando intenta registrar una consulta en dicho expediente
Entonces recibe HTTP 403 o HTTP 404 (por efecto del AreaScope)

Dado que el expediente está en estado "cerrado"
Cuando el Especialista intenta registrar una consulta
Entonces el sistema rechaza la operación indicando que el expediente está cerrado
```

**Tareas Técnicas (Laravel):**

- [ ] **Migración:** Crear tabla `consultas`: `id` (UUID), `expediente_id` (`foreignUuid`), `profesional_id` (`foreignUuid`), `motivo_consulta` (TEXT cifrado), `notas_clinicas` (TEXT cifrado), `diagnostico` (TEXT cifrado), `fecha_consulta` (TIMESTAMPTZ), timestamps, soft deletes.
- [ ] **Modelo `Consulta`:** Relaciones `belongsTo Expediente`, `belongsTo Profesional`. Aplicar `EncryptedFieldCast` a campos clínicos. Aplicar `SoftDeletes`. Registrar en Log de Auditoría (ej. trait `LogsActivity` de Spatie).
- [ ] **Controlador `ConsultaController`:** Métodos `create()` y `store()`. Transacción DB. Actualizar estado del expediente. Registrar evento de creación en auditoría.
- [ ] **Form Request `ConsultaStoreRequest`:** Validar campos obligatorios, verificar que el expediente no esté cerrado.
- [ ] **Policy `ConsultaPolicy`:** Solo especialistas del área del expediente. Verificar estado del expediente.
- [ ] **Rutas:** `GET /expedientes/{expediente}/consultas/create`, `POST /expedientes/{expediente}/consultas`. Middleware `auth` + `enforce_area_scope` + policy.
- [ ] **Vista:** `Pages/Consultas/Create.vue` — formulario con campos clínicos, integrado a `ClinicalLayout`.
- [ ] **Tests:** Feature test cubriendo: registro exitoso con cifrado, rechazo cross-área, rechazo en expediente cerrado.

---

### US-09: Consultar el historial multidisciplinario

**Épica:** PAN-3 · **Puntos:** 8 · **Prioridad:** Media

**Descripción:**  
"Como Especialista o Coordinador de Área, quiero consultar el historial completo de atenciones de un paciente dentro de mi área (o áreas autorizadas), para tener contexto clínico integral antes de una atención."

**Criterios de Aceptación:**

```gherkin
Dado que el Especialista/Coordinador está autenticado
  Y tiene áreas autorizadas asignadas
Cuando accede al historial de un paciente con expedientes en sus áreas
Entonces visualiza la lista cronológica de consultas registradas
  Y cada consulta muestra: fecha, área, especialista que atendió, motivo y diagnóstico (descifrados)
  Y solo aparecen consultas de expedientes dentro de sus áreas autorizadas (AreaScope)

Dado que el paciente tiene expedientes en áreas a las que el usuario NO pertenece
Cuando consulta el historial
Entonces las consultas de áreas no autorizadas NO aparecen en el listado

Dado que el usuario tiene rol "sysadmin"
Cuando intenta acceder al historial clínico
Entonces recibe HTTP 403 Forbidden
```

**Tareas Técnicas (Laravel):**

- [ ] **Modelo `Paciente`:** Agregar relación `hasMany Expediente`. Agregar método `historialMultidisciplinario()` que retorne consultas ordenadas cronológicamente, filtradas por AreaScope.
- [ ] **Controlador `HistorialController`:** Método `show(Paciente $paciente)` que recopile consultas de todos los expedientes accesibles.
- [ ] **API Resource `HistorialResource`:** Empaquetar datos del historial para el frontend, filtrando campos sensibles.
- [ ] **Middleware:** Reutilizar `EnforceAreaScope` + bloqueo de `sysadmin`.
- [ ] **Rutas:** `GET /pacientes/{paciente}/historial`. Middleware `auth` + `enforce_area_scope`.
- [ ] **Vista:** `Pages/Historial/Show.vue` — timeline de consultas agrupadas por expediente/área, integrado a `ClinicalLayout`.
- [ ] **Tests:** Feature test cubriendo: historial completo en área propia, filtrado cross-área, bloqueo sysadmin.

---

### US-10: Actualizar o cerrar un expediente clínico

**Épica:** PAN-3 · **Puntos:** 5 · **Prioridad:** Media

**Descripción:**  
"Como Coordinador de Área, quiero actualizar el estado de un expediente o cerrarlo formalmente, para reflejar que el paciente ha concluido su proceso de atención en el área."

**Criterios de Aceptación:**

```gherkin
Dado que el Coordinador de Área está autenticado
  Y el expediente pertenece a su área
  Y el expediente está en estado "abierto" o "en_atencion"
Cuando el Coordinador cambia el estado a "cerrado"
  Y proporciona un motivo de cierre
Entonces el expediente se actualiza a estado "cerrado"
  Y se registra la fecha de cierre y el motivo (cifrado)
  Y el evento queda registrado en el audit log
  Y no se pueden registrar nuevas consultas en el expediente

Dado que un Especialista (no Coordinador) intenta cerrar un expediente
Cuando envía la solicitud
Entonces recibe HTTP 403 Forbidden

Dado que el expediente ya está en estado "cerrado"
Cuando se intenta cerrar nuevamente
Entonces el sistema rechaza la operación
```

**Tareas Técnicas (Laravel):**

- [ ] **Migración:** Agregar a `expedientes`: `motivo_cierre` (TEXT cifrado), `fecha_cierre` (TIMESTAMPTZ), `cerrado_por_profesional_id` (FK).
- [ ] **Modelo `Expediente`:** Aplicar `EncryptedFieldCast` a `motivo_cierre`. Agregar relación `belongsTo Profesional` (cierre).
- [ ] **Controlador `ExpedienteController`:** Método `update()` para cambio de estado. Validar transiciones de estado válidas. Registrar evento en log de auditoría (ej. `spatie/laravel-activitylog`).
- [ ] **Form Request `ExpedienteUpdateRequest`:** Validar estado destino, motivo de cierre obligatorio al cerrar.
- [ ] **Policy `ExpedientePolicy`:** Método `cerrar()`: solo rol `area_coordinator` del área correspondiente.
- [ ] **Rutas:** `PUT /expedientes/{expediente}`. Middleware `auth` + `enforce_area_scope` + policy.
- [ ] **Vista:** `Pages/Expedientes/Edit.vue` — formulario de actualización/cierre con campo de motivo.
- [ ] **Tests:** Feature test cubriendo: cierre exitoso, rechazo por rol, rechazo de doble cierre.

---

### US-11: Asignar la próxima cita durante la atención

**Épica:** PAN-4 · **Puntos:** 5 · **Prioridad:** Alta

**Descripción:**  
"Como Especialista, quiero asignar la próxima cita al paciente al finalizar una consulta, para garantizar la continuidad de la atención."

**Criterios de Aceptación:**

```gherkin
Dado que el Especialista está autenticado
  Y acaba de registrar o está visualizando una consulta de un expediente en su área
Cuando asigna una nueva cita especificando fecha, hora y motivo
Entonces se crea un registro de cita vinculado al expediente y al especialista
  Y la cita queda en estado "programada"
  Y el evento queda registrado en el audit log

Dado que la fecha/hora solicitada ya tiene una cita programada para el mismo especialista
Cuando intenta crear la cita
Entonces el sistema rechaza indicando conflicto de horario

Dado que el expediente está en estado "cerrado"
Cuando el Especialista intenta asignar una cita
Entonces el sistema rechaza la operación
```

**Tareas Técnicas (Laravel):**

- [ ] **Migración:** Crear tabla `citas`: `id` (UUID), `expediente_id` (`foreignUuid`), `profesional_id` (`foreignUuid`), `area_id` (`foreignUuid` - desnormalizado para optimización de queries), `fecha_hora` (TIMESTAMPTZ), `motivo` (TEXT cifrado), `estado` (ENUM: `programada`, `asistio`, `no_asistio`, `cancelada`, `reprogramada`), timestamps, soft deletes.
- [ ] **Modelo `Cita`:** Relaciones `belongsTo Expediente`, `belongsTo Profesional`, `belongsTo Area`. `EncryptedFieldCast` en `motivo`. `SoftDeletes`. Agregar trait para Audit Log (`LogsActivity`).
- [ ] **Controlador `CitaController`:** Método `store()`. Validar que no haya conflicto de horario. Registrar evento de auditoría.
- [ ] **Form Request `CitaStoreRequest`:** Validar fecha futura, horario sin conflictos, expediente no cerrado.
- [ ] **Policy `CitaPolicy`:** Solo especialistas del área del expediente.
- [ ] **Rutas:** `POST /expedientes/{expediente}/citas`. Middleware `auth` + `enforce_area_scope`.
- [ ] **Vista:** `Pages/Citas/Create.vue` — formulario con date-time picker y motivo.
- [ ] **Tests:** Feature test cubriendo: creación exitosa, conflicto de horario, rechazo en expediente cerrado.

---

### US-12: Registrar asistencia o ausencia a una cita

**Épica:** PAN-4 · **Puntos:** 5 · **Prioridad:** Alta

**Descripción:**  
"Como Especialista, quiero registrar si el paciente asistió o no a la cita programada, para mantener un registro de adherencia al tratamiento."

**Criterios de Aceptación:**

```gherkin
Dado que existe una cita en estado "programada"
  Y el Especialista asignado está autenticado
  Y la fecha de la cita ya se cumplió o es el día actual
Cuando el Especialista marca la cita como "asistió" o "no asistió"
Entonces el estado de la cita se actualiza
  Y se registra la fecha/hora del registro de asistencia
  Y el evento queda registrado en el audit log

Dado que la cita NO está en estado "programada"
Cuando se intenta registrar asistencia
Entonces el sistema rechaza la operación

Dado que un Especialista que NO es el asignado a la cita intenta registrar asistencia
Cuando envía la solicitud
Entonces recibe HTTP 403 Forbidden
```

**Tareas Técnicas (Laravel):**

- [ ] **Migración:** Agregar a `citas`: `fecha_registro_asistencia` (TIMESTAMPTZ nullable), `registrado_por_profesional_id` (`foreignUuid` nullable).
- [ ] **Modelo `Cita`:** Agregar método `registrarAsistencia($estado)` con validación de transición de estado.
- [ ] **Controlador `CitaController`:** Método `registrarAsistencia()`. Validar que la fecha de la cita ya pasó o es hoy. Registrar cambio en audit log.
- [ ] **Form Request `CitaAsistenciaRequest`:** Validar estado destino (`asistio` o `no_asistio`), fecha válida.
- [ ] **Policy `CitaPolicy`:** Método `registrarAsistencia()`: solo el especialista asignado.
- [ ] **Rutas:** `PATCH /citas/{cita}/asistencia`. Middleware `auth` + policy.
- [ ] **Vista:** Botones de acción en `Pages/Citas/Show.vue` o `Index.vue` para marcar asistencia.
- [ ] **Tests:** Feature test cubriendo: registro exitoso, rechazo por estado, rechazo por especialista no asignado.

---

### US-13: Reprogramar o cancelar una cita

**Épica:** PAN-4 · **Puntos:** 5 · **Prioridad:** Media

**Descripción:**  
"Como Especialista, quiero reprogramar o cancelar una cita pendiente, para gestionar cambios en la agenda de atención."

**Criterios de Aceptación:**

```gherkin
Dado que existe una cita en estado "programada"
  Y el Especialista asignado está autenticado
Cuando el Especialista reprograma la cita proporcionando nueva fecha/hora
Entonces la cita original se marca como "reprogramada"
  Y se crea una nueva cita con la nueva fecha/hora en estado "programada"
  Y la nueva cita mantiene la referencia al expediente original
  Y ambos eventos quedan registrados en el audit log

Dado que existe una cita en estado "programada"
Cuando el Especialista cancela la cita proporcionando un motivo
Entonces la cita se marca como "cancelada"
  Y se registra el motivo de cancelación
  Y el evento queda registrado en el audit log

Dado que la cita NO está en estado "programada"
Cuando se intenta reprogramar o cancelar
Entonces el sistema rechaza la operación
```

**Tareas Técnicas (Laravel):**

- [ ] **Migración:** Agregar a `citas`: `motivo_cancelacion` (TEXT cifrado, nullable), `cita_origen_id` (`foreignUuid` nullable, FK self-referencing para reprogramación).
- [ ] **Modelo `Cita`:** Relación `belongsTo Cita` (origen). `EncryptedFieldCast` en `motivo_cancelacion`. Método `reprogramar()` y `cancelar()`.
- [ ] **Controlador `CitaController`:** Métodos `reprogramar()` y `cancelar()`. Transacción DB para reprogramación (marcar antigua + crear nueva). Registrar eventos en audit log.
- [ ] **Form Request `CitaReprogramarRequest`:** Validar nueva fecha futura, sin conflicto de horario.
- [ ] **Form Request `CitaCancelarRequest`:** Validar motivo obligatorio.
- [ ] **Policy `CitaPolicy`:** Métodos `reprogramar()` y `cancelar()`: solo especialista asignado o coordinador del área.
- [ ] **Rutas:** `PATCH /citas/{cita}/reprogramar`, `PATCH /citas/{cita}/cancelar`. Middleware `auth` + policy.
- [ ] **Vista:** Modal o página con formulario de reprogramación / cancelación.
- [ ] **Tests:** Feature test cubriendo: reprogramación exitosa (cita antigua + nueva), cancelación con motivo, rechazo por estado.

---

### US-14: Consultar citas asignadas e historial de asistencia

**Épica:** PAN-4 · **Puntos:** 5 · **Prioridad:** Media

**Descripción:**  
"Como Especialista o Coordinador de Área, quiero consultar las citas programadas y el historial de asistencia de los pacientes de mi área, para planificar la agenda y dar seguimiento a la adherencia."

**Criterios de Aceptación:**

```gherkin
Dado que el Especialista está autenticado
Cuando accede a la vista de citas
Entonces visualiza sus citas programadas ordenadas cronológicamente
  Y cada cita muestra: fecha/hora, paciente (código de privacidad), estado, motivo
  Y puede filtrar por estado (programada, asistió, no asistió, cancelada, reprogramada)

Dado que el Coordinador de Área está autenticado
Cuando accede a la vista de citas
Entonces visualiza las citas de TODOS los especialistas de su(s) área(s)
  Y puede filtrar por especialista, estado y rango de fechas

Dado que el usuario tiene rol "sysadmin"
Cuando intenta acceder a la vista de citas clínicas
Entonces recibe HTTP 403 Forbidden

Dado que el AreaScope global está activo
Cuando cualquier consulta de citas se ejecuta
Entonces solo se retornan citas vinculadas a expedientes de las áreas autorizadas del usuario
```

**Tareas Técnicas (Laravel):**

- [ ] **Modelo `Cita`:** Aplicar Global Scope `AreaScope` apuntando directamente a la columna local `area_id` para evitar subconsultas (WHERE EXISTS) hacia la tabla de expedientes. Agregar scopes locales: `programadas()`, `porFecha()`, `porEstado()`.
- [ ] **Controlador `CitaController`:** Método `index()` con filtros. Para coordinadores: cargar citas de todos los profesionales del área. Paginación.
- [ ] **API Resource `CitaResource`:** Empaquetar datos para el frontend: código de privacidad del paciente (no nombre), fecha, estado, motivo descifrado.
- [ ] **Middleware:** Reutilizar `EnforceAreaScope` + bloqueo de `sysadmin`.
- [ ] **Rutas:** `GET /citas`. Middleware `auth` + `enforce_area_scope`.
- [ ] **Vista:** `Pages/Citas/Index.vue` — tabla con filtros de estado/fecha/especialista, paginación, integrado a `ClinicalLayout`. Diseño Nord.
- [ ] **Tests:** Feature test cubriendo: listado propio (especialista), listado completo de área (coordinador), filtros, bloqueo sysadmin, filtrado cross-área.

---

## Resumen de Esfuerzo

| HU    | Título                                              | Pts | Prioridad |
|-------|-----------------------------------------------------|-----|-----------|
| US-06b| Migrar infraestructura criptográfica a Clave por Área (Deuda C-09) | 8 | Crítica   |
| US-07 | Derivar un paciente a un área clínica               | 8   | Alta      |
| US-08 | Registrar una consulta clínica                      | 8   | Alta      |
| US-09 | Consultar el historial multidisciplinario            | 8   | Media     |
| US-10 | Actualizar o cerrar un expediente clínico            | 5   | Media     |
| US-11 | Asignar la próxima cita durante la atención          | 5   | Alta      |
| US-12 | Registrar asistencia o ausencia a una cita           | 5   | Alta      |
| US-13 | Reprogramar o cancelar una cita                      | 5   | Media     |
| US-14 | Consultar citas asignadas e historial de asistencia  | 5   | Media     |
| **Total** |                                                 | **57** |        |

## Orden de Implementación Sugerido

```
US-06b (Deuda C-09: Cifrado multi-especialista)
  └── US-07 (Derivación)
        └── US-08 (Consulta clínica)
              ├── US-09 (Historial multidisciplinario)
              ├── US-10 (Cierre de expediente)
              └── US-11 (Asignación de citas)
                    ├── US-12 (Registro de asistencia)
                    ├── US-13 (Reprogramar/cancelar cita)
                    └── US-14 (Consultar citas e historial)
```

---

*Fin del documento — Sprint 2 Backlog v1.0*  
*Generado a partir de: Reestructuración.md, ROADMAP.md, DOCUMENTACION.md y README.md*
