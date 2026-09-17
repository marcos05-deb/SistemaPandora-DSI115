# Sprint 2 — Nuevos hallazgos por resolver

> **Histórico.** Estado revisado en `366b2e8`. La revisión técnica más reciente está en [`SPRINT_2_REVISION_5935126_PENDIENTES.md`](./SPRINT_2_REVISION_5935126_PENDIENTES.md).

## 1. Propósito del documento

Este documento reúne únicamente los pendientes nuevos encontrados después de actualizar y revisar la rama `correcciones-sprint-2` contra los criterios de aceptación de Jira para el Sprint 2.

No sustituye a `SPRINT_2_CORRECCIONES_JIRA.md`. Su objetivo es servir como lista de cierre antes de declarar el Sprint 2 completo y presentar el MVP.

## 2. Estado revisado

- Rama: `correcciones-sprint-2`
- Commit revisado: `366b2e8`
- Estado remoto: sincronizado con `origin/correcciones-sprint-2`
- Historias del Sprint 2: PAN-14 a PAN-21, equivalentes a HU-07 a HU-14
- Pruebas funcionales: 128 aprobadas y 2 incompletas
- Pruebas unitarias: 8 aprobadas
- Total comprobado: 136 pruebas aprobadas, 592 aserciones y 2 pruebas incompletas
- Compilación de la interfaz: exitosa
- Sintaxis PHP: sin errores

## 3. Resumen de pendientes

| ID | Historia | Prioridad | Hallazgo | Impacto |
|---|---|---:|---|---|
| NH-01 | PAN-18 / HU-11 | Crítica | El servidor acepta una consulta anterior como origen de una nueva cita | Incumplimiento directo del flujo “desde consulta activa” |
| NH-02 | PAN-15 / HU-08 | Alta | La autorización para registrar consultas utiliza solo el área principal | Puede bloquear a un especialista autorizado en múltiples áreas |
| NH-03 | PAN-19 / HU-12 | Alta | La ausencia dispara un evento, pero no alimenta ninguna estadística real | El criterio de estadísticas preventivas queda incompleto |
| NH-04 | PAN-17 / HU-10 | Alta | Hay dos pruebas generales de auditoría todavía incompletas | No queda demostrada la trazabilidad e inmutabilidad completa |
| NH-05 | PAN-18 / HU-11 y PAN-20 / HU-13 | Media | La prevención de solapamientos concurrentes no cubre de forma robusta todos los intervalos | Dos solicitudes simultáneas podrían generar citas traslapadas |
| NH-06 | PAN-21 / HU-14 | Baja | La vista semanal agrupa solamente los elementos de la página actual | Una semana con más de 30 citas puede mostrarse incompleta |

## 4. NH-01 — Exigir que la cita provenga de la consulta activa

### Historia relacionada

PAN-18 / HU-11 — Asignar próxima cita.

### Problema actual

La interfaz obtiene la consulta más reciente mediante `Consulta::activaParaExpediente()` y envía ese identificador al crear la cita. Sin embargo, la validación del servidor solo comprueba lo siguiente:

- Que `consulta_id` exista.
- Que la consulta pertenezca al expediente indicado.
- Que el expediente no esté cerrado.

Por lo tanto, una petición manipulada puede enviar el identificador de cualquier consulta anterior del mismo expediente y la cita sería aceptada.

### Archivos implicados

- `app/Http/Requests/CitaStoreRequest.php`
- `app/Models/Consulta.php`
- `app/Http/Controllers/CitaController.php`
- `tests/Feature/Cita/AsignarProximaCitaTest.php`

### Corrección requerida

En la validación del servidor se debe obtener la consulta activa del expediente y comparar su identificador con `consulta_id`.

La creación debe rechazarse si:

- No existe una consulta activa.
- La consulta enviada pertenece al expediente, pero no es la consulta activa.
- La consulta enviada pertenece a otro expediente.
- El expediente está cerrado.

No se debe confiar únicamente en que la interfaz envíe el identificador correcto.

### Criterios para darlo por terminado

- Una cita puede crearse utilizando la consulta activa del expediente.
- Una consulta anterior del mismo expediente produce un error de validación.
- Una consulta de otro expediente produce un error de validación.
- Un expediente sin consulta no permite crear una cita.
- Un expediente cerrado no permite crear una cita.
- El mensaje mostrado al usuario explica que debe agendar desde la consulta activa.

### Pruebas nuevas necesarias

1. Crear dos consultas en un expediente e intentar agendar con la primera; debe rechazarse.
2. Agendar con la segunda y más reciente consulta; debe aprobarse.
3. Enviar manualmente una consulta de otro expediente; debe rechazarse.
4. Intentar agendar sin consultas registradas; debe rechazarse.

## 5. NH-02 — Corregir la autorización multiárea al registrar consultas

### Historia relacionada

PAN-15 / HU-08 — Registrar consulta.

### Problema actual

`ConsultaPolicy::create()` compara el área del expediente únicamente con `user->profesional->area_id`.

El sistema ya contempla especialistas con varias áreas autorizadas mediante `user->areas()`. Como consecuencia, un especialista autorizado en dos áreas puede acceder a expedientes de ambas mediante `AreaScope`, pero la política de consultas puede rechazar el registro en su segunda área.

### Archivos implicados

- `app/Policies/ConsultaPolicy.php`
- `app/Models/Especialista.php`
- `app/Models/Scopes/AreaScope.php`
- `tests/Feature/Consulta/ConsultaTest.php`
- `tests/Feature/HU03/AreaScopeTest.php`

### Corrección requerida

La política debe comprobar que el área del expediente se encuentre dentro de todas las áreas autorizadas del especialista, utilizando la relación `areas()`.

También debe conservar las restricciones existentes:

- Solo especialista o coordinador de área.
- El expediente debe estar activo.
- El expediente no puede estar cerrado.
- Un usuario sin autorización para el área debe recibir una respuesta 403.

### Criterios para darlo por terminado

- Un especialista puede registrar consultas en cualquiera de sus áreas autorizadas.
- Un coordinador puede registrar consultas únicamente en sus áreas autorizadas.
- Un especialista de otra área no puede registrar la consulta.
- Un referente psicosocial no puede registrar una consulta clínica.
- El expediente cerrado continúa siendo de solo lectura.

### Pruebas nuevas necesarias

1. Especialista multiárea registra una consulta en su primera área.
2. El mismo especialista registra una consulta en su segunda área.
3. El especialista intenta registrar en una tercera área no autorizada; debe recibir 403.
4. Repetir el escenario con un coordinador multiárea.

## 6. NH-03 — Incorporar las ausencias a estadísticas preventivas

### Historia relacionada

PAN-19 / HU-12 — Registrar asistencia o ausencia.

### Problema actual

Cuando una cita se marca como ausente, el modelo dispara `CitaAusenciaRegistrada`. Actualmente no existe un listener, servicio, contador o proyección que procese el evento y lo convierta en información estadística.

La prueba existente solo confirma que el evento fue disparado; no demuestra que las estadísticas preventivas hayan cambiado.

### Archivos implicados

- `app/Models/Cita.php`
- `app/Events/CitaAusenciaRegistrada.php`
- Configuración de eventos de Laravel
- Módulo o servicio de estadísticas preventivas que se defina
- `tests/Feature/Cita/AsistenciaTest.php`

### Corrección requerida

Implementar un consumidor del evento que actualice una fuente consultable de estadísticas preventivas. La solución puede ser una consulta agregada confiable sobre las citas o una proyección específica, pero debe existir un resultado real y verificable.

Como mínimo debe permitir obtener:

- Cantidad de ausencias por paciente.
- Cantidad de ausencias dentro de un período.
- Área y profesional asociados.
- Fecha del registro de ausencia.

Si el MVP todavía no incluye una pantalla estadística, debe quedar disponible un servicio o consulta probada que pueda consumir el siguiente módulo.

### Criterios para darlo por terminado

- Marcar una cita como `ausente` cambia el resultado de las estadísticas preventivas.
- Marcar una cita como `asistida` no aumenta el contador de ausencias.
- No se cuenta dos veces la misma cita.
- Las estadísticas respetan área y permisos.
- El proceso conserva la relación con paciente, profesional y fecha.

### Pruebas nuevas necesarias

1. Registrar una ausencia y verificar que el contador aumente una vez.
2. Registrar una asistencia y verificar que el contador de ausencias no cambie.
3. Intentar repetir el registro y comprobar que no se duplique.
4. Consultar estadísticas desde otra área y comprobar que no se expongan datos.

## 7. NH-04 — Completar las garantías de auditoría

### Historia relacionada

Principalmente PAN-17 / HU-10, con impacto transversal en las historias clínicas del Sprint 2.

### Problema actual

La suite funcional termina correctamente, pero reporta dos pruebas incompletas en `AuditLoggingTest`:

- Registro de auditoría por cada acceso a información clínica protegida.
- Inmutabilidad de los registros de auditoría.

Las pruebas específicas de derivación, consulta, cita y expediente comprueban varios eventos de auditoría. No obstante, mientras esas dos pruebas generales permanezcan incompletas, no queda demostrada la cobertura transversal exigida para la trazabilidad clínica.

### Archivos implicados

- `tests/Feature/Compliance/AuditLoggingTest.php`
- Modelos que utilizan auditoría
- Middleware o servicio encargado de registrar accesos a información clínica
- Configuración y permisos de la tabla de auditoría

### Corrección requerida

- Implementar o habilitar el registro de acceso a información clínica protegida.
- Impedir que el usuario normal de la aplicación modifique o elimine auditorías.
- Sustituir las pruebas marcadas como incompletas por pruebas ejecutables.
- Verificar que el motivo de cambio, autor y fecha permanezcan asociados a cada actualización.
- Evitar guardar contenido clínico sensible en texto plano dentro de los registros de auditoría.

### Criterios para darlo por terminado

- La lectura de información clínica genera el registro de acceso correspondiente.
- Una actualización conserva valores anteriores, autor, fecha y motivo.
- Un usuario de aplicación no puede modificar ni eliminar una auditoría.
- Los valores sensibles se almacenan cifrados o enmascarados.
- La suite termina sin pruebas incompletas relacionadas con auditoría.

### Pruebas nuevas necesarias

1. Acceder a un expediente y comprobar el registro de lectura clínica.
2. Actualizar un expediente y comprobar valores anteriores, nuevos valores, autor, fecha y motivo.
3. Intentar editar una auditoría con el usuario de aplicación; debe fallar.
4. Intentar eliminar una auditoría con el usuario de aplicación; debe fallar.
5. Verificar directamente en base de datos que no haya información clínica sensible en texto plano.

## 8. NH-05 — Fortalecer la prevención de solapamientos concurrentes

### Historias relacionadas

- PAN-18 / HU-11 — Asignar próxima cita.
- PAN-20 / HU-13 — Reprogramar o cancelar cita.

### Problema actual

La aplicación consulta si existe un intervalo ocupado antes de guardar. También existe protección para horarios exactamente iguales. Sin embargo, dos solicitudes concurrentes con horas diferentes pero intervalos solapados podrían superar la consulta antes de que cualquiera de las dos confirme su transacción.

Ejemplo:

- Solicitud A intenta crear una cita a las 10:00.
- Solicitud B intenta crear una cita a las 10:30.
- Ambas verifican disponibilidad casi al mismo tiempo.
- Las dos podrían guardarse aunque la duración sea de 60 minutos.

### Archivos implicados

- `app/Models/Cita.php`
- `app/Http/Controllers/CitaController.php`
- Migraciones de índices y restricciones de citas
- `tests/Feature/Cita/AsignarProximaCitaTest.php`
- `tests/Feature/Cita/ReprogramarCancelarCitaTest.php`

### Corrección requerida

Agregar una protección transaccional o una restricción compatible con PostgreSQL que impida intervalos superpuestos para el mismo profesional cuando la cita se encuentre en estado programada.

La solución debe funcionar tanto al crear como al reprogramar una cita y debe devolver un error entendible cuando otro proceso haya ocupado el horario.

### Criterios para darlo por terminado

- No pueden existir dos citas programadas solapadas para el mismo profesional.
- La protección funciona aunque las solicitudes sean simultáneas.
- Profesionales distintos pueden utilizar el mismo horario.
- Las citas canceladas o reprogramadas no mantienen bloqueado el horario anterior.
- El usuario recibe un mensaje de horario no disponible.

### Pruebas nuevas necesarias

1. Dos solicitudes concurrentes con la misma hora: solo una debe aprobarse.
2. Dos solicitudes concurrentes con horas distintas pero duración solapada: solo una debe aprobarse.
3. Dos profesionales diferentes en el mismo horario: ambas deben aprobarse.
4. Reprogramar y comprobar que el horario original queda disponible.

## 9. NH-06 — Evitar semanas incompletas por paginación

### Historia relacionada

PAN-21 / HU-14 — Consultar citas e historial de asistencia.

### Problema actual

La consulta pagina los resultados en grupos de 30 y posteriormente agrupa en memoria los elementos de la página actual para construir la vista semanal.

Si una semana contiene más de 30 citas autorizadas, la agrupación semanal puede aparentar que la semana está completa aunque existan citas en las páginas siguientes.

### Archivos implicados

- `app/Http/Controllers/CitaController.php`
- Pantalla de consulta de citas
- `tests/Feature/Citas/ConsultarCitasTest.php`

### Corrección requerida

Definir un comportamiento explícito para la vista semanal:

- Cargar todas las citas autorizadas de la semana, si el volumen esperado lo permite; o
- Mantener paginación, pero mostrar claramente los controles y agrupar cada página sin indicar que representa la semana completa; o
- Agrupar desde la base de datos y paginar por día o por grupo.

Para el MVP, la alternativa más sencilla es cargar todas las citas de la semana respetando los límites por área y rol.

### Criterios para darlo por terminado

- Una semana con más de 30 citas muestra todos sus registros o una paginación explícita y funcional.
- Las citas permanecen agrupadas por día.
- La navegación anterior, siguiente y hoy continúa funcionando.
- Los filtros de paciente, profesional y resultado de asistencia se conservan.
- La solución no permite acceder a citas de otra área.

### Pruebas nuevas necesarias

1. Crear al menos 31 citas en una semana y verificar que todas sean accesibles.
2. Verificar agrupación correcta por cada día.
3. Aplicar filtros sobre una semana de más de 30 citas.
4. Comprobar que un especialista solo vea sus citas y el coordinador las de sus áreas.

## 10. Orden recomendado de resolución

### Bloque obligatorio antes de declarar completo el Sprint 2

1. NH-01 — Validación real de consulta activa.
2. NH-02 — Autorización multiárea para consultas.
3. NH-03 — Procesamiento real de ausencias para estadísticas.
4. NH-04 — Auditoría sin pruebas incompletas.

### Bloque de robustez antes de producción

5. NH-05 — Protección concurrente contra intervalos solapados.

### Mejora de experiencia y volumen

6. NH-06 — Vista semanal completa cuando hay más de 30 citas.

## 11. Definición de terminado del Sprint 2

El Sprint 2 puede considerarse completo cuando:

- Las ocho historias PAN-14 a PAN-21 cumplen sus criterios desde la interfaz y mediante peticiones directas al servidor.
- Todos los permisos se validan en el servidor y respetan múltiples áreas autorizadas.
- No es posible crear una cita desde una consulta anterior.
- Las ausencias producen un resultado estadístico real y verificable.
- La auditoría clínica registra acceso y cambios, es inmutable y no expone datos sensibles.
- La agenda evita conflictos incluso bajo solicitudes concurrentes.
- Las vistas diaria y semanal no omiten citas sin indicarlo.
- Las pruebas nuevas están incorporadas a la suite.
- La suite completa termina sin fallos y sin pruebas incompletas del Sprint 2.
- La compilación de producción continúa finalizando correctamente.

## 12. Evidencia que debe presentarse

Para la nueva presentación se recomienda demostrar este recorrido:

1. Referente autorizado deriva al paciente.
2. Especialista registra una consulta con fecha, notas, diagnóstico u observación y plan de atención.
3. Desde la consulta activa se crea una cita futura sin conflicto.
4. La cita aparece en las vistas diaria y semanal.
5. Al llegar la hora se registra asistencia o ausencia.
6. Una ausencia se refleja en la estadística preventiva.
7. Una cita futura se reprograma y libera el horario anterior.
8. Se actualiza el expediente mostrando la trazabilidad del cambio.
9. Se cierra el expediente con resultado final y fecha.
10. Se demuestra que el expediente cerrado es de solo lectura.
11. Se consulta el historial multidisciplinario con filtros de período, área y tipo.
12. Se demuestra que un usuario de otra área no puede acceder a la información.

---

Última revisión: 12 de septiembre de 2026.

