# Sprint 2 — Plan completo de correcciones según Jira

## 1. Propósito del documento

Este documento define todo lo que debe corregirse o completarse en la rama `dev` para que el Sprint 2 del Sistema PANDORA cumpla con las historias de usuario y criterios de aceptación registrados en Jira.

La comparación se realizó contra la rama remota `dev` en el commit:

```text
02596122113321bb39a0b5481b40bd3582b91091
```

Fecha de revisión: 12 de septiembre de 2026.

Este documento no cambia el alcance de Jira. Cuando el backlog interno del repositorio y Jira difieren, se considera Jira como la fuente de verdad para la aceptación del Sprint 2.

## 2. Alcance real del Sprint 2 en Jira

| Incidencia Jira | Historia funcional | Puntos | Prioridad Jira | Estado encontrado |
|---|---|---:|---|---|
| [PAN-14](https://ues-team-nfbnyzvr.atlassian.net/browse/PAN-14) | HU-07 — Derivar un paciente a un área clínica | 8 | Alta | Por hacer / 0 % |
| [PAN-15](https://ues-team-nfbnyzvr.atlassian.net/browse/PAN-15) | HU-08 — Registrar una consulta clínica | 8 | Alta | Por hacer / 0 % |
| [PAN-16](https://ues-team-nfbnyzvr.atlassian.net/browse/PAN-16) | HU-09 — Consultar el historial multidisciplinario | 8 | Media | Por hacer / 0 % |
| [PAN-17](https://ues-team-nfbnyzvr.atlassian.net/browse/PAN-17) | HU-10 — Actualizar o cerrar un expediente clínico | 5 | Media | Por hacer / 0 % |
| [PAN-18](https://ues-team-nfbnyzvr.atlassian.net/browse/PAN-18) | HU-11 — Asignar la próxima cita durante la atención | 5 | Alta | Por hacer / 0 % |
| [PAN-19](https://ues-team-nfbnyzvr.atlassian.net/browse/PAN-19) | HU-12 — Registrar asistencia o ausencia a una cita | 5 | Alta | Por hacer / 0 % |
| [PAN-20](https://ues-team-nfbnyzvr.atlassian.net/browse/PAN-20) | HU-13 — Reprogramar o cancelar una cita | 5 | Media | Por hacer / 0 % |
| [PAN-21](https://ues-team-nfbnyzvr.atlassian.net/browse/PAN-21) | HU-14 — Consultar citas asignadas e historial de asistencia | 5 | Media | Por hacer / 0 % |
| **Total** | **8 historias** | **49** |  |  |

### Aclaración sobre la numeración

`PAN-18`, `PAN-19` y `PAN-20` son claves de incidencias de Jira. En el Sprint 2 corresponden a HU-11, HU-12 y HU-13; no se refieren a las historias funcionales HU-18, HU-19 y HU-20.

### Diferencia con el backlog interno

El archivo `sprint_2_backlog.md` del repositorio declara 57 puntos porque agrega US-06b, la migración a cifrado compartido por área. Esa mejora técnica es un prerrequisito importante y ya tiene implementación en `dev`, pero no aparece como una de las ocho historias del Sprint 2 consultadas en Jira.

## 3. Resultado general de la revisión

La rama `dev` contiene una implementación base para las ocho historias. Sin embargo, ninguna cumple todavía todos los criterios de Jira de extremo a extremo.

Validaciones realizadas:

- La interfaz compila correctamente con `npm run build`.
- Los 160 archivos PHP de la rama revisada no presentan errores de sintaxis.
- Existen pruebas automatizadas para los flujos base de las ocho historias.
- No fue posible ejecutar la suite completa porque el entorno de pruebas requiere PostgreSQL en Docker y Docker Desktop no estaba activo durante la revisión.
- Algunas pruebas actuales validan comportamientos que contradicen Jira; por ejemplo, se prueba como válido registrar asistencia antes de la hora de una cita cuando ocurre el mismo día.

## 4. Niveles de prioridad de corrección

### P0 — Bloqueante del MVP

Una brecha P0 compromete autorización, integridad clínica, consistencia de datos o un criterio esencial del flujo principal. Debe resolverse antes de presentar el Sprint 2 como completo.

### P1 — Necesario para aceptar la historia

Una brecha P1 impide cumplir uno o más criterios de Jira, aunque el flujo principal ya exista. Debe resolverse antes del cierre formal del Sprint 2.

### P2 — Calidad, claridad o evidencia

Una brecha P2 no necesariamente bloquea una demostración básica, pero es necesaria para dejar la historia verificable, mantenible y correctamente documentada.

---

# 5. Historias de usuario y correcciones requeridas

## HU-07 — Derivar un paciente a un área clínica

**Jira:** [PAN-14](https://ues-team-nfbnyzvr.atlassian.net/browse/PAN-14)  
**Puntos:** 8  
**Prioridad Jira:** Alta  
**Prioridad de corrección:** P0  
**Estado técnico actual:** Parcial

### Historia de usuario registrada en Jira

> Como referente psicosocial, necesito derivar un paciente a un área clínica para iniciar su atención especializada.

### Criterios de aceptación de Jira

1. El referente solo puede derivar pacientes que estén bajo su responsabilidad o para los que tenga autorización vigente.
2. La derivación exige seleccionar el área clínica y registrar un motivo antes de guardarse.
3. Al confirmar la derivación, el sistema crea un único expediente clínico activo para esa área y evita duplicados.

### Dependencias indicadas en Jira

- HU-04 y HU-06.
- Paciente existente.
- Identificación segura del paciente.

### Flujo funcional esperado

1. El referente psicosocial inicia sesión.
2. Busca o selecciona un paciente existente mediante el mecanismo autorizado.
3. El sistema verifica que el paciente está bajo su responsabilidad o que existe una autorización vigente.
4. El referente abre la acción de derivación.
5. Selecciona un área clínica de destino.
6. Registra el motivo de la derivación.
7. Confirma la operación.
8. El backend vuelve a validar autorización, área, motivo y ausencia de un expediente activo en esa área.
9. Se crea un expediente en estado `abierto` y se registra el referente, la fecha y el motivo.
10. La operación queda en auditoría.
11. Los especialistas autorizados del área pueden ver el nuevo expediente.

### Lo que ya existe en `dev`

- Ruta y servicio para derivar pacientes.
- Validación del área de destino.
- Creación transaccional del expediente.
- Registro del profesional derivante y fecha de derivación.
- Bloqueo por rol para usuarios que no son referentes psicosociales.
- Validación básica contra un expediente duplicado en estado `abierto`.
- Pruebas de derivación exitosa, duplicado abierto y acceso por rol.

### Brechas y correcciones requeridas

#### P0 — Autorización sobre el paciente

- La autorización actual comprueba el rol de referente, pero no verifica que el paciente esté bajo su responsabilidad.
- Implementar una Policy o regla de dominio que valide `creado_por_profesional_id` o una autorización vigente explícita.
- No depender únicamente de que el usuario pueda abrir la pantalla; la validación debe repetirse en el backend.
- Agregar una prueba donde otro referente intenta derivar un paciente ajeno y recibe 403.

#### P0 — Duplicados de expedientes activos

- La validación actual solo busca expedientes con estado `abierto`.
- Un expediente cambia a `en_atencion` al registrar una consulta; en ese momento la implementación actual puede permitir otro expediente activo en la misma área.
- Considerar activos tanto `abierto` como `en_atencion`.
- Agregar una restricción de base de datos o mecanismo transaccional que evite condiciones de carrera.
- Agregar una prueba de duplicado cuando el expediente existente está `en_atencion`.

#### P0 — Motivo de derivación

- Agregar un campo obligatorio `motivo_derivacion`.
- Definirlo como dato clínico sensible y almacenarlo cifrado.
- Incorporarlo en migración, modelo, formulario, Request, servicio, auditoría y pantalla de detalle.
- Validar longitud mínima y máxima.

#### P1 — Evidencia de auditoría

- Verificar con una prueba explícita que la creación registra autor, paciente, área, fecha y motivo.
- Evitar incluir el motivo clínico en texto plano dentro de logs técnicos.

### Pruebas mínimas de aceptación

- Referente responsable deriva correctamente.
- Referente con autorización vigente deriva correctamente.
- Referente sin relación con el paciente recibe 403.
- Área inexistente genera error de validación.
- Motivo vacío genera error de validación.
- Motivo queda cifrado en la base de datos.
- Expediente `abierto` duplicado es rechazado.
- Expediente `en_atencion` duplicado es rechazado.
- Dos solicitudes simultáneas no crean duplicados.
- Usuario con otro rol recibe 403.
- Auditoría registra la derivación.

### Definición de terminado

- Los tres criterios de Jira pasan en pruebas automatizadas y prueba manual.
- El formulario muestra errores comprensibles.
- No existe forma de duplicar expedientes activos por interfaz ni por solicitud directa.
- El motivo nunca queda almacenado en texto plano.

---

## HU-08 — Registrar una consulta clínica

**Jira:** [PAN-15](https://ues-team-nfbnyzvr.atlassian.net/browse/PAN-15)  
**Puntos:** 8  
**Prioridad Jira:** Alta  
**Prioridad de corrección:** P0/P1  
**Estado técnico actual:** Parcial avanzado

### Historia de usuario registrada en Jira

> Como especialista, necesito registrar una consulta clínica para documentar la atención brindada al paciente.

### Criterios de aceptación de Jira

1. El especialista solo puede registrar consultas dentro de su área clínica y en expedientes autorizados.
2. La consulta exige fecha, notas clínicas, diagnóstico u observación y plan de atención.
3. Al guardarse, la información sensible queda cifrada y asociada al autor y a la fecha de creación.

### Dependencias indicadas en Jira

- HU-03 y HU-07.
- Permisos por rol.
- Expediente activo.

### Flujo funcional esperado

1. El especialista abre un expediente activo de su área.
2. El sistema valida área, rol, autorización y estado del expediente.
3. El especialista inicia una nueva consulta.
4. Registra la fecha de atención, notas clínicas, diagnóstico u observación y plan de atención.
5. Confirma el registro.
6. El backend valida nuevamente los permisos y campos obligatorios.
7. La consulta se almacena cifrada, vinculada al expediente, profesional y fecha.
8. Si el expediente estaba `abierto`, cambia a `en_atencion`.
9. La creación queda registrada en auditoría.

### Lo que ya existe en `dev`

- Formulario para registrar consultas.
- Validación de área y expediente no cerrado mediante Policy.
- Motivo, notas, diagnóstico y técnica utilizada.
- Evaluación inicial cifrada para la primera consulta.
- Autor y fecha automática.
- Cifrado por área de los campos clínicos.
- Actualización del expediente a `en_atencion`.
- Auditoría mediante el modelo.
- Pruebas de registro, cifrado, aislamiento entre áreas y expediente cerrado.

### Brechas y correcciones requeridas

#### P0 — Confirmar autorización completa del expediente

- La pertenencia al área no debe ser la única condición si el dominio contempla asignaciones o autorizaciones específicas.
- Consolidar la comprobación en Policy y no depender solo del Global Scope.
- Probar solicitudes directas usando identificadores de expedientes no autorizados.

#### P1 — Fecha de la consulta

- Jira exige fecha como parte de la consulta.
- Actualmente se asigna `now()` automáticamente.
- Definir con Product Owner si Jira requiere una fecha seleccionable o si la fecha automática satisface el criterio.
- Si debe ser seleccionable, agregar `fecha_consulta`, validar rango y evitar fechas futuras inválidas.
- Mantener por separado `fecha_consulta` y los timestamps técnicos de creación.

#### P1 — Plan de atención en todas las consultas

- `plan_tratamiento` existe dentro de `evaluacion_inicial` y solo es obligatorio en la primera consulta.
- Jira exige un plan de atención para la consulta.
- Agregar un campo de plan de atención a cada consulta o confirmar formalmente en Jira que solo se requiere en la primera.
- El campo debe cifrarse, mostrarse en historial y quedar cubierto por pruebas.

#### P1 — Diagnóstico u observación

- La implementación exige simultáneamente diagnóstico y notas clínicas.
- Jira permite “diagnóstico u observación”.
- Definir una validación clara: al menos uno de los campos clínicos correspondientes debe existir.
- No impedir el registro cuando clínicamente aún no procede un diagnóstico definitivo.

#### P2 — Nombres y mensajes consistentes

- Alinear los nombres “plan de atención”, “plan de tratamiento”, “observación” y “notas clínicas”.
- Evitar que interfaz, base de datos, pruebas y Jira utilicen conceptos diferentes para el mismo dato.

### Pruebas mínimas de aceptación

- Especialista del área registra una consulta válida.
- Especialista de otra área no puede registrar.
- Usuario sin rol clínico recibe 403.
- Expediente cerrado rechaza nuevas consultas.
- Fecha válida queda asociada a la consulta.
- Notas y plan son obligatorios según la regla acordada.
- Se permite diagnóstico u observación conforme al criterio final.
- Todos los datos sensibles son ilegibles en consulta directa a la base.
- Otro especialista autorizado de la misma área puede descifrar.
- Especialista de otra área no puede descifrar ni consultar.
- Auditoría registra autor y fecha.

### Definición de terminado

- Los campos obligatorios coinciden exactamente con Jira.
- La consulta queda asociada al expediente, autor y fecha.
- Cifrado, autorización y auditoría tienen pruebas positivas y negativas.

---

## HU-09 — Consultar el historial multidisciplinario

**Jira:** [PAN-16](https://ues-team-nfbnyzvr.atlassian.net/browse/PAN-16)  
**Puntos:** 8  
**Prioridad Jira:** Media  
**Prioridad de corrección:** P1  
**Estado técnico actual:** Parcial

### Historia de usuario registrada en Jira

> Como profesional autorizado, necesito consultar el historial multidisciplinario para comprender la evolución integral del paciente.

### Criterios de aceptación de Jira

1. Las atenciones se muestran en orden cronológico, identificando fecha, área y profesional responsable.
2. El usuario puede filtrar el historial por período, área clínica y tipo de atención.
3. El sistema oculta cualquier área o dato para el que el usuario no tenga autorización.

### Dependencias indicadas en Jira

- HU-03 y HU-08.
- Control de acceso.
- Consultas registradas.

### Flujo funcional esperado

1. El profesional abre un paciente al que tiene acceso.
2. Selecciona “Historial multidisciplinario”.
3. El backend obtiene únicamente atenciones de áreas autorizadas.
4. La pantalla muestra fecha, área, profesional y datos clínicos permitidos.
5. El usuario puede definir fecha inicial y final.
6. Puede filtrar por área clínica dentro de sus áreas autorizadas.
7. Puede filtrar por tipo de atención.
8. Al limpiar filtros vuelve al historial autorizado completo.

### Lo que ya existe en `dev`

- Vista de línea de tiempo.
- Fecha, área y profesional por consulta.
- Ordenamiento por fecha.
- Carga de consultas a través de expedientes.
- Filtrado de áreas mediante los controles de acceso existentes.
- Bloqueo del sysadmin en pruebas.
- Pruebas para especialista, coordinador y aislamiento entre áreas.

### Brechas y correcciones requeridas

#### P1 — Filtro por período

- Agregar fecha desde y fecha hasta.
- Validar que el rango sea coherente y tenga un límite razonable.
- Mantener los filtros al paginar o navegar.

#### P1 — Filtro por área clínica

- Mostrar únicamente las áreas autorizadas del usuario.
- Validar en backend que un `area_id` enviado manualmente pertenece a sus permisos.
- No permitir que un filtro manipulado evada el AreaScope.

#### P1 — Filtro por tipo de atención

- Definir catálogo o valores permitidos de tipos de atención.
- Si actualmente todas las entradas son consultas clínicas, definir cómo se incorporarán derivaciones, cierres u otros eventos.
- No presentar un filtro sin comportamiento real.

#### P1 — Orden cronológico definido

- Jira dice “orden cronológico”, pero no especifica ascendente o descendente.
- La implementación actual utiliza el más reciente primero.
- Acordar el orden y documentarlo; opcionalmente permitir alternarlo.

#### P2 — Estados vacíos y filtros visibles

- Diferenciar “no hay historial” de “los filtros no encontraron resultados”.
- Mostrar filtros activos y opción clara para restablecerlos.

### Pruebas mínimas de aceptación

- Historial presenta fecha, área y profesional.
- Orden cronológico es estable.
- Filtro por período incluye y excluye correctamente.
- Filtro por cada área autorizada funciona.
- Área no autorizada enviada manualmente no revela datos.
- Filtro por tipo funciona.
- Combinación de filtros funciona.
- Sysadmin y usuarios no clínicos no acceden a información clínica.

### Definición de terminado

- Los tres filtros de Jira funcionan en interfaz y backend.
- Ningún parámetro permite evadir el aislamiento por área.
- La vista es demostrable con datos de varias fechas y áreas.

---

## HU-10 — Actualizar o cerrar un expediente clínico

**Jira:** [PAN-17](https://ues-team-nfbnyzvr.atlassian.net/browse/PAN-17)  
**Puntos:** 5  
**Prioridad Jira:** Media  
**Prioridad de corrección:** P0/P1  
**Estado técnico actual:** Parcial

### Historia de usuario registrada en Jira

> Como especialista, necesito actualizar o cerrar un expediente para mantener vigente el estado de la atención clínica.

### Criterios de aceptación de Jira

1. El especialista solo puede modificar expedientes de su propia área y para los que conserve autorización.
2. Cada actualización preserva la versión anterior y registra autor, fecha y motivo del cambio.
3. El cierre exige resultado final y fecha de cierre; después del cierre el expediente queda en modo de consulta.

### Dependencias indicadas en Jira

- HU-08.
- La auditoría completa se consolidará posteriormente con HU-21.

### Flujo funcional esperado

1. El especialista autorizado abre un expediente de su área.
2. Puede actualizar los campos expresamente permitidos.
3. Para actualizar, registra un motivo del cambio.
4. El sistema conserva los valores anteriores y registra autor y fecha.
5. Para cerrar, registra resultado final y fecha de cierre.
6. El sistema confirma la operación y cambia el expediente a `cerrado`.
7. Después del cierre se puede consultar el historial, pero no crear consultas, citas ni nuevas modificaciones clínicas no permitidas.

### Lo que ya existe en `dev`

- Cierre de expediente desde la interfaz.
- Motivo de cierre obligatorio y cifrado.
- Fecha de cierre automática.
- Profesional que realizó el cierre.
- Rechazo de cierre duplicado.
- Bloqueo de nuevas consultas y citas cuando está cerrado.
- Auditoría de cambios del modelo.
- Pruebas de cierre por coordinador, rechazo por especialista y doble cierre.

### Brechas y correcciones requeridas

#### P0 — Alinear el rol autorizado con Jira

- Jira asigna la capacidad al especialista autorizado.
- La implementación actual permite cerrar únicamente al coordinador del área.
- Confirmar con Product Owner si “especialista” se usa de forma genérica o si Jira realmente permite el cierre al especialista clínico.
- Ajustar Policy, interfaz y pruebas al acuerdo; no dejar una contradicción sin resolver.

#### P0 — Resultado final

- Agregar el campo obligatorio `resultado_final` para el cierre.
- Tratarlo como información clínica cifrada.
- Separarlo del “motivo del cambio” o “motivo de cierre” si representan conceptos distintos.
- Mostrarlo en la vista de expediente cerrado solamente a usuarios autorizados.

#### P1 — Actualización del expediente

- La implementación se concentra en cerrar; Jira también exige actualizar.
- Definir campos editables y transiciones válidas.
- Exigir motivo del cambio.
- Prohibir modificaciones arbitrarias de identificadores, paciente o área.

#### P1 — Preservación de la versión anterior

- Confirmar que la auditoría guarda valores anteriores y nuevos de cada campo requerido.
- Añadir pruebas que inspeccionen la auditoría, no solo que verifiquen la existencia de un registro.
- Definir si se requiere una pantalla de historial de versiones o solo persistencia para consulta posterior.

#### P1 — Modo de consulta

- Ocultar o deshabilitar acciones de escritura al cerrar.
- Mantener visibles el expediente, consultas, citas e historial autorizados.
- Reforzar el bloqueo en backend para toda ruta de escritura.

### Pruebas mínimas de aceptación

- Usuario autorizado actualiza un campo permitido con motivo.
- Se conservan valor anterior, nuevo, autor y fecha.
- Usuario de otra área recibe 403/404.
- Usuario sin autorización vigente recibe 403.
- Cierre exige resultado final y fecha.
- Resultado final se almacena cifrado.
- Cierre duplicado se rechaza.
- Expediente cerrado permanece visible en lectura.
- Expediente cerrado rechaza consultas, citas y actualizaciones no permitidas.

### Definición de terminado

- El rol autorizado está formalmente alineado con Jira.
- Actualizar y cerrar son flujos completos, no únicamente un botón de cierre.
- La versión anterior se puede demostrar mediante auditoría.
- El modo de consulta está protegido tanto en frontend como en backend.

---

## HU-11 — Asignar la próxima cita durante la atención

**Jira:** [PAN-18](https://ues-team-nfbnyzvr.atlassian.net/browse/PAN-18)  
**Puntos:** 5  
**Prioridad Jira:** Alta  
**Prioridad de corrección:** P0  
**Estado técnico actual:** Parcial

### Historia de usuario registrada en Jira

> Como profesional clínico, necesito asignar al paciente su próxima cita durante la atención para dar continuidad al tratamiento.

### Criterios de aceptación de Jira

1. La próxima cita solo puede asignarse desde una atención o consulta activa del paciente.
2. El profesional registra una fecha y hora futura acordada con el paciente, y el sistema evita conflictos con otras citas ya asignadas al mismo profesional.
3. Al confirmar, la cita queda vinculada al paciente, al profesional y a la consulta que la originó, con estado Programada.

### Dependencias indicadas en Jira

- HU-08.
- Consulta activa desde la cual se asignará la próxima cita.

### Flujo funcional esperado

1. El profesional registra o visualiza una consulta activa.
2. Desde esa consulta selecciona “Asignar próxima cita”.
3. El sistema conserva el contexto de paciente, expediente, profesional y consulta origen.
4. El profesional ingresa fecha y hora futuras.
5. El sistema comprueba que el horario no entra en conflicto con otra cita programada del profesional.
6. Al confirmar se crea la cita en estado `programada`.
7. La cita queda vinculada a la consulta origen, expediente/paciente y profesional.
8. La operación queda auditada.

### Lo que ya existe en `dev`

- Modal de agendamiento.
- Fecha y hora futuras.
- Motivo de cita.
- Asociación con expediente, área y profesional.
- Estado `programada`.
- Restricción de conflicto para el mismo profesional y fecha/hora.
- Bloqueo para expedientes cerrados.
- Pruebas de creación, conflicto y expediente cerrado.

### Brechas y correcciones requeridas

#### P0 — Relación con la consulta origen

- La tabla de citas no tiene `consulta_id`.
- Agregar una clave foránea hacia la consulta que origina la cita.
- Definir la política de borrado o conservación; una consulta con citas no debería eliminar su trazabilidad.
- Incluir la relación en modelo, Resource y auditoría.

#### P0 — Crear únicamente desde una consulta activa

- La ruta actual permite crear una cita con el expediente como único contexto.
- Incluir la consulta en la ruta o en la solicitud y validarla en backend.
- La consulta debe pertenecer al mismo expediente y paciente.
- No aceptar identificadores de consultas de otra área o expediente.

#### P1 — Definición de consulta activa

- Definir si “activa” significa recién creada, consulta del día, consulta abierta en pantalla o algún estado persistente.
- Implementar una regla verificable y documentada, no solo una condición visual.

#### P1 — Conflicto de horario real

- Actualmente se evita una coincidencia exacta de fecha/hora.
- Confirmar duración de las citas y si un solapamiento parcial también constituye conflicto.
- Si existe duración, validar intervalos y no solo timestamps iguales.

### Pruebas mínimas de aceptación

- Cita creada desde consulta activa.
- Cita queda asociada a `consulta_id`, expediente/paciente y profesional.
- Consulta ajena o de otro expediente se rechaza.
- Creación sin consulta origen se rechaza.
- Fecha pasada o presente inválida se rechaza.
- Conflicto de horario se rechaza.
- Cita inicia en `programada`.
- Expediente cerrado se rechaza.
- Auditoría registra creación y consulta origen.

### Definición de terminado

- No se puede crear una “próxima cita” sin consulta origen válida.
- La trazabilidad consulta → cita es visible y comprobable.
- El conflicto de agenda está protegido en aplicación y base de datos cuando corresponda.

---

## HU-12 — Registrar asistencia o ausencia a una cita

**Jira:** [PAN-19](https://ues-team-nfbnyzvr.atlassian.net/browse/PAN-19)  
**Puntos:** 5  
**Prioridad Jira:** Alta  
**Prioridad de corrección:** P0  
**Estado técnico actual:** Parcial

### Historia de usuario registrada en Jira

> Como profesional clínico, necesito registrar si el paciente asistió o faltó a la cita para mantener actualizado su seguimiento.

### Criterios de aceptación de Jira

1. La asistencia solo puede registrarse para una cita asignada cuya fecha y hora hayan llegado o finalizado.
2. El profesional selecciona el resultado Asistió o Ausente, y el sistema registra autor, fecha y hora de la actualización.
3. Cuando el resultado es Ausente, el evento se incorpora a las estadísticas que alimentan las alertas preventivas.

### Dependencias indicadas en Jira

- HU-11.
- Cita previamente asignada durante la atención.

### Flujo funcional esperado

1. El profesional abre una cita asignada.
2. El sistema verifica que la cita está programada y que su fecha/hora ya llegó o terminó.
3. El profesional selecciona `Asistió` o `Ausente`.
4. Confirma el resultado.
5. El sistema guarda el estado, autor y timestamp del registro.
6. Si el resultado es `Ausente`, se actualizan los datos estadísticos o se genera el evento que alimentará las alertas preventivas.
7. La operación queda auditada y ya no puede repetirse como si la cita siguiera programada.

### Lo que ya existe en `dev`

- Acciones para marcar asistencia o ausencia.
- Estados internos `asistida` y `ausente`.
- Registro del profesional y fecha/hora del cambio.
- Validación de cita en estado `programada`.
- Restricción al profesional asignado.
- Auditoría del cambio.
- Pruebas de estado, autorización y citas de días posteriores.

### Brechas y correcciones requeridas

#### P0 — Validación exacta de fecha y hora

- La implementación compara el inicio del día, no la hora de la cita.
- Esto permite marcar una cita de la tarde como asistida durante la mañana del mismo día.
- Jira exige que la fecha y hora hayan llegado o finalizado.
- Comparar contra el timestamp completo y, si aplica, contra la hora final calculada con la duración.
- Sustituir la prueba que considera válido registrar antes de la hora por una prueba que lo rechace.

#### P0 — Estadísticas y alertas preventivas

- No se encontró evidencia del evento o agregado estadístico requerido cuando el resultado es `Ausente`.
- Implementar una de estas opciones de forma explícita:
  - evento de dominio `CitaMarcadaAusente` consumido por estadísticas/alertas;
  - consulta estadística basada en las citas `ausente`;
  - tabla/agregado de seguimiento actualizado de forma transaccional.
- Aunque la alerta completa pertenezca a un sprint posterior, Sprint 2 debe dejar disponible y comprobable el dato que la alimentará.

#### P1 — Consistencia de nombres de estado

- Jira usa “Asistió” y “Ausente”.
- La implementación usa `asistida` y `ausente`; el backlog interno menciona variantes adicionales.
- Definir constantes o Enum único para base de datos, backend, frontend y pruebas.

#### P1 — Idempotencia y concurrencia

- Evitar dos actualizaciones simultáneas de una misma cita programada.
- Bloquear o verificar el estado dentro de la misma transacción en la que se actualiza.

### Pruebas mínimas de aceptación

- Cita cuya hora ya pasó permite registrar resultado.
- Cita del mismo día pero de una hora futura se rechaza.
- Cita de otro día futuro se rechaza.
- Cita no programada se rechaza.
- Profesional no asignado recibe 403.
- Se guardan autor, fecha y hora.
- Una ausencia queda disponible para estadísticas/alertas.
- Dos solicitudes simultáneas no registran resultados inconsistentes.
- Auditoría registra valores anterior y nuevo.

### Definición de terminado

- El resultado solo puede registrarse después del momento permitido.
- Cada ausencia alimenta de forma comprobable el mecanismo estadístico/preventivo.
- Estados y etiquetas son consistentes en todas las capas.

---

## HU-13 — Reprogramar o cancelar una cita

**Jira:** [PAN-20](https://ues-team-nfbnyzvr.atlassian.net/browse/PAN-20)  
**Puntos:** 5  
**Prioridad Jira:** Media  
**Prioridad de corrección:** P0/P1  
**Estado técnico actual:** Parcial

### Historia de usuario registrada en Jira

> Como profesional autorizado, necesito reprogramar o cancelar una cita asignada para reflejar cambios en la continuidad de la atención.

### Criterios de aceptación de Jira

1. Solo se pueden reprogramar o cancelar citas futuras que no estén cerradas.
2. La operación exige registrar un motivo y, al reprogramar, definir una nueva fecha y hora acordada con el paciente.
3. El horario anterior se libera y el sistema conserva el historial del cambio con usuario y fecha.

### Dependencias indicadas en Jira

- HU-11.
- Cita previamente asignada.

### Flujo funcional esperado — Reprogramación

1. El profesional abre una cita futura y modificable.
2. Selecciona “Reprogramar”.
3. Registra el motivo del cambio.
4. Selecciona una nueva fecha y hora futuras.
5. El sistema valida autorización y conflicto de agenda.
6. La cita anterior deja libre su horario y conserva su trazabilidad.
7. La nueva cita queda programada y vinculada a la anterior.
8. Se guardan usuario, fecha y motivo de la operación.

### Flujo funcional esperado — Cancelación

1. El profesional abre una cita futura y modificable.
2. Selecciona “Cancelar”.
3. Registra el motivo.
4. Confirma la operación.
5. La cita cambia a cancelada y libera el horario.
6. Se conservan usuario, fecha y motivo en historial/auditoría.

### Lo que ya existe en `dev`

- Reprogramación mediante creación de una nueva cita.
- Relación con la cita de origen.
- Cita anterior marcada `reprogramada`.
- Cancelación con motivo obligatorio.
- Nueva fecha futura para reprogramación.
- Liberación del horario mediante índice aplicable solo a citas programadas.
- Autorización para profesional asignado o coordinador del área.
- Auditoría del modelo.
- Pruebas de reprogramación, cancelación, conflicto, rol y estado.

### Brechas y correcciones requeridas

#### P0 — La cita original debe ser futura

- La validación actual se basa principalmente en que la cita esté `programada`.
- No comprueba en ambos flujos que la fecha/hora original sea futura.
- Rechazar reprogramación o cancelación si el momento de la cita ya llegó o terminó.

#### P0 — Motivo obligatorio al reprogramar

- Jira exige motivo para la operación, tanto al cancelar como al reprogramar.
- La reprogramación actual solo solicita nueva fecha/hora.
- Agregar `motivo_reprogramacion`, cifrado si contiene información clínica o personal.
- Guardar el motivo junto con autor y fecha.

#### P1 — Fecha acordada con el paciente

- La aplicación puede registrar la fecha; “acordada con el paciente” puede requerir una confirmación explícita.
- Añadir un indicador o texto de confirmación si el Product Owner considera que debe quedar como evidencia.

#### P1 — Historial visible del cambio

- Verificar que se puede reconstruir cita anterior, cita nueva, motivo, usuario y fecha.
- Definir si esa información debe mostrarse en la pantalla de historial de citas.

#### P1 — Operación transaccional y concurrente

- Bloquear la cita original durante la actualización.
- Verificar el estado dentro de la transacción para evitar dos reprogramaciones simultáneas.

### Pruebas mínimas de aceptación

- Cita futura se reprograma con motivo y nueva fecha.
- Cita futura se cancela con motivo.
- Cita cuya hora ya pasó no se reprograma.
- Cita cuya hora ya pasó no se cancela.
- Reprogramación sin motivo se rechaza.
- Cancelación sin motivo se rechaza.
- Nueva fecha pasada se rechaza.
- Conflicto de nueva fecha se rechaza.
- Horario anterior queda libre.
- Historial conserva cita anterior, nueva cita, motivo, usuario y fecha.
- Solicitudes simultáneas no generan dos citas nuevas.

### Definición de terminado

- Ambos flujos exigen motivo.
- Solo operan sobre citas futuras modificables.
- La trazabilidad completa puede demostrarse desde datos y auditoría.

---

## HU-14 — Consultar citas asignadas e historial de asistencia

**Jira:** [PAN-21](https://ues-team-nfbnyzvr.atlassian.net/browse/PAN-21)  
**Puntos:** 5  
**Prioridad Jira:** Media  
**Prioridad de corrección:** P1  
**Estado técnico actual:** Parcial

### Historia de usuario registrada en Jira

> Como profesional clínico, necesito consultar las citas asignadas y el historial de asistencia para organizar la continuidad de atención.

### Criterios de aceptación de Jira

1. La consulta permite una vista diaria y semanal con fecha, hora, paciente y estado de cada cita asignada.
2. El historial se puede filtrar por período, paciente, profesional y resultado de asistencia.
3. La información visible se limita al rol, área y permisos del usuario autenticado.

### Dependencias indicadas en Jira

- HU-11, HU-12 y HU-13.
- Citas asignadas y sus resultados o cambios.

### Flujo funcional esperado

1. El profesional abre la agenda.
2. El sistema presenta por defecto una vista diaria o semanal.
3. Cada elemento muestra fecha, hora, paciente permitido y estado.
4. El usuario alterna entre vista diaria y semanal.
5. Para revisar historial define un período.
6. Puede filtrar por paciente, profesional y resultado de asistencia según su rol.
7. El backend limita siempre los resultados a las áreas y permisos autorizados.
8. La navegación conserva los filtros activos.

### Lo que ya existe en `dev`

- Listado paginado de citas clínicas.
- Fecha/hora, paciente mediante código de privacidad, estado y motivo.
- Filtro por fecha exacta.
- Filtro por estado.
- Filtro por profesional para coordinadores.
- Especialista limitado a sus propias citas.
- Coordinador con acceso a citas de su área.
- Global Scope por área.
- Pruebas de listado, filtros existentes, sysadmin y aislamiento entre áreas.
- Calendario administrativo adicional, fuera del flujo clínico principal de esta historia.

### Brechas y correcciones requeridas

#### P1 — Vista diaria

- Crear una vista de agenda diaria claramente identificada.
- Ordenar por hora.
- Mostrar fecha, hora, paciente autorizado y estado.

#### P1 — Vista semanal

- Agregar navegación por semana y agrupación por día.
- Permitir avanzar, retroceder y volver a la semana actual.
- Evitar reutilizar únicamente el calendario administrativo como cumplimiento del profesional clínico.

#### P1 — Filtro por período

- Agregar fecha inicial y final.
- Validar el rango en backend.
- Conservar parámetros en paginación.

#### P1 — Filtro por paciente

- Usar el identificador permitido o mecanismo de búsqueda seguro.
- No exponer nombres u otros datos si el rol solo debe utilizar el código de privacidad.
- Validar autorización sobre el paciente y sus áreas.

#### P1 — Filtro por resultado de asistencia

- Diferenciar estado de agenda de resultado de asistencia cuando corresponda.
- Alinear valores con HU-12.

#### P1 — Filtro por profesional según rol

- El coordinador puede filtrar profesionales de su área.
- Un especialista no debe convertir el parámetro en una forma de consultar citas ajenas.
- Rechazar o ignorar identificadores fuera del área, incluso si son UUID válidos.

#### P2 — Respuesta correcta para roles no autorizados

- La implementación redirige al sysadmin hacia su panel.
- Si el contrato de aceptación o seguridad exige 403, devolver 403 de forma consistente.
- Alinear prueba, comportamiento y documentación.

### Pruebas mínimas de aceptación

- Vista diaria muestra los campos requeridos.
- Vista semanal agrupa correctamente.
- Navegación entre días y semanas funciona.
- Filtro por período funciona.
- Filtro por paciente autorizado funciona.
- Paciente no autorizado no revela resultados.
- Filtro por profesional respeta el área.
- Filtro por resultado de asistencia funciona.
- Combinación de filtros funciona.
- Especialista solo ve sus citas cuando esa sea la regla definida.
- Coordinador solo ve citas de áreas autorizadas.
- Sysadmin y roles no clínicos no acceden al contenido clínico.

### Definición de terminado

- Existen vistas diaria y semanal para el profesional clínico.
- Los cuatro filtros de Jira funcionan de extremo a extremo.
- Las restricciones de rol y área tienen pruebas contra manipulación de parámetros.

---

# 6. Correcciones transversales

## 6.1 Alinear Jira, backlog interno y código

**Prioridad: P0**

- Jira debe ser la fuente de verdad para la aceptación.
- Actualizar `sprint_2_backlog.md` después de implementar, no antes.
- No marcar tareas `[x]` únicamente porque existe una clase, ruta o pantalla.
- Una tarea está completa cuando cumple el criterio, tiene pruebas y puede demostrarse.
- Mantener siempre el formato `PAN-XX / HU-YY` para evitar confundir la clave Jira con el número de historia.

## 6.2 Estados y vocabulario del dominio

**Prioridad: P1**

- Unificar `Asistió`, `Asistida`, `Ausente` y cualquier variante técnica.
- Centralizar estados de citas y expedientes mediante Enum o constantes.
- Usar los mismos términos en migraciones, validaciones, Resources, componentes, pruebas y documentación.

## 6.3 Autorización en backend

**Prioridad: P0**

- Toda restricción de interfaz debe existir también en Policy, Request o servicio de dominio.
- Verificar rol, área, relación con paciente/expediente y autorización vigente.
- Probar manipulación directa de UUID, filtros y rutas.
- No confiar únicamente en Global Scopes para decisiones críticas de autorización.

## 6.4 Auditoría y versionado

**Prioridad: P1**

- Confirmar que las operaciones clínicas registran autor, fecha, acción, valores anteriores y nuevos cuando corresponda.
- No registrar datos clínicos sensibles en logs de aplicación en texto plano.
- Agregar pruebas que inspeccionen el contenido de auditoría.

## 6.5 Cifrado por área

**Prioridad: P0 de seguridad, implementación base existente**

- Verificar que todos los campos nuevos —motivo de derivación, resultado final y motivo de reprogramación— usen cifrado por área.
- Confirmar que dos profesionales autorizados de la misma área pueden descifrar.
- Confirmar que profesionales de otra área no pueden acceder.
- Ejecutar y documentar el procedimiento de migración de datos anteriores cuando el entorno contenga datos reales.

## 6.6 Fechas, horas y zona horaria

**Prioridad: P0/P1**

- Definir la zona horaria oficial del sistema.
- Guardar timestamps de forma coherente y convertirlos para visualización.
- Comparar citas usando fecha y hora completas, no solo el día.
- Añadir pruebas en límites: hora exacta, cambio de día y zona horaria.

## 6.7 Concurrencia e integridad de base de datos

**Prioridad: P0**

- Evitar expedientes activos duplicados mediante regla transaccional y, cuando PostgreSQL lo permita, índice único parcial.
- Evitar doble registro de asistencia.
- Evitar doble reprogramación.
- Mantener la prevención de conflictos de horario dentro de la base de datos.

## 6.8 Manejo de errores

**Prioridad: P1**

- Devolver 403/404/422 de forma consistente.
- No utilizar redirecciones como sustituto silencioso de una denegación cuando el contrato exige 403.
- Mostrar mensajes comprensibles y mantener los datos válidos del formulario.

---

# 7. Plan de implementación recomendado

## Fase 1 — Integridad y autorización del flujo clínico

1. HU-07: autorización del paciente, motivo cifrado y expediente activo único.
2. HU-08: autorización completa, fecha y plan de atención alineados con Jira.
3. HU-10: rol autorizado, resultado final, actualización/versionado y modo consulta.

## Fase 2 — Trazabilidad y reglas temporales de citas

4. HU-11: relación obligatoria entre consulta origen y cita.
5. HU-12: validación de timestamp y alimentación de estadísticas preventivas.
6. HU-13: cita original futura y motivo obligatorio de reprogramación.

## Fase 3 — Consulta y experiencia de presentación

7. HU-09: filtros por período, área y tipo.
8. HU-14: vistas diaria/semanal y filtros completos.

## Fase 4 — Validación del MVP

9. Levantar PostgreSQL en Docker.
10. Ejecutar la suite completa.
11. Corregir pruebas que validan comportamientos contrarios a Jira.
12. Ejecutar prueba manual de extremo a extremo.
13. Guardar evidencia de cada criterio.
14. Actualizar documentación y estados de Jira únicamente después de validar.

---

# 8. Distribución de correcciones entre Marcos y Eduardo

## 8.1 Criterio de distribución

El trabajo se divide por historias verticales completas. Cada responsable debe entregar backend, frontend, migraciones, autorización, pruebas y documentación de sus historias. Esta separación reduce conflictos de integración frente a una división tradicional donde una persona modifica todo el backend y otra todo el frontend.

La distribución queda equilibrada por puntos de historia:

| Responsable | Historias | Puntos Jira | Enfoque principal |
|---|---|---:|---|
| **Eduardo** | HU-07, HU-08, HU-11 y HU-12 | **26** | Derivación, consulta, creación de citas y asistencia |
| **Marcos** | HU-09, HU-10, HU-13 y HU-14 | **23** | Historial, expediente, cambios de citas y agenda |
| **Total** | HU-07 a HU-14 | **49** | Sprint 2 completo |

Cada responsable debe trabajar desde una rama creada a partir del mismo commit actualizado de `dev`. No se deben mezclar correcciones del Sprint 3.

## 8.2 Reglas de colaboración

1. Actualizar la rama local desde `dev` antes de iniciar.
2. Crear una rama independiente por persona o, preferiblemente, una rama por historia.
3. No modificar simultáneamente archivos compartidos sin coordinación previa.
4. Cada historia debe incluir pruebas antes de solicitar integración.
5. Los commits deben mencionar la historia funcional y la incidencia Jira, por ejemplo: `fix(HU-07/PAN-14): validar responsable y motivo de derivación`.
6. No cambiar estados de Jira hasta que las pruebas y la revisión cruzada estén completas.
7. La persona que no implementó la historia debe realizar su revisión funcional.
8. Toda migración debe probarse desde una base vacía y desde una base con datos existentes.
9. Los datos clínicos nuevos deben tratarse como sensibles y cifrarse por área.
10. Si un criterio de Jira es ambiguo, detener esa parte, documentar la decisión pendiente y consultar al Product Owner.

## 8.3 Trabajo asignado a Eduardo

### Resumen

Eduardo es responsable del núcleo del flujo clínico inicial:

```text
Derivar paciente → Registrar consulta → Crear próxima cita → Registrar asistencia
```

Sus historias habilitan varias tareas posteriores de Marcos. Debe priorizar primero los contratos de datos y rutas que otros módulos consumirán.

### Eduardo — HU-07 / PAN-14: Derivar paciente

**Prioridad:** P0  
**Puntos:** 8  
**Dependencias:** HU-04 y HU-06

#### Tareas de backend y base de datos

- [ ] Agregar el campo cifrado `motivo_derivacion` al expediente o a una entidad específica de derivación.
- [ ] Definir longitud, nulabilidad y estrategia de migración de datos existentes.
- [ ] Incorporar el campo en modelo, cast, Request, servicio y auditoría.
- [ ] Implementar autorización sobre el paciente, no solo validación del rol de referente.
- [ ] Permitir derivar únicamente pacientes bajo responsabilidad del referente o con autorización vigente.
- [ ] Considerar `abierto` y `en_atencion` como estados activos al detectar duplicados.
- [ ] Agregar protección transaccional o índice único parcial contra expedientes activos duplicados.
- [ ] Evitar que una solicitud concurrente cree dos expedientes para la misma área.

#### Tareas de frontend

- [ ] Agregar el campo obligatorio “Motivo de derivación” al modal o formulario.
- [ ] Mostrar errores de autorización y duplicidad sin perder los datos válidos.
- [ ] Indicar claramente el área destino y el paciente antes de confirmar.
- [ ] No mostrar la acción para pacientes sobre los que el referente no tenga autorización.

#### Pruebas asignadas

- [ ] Derivación exitosa por referente responsable.
- [ ] Derivación exitosa con autorización vigente.
- [ ] Rechazo de paciente ajeno.
- [ ] Rechazo sin motivo.
- [ ] Verificación del motivo cifrado.
- [ ] Rechazo con expediente `abierto` existente.
- [ ] Rechazo con expediente `en_atencion` existente.
- [ ] Prueba de solicitudes concurrentes.
- [ ] Evidencia de auditoría.

#### Entregable para Marcos

- Contrato definitivo del expediente activo.
- Campo y formato final del motivo de derivación.
- Reglas reutilizables de autorización sobre paciente y expediente.

### Eduardo — HU-08 / PAN-15: Registrar consulta

**Prioridad:** P0/P1  
**Puntos:** 8  
**Dependencia:** HU-07 corregida

#### Tareas de backend y base de datos

- [ ] Consolidar la autorización de consulta mediante Policy.
- [ ] Verificar área, expediente activo y autorización vigente.
- [ ] Resolver con Product Owner si la fecha de consulta es automática o seleccionable.
- [ ] Si es seleccionable, agregar validación y separar fecha clínica de timestamp técnico.
- [ ] Agregar un plan de atención a cada consulta o documentar la decisión aprobada de Jira.
- [ ] Implementar la regla “diagnóstico u observación” conforme al criterio definitivo.
- [ ] Cifrar todo campo clínico nuevo con la clave de área.
- [ ] Mantener el cambio de expediente `abierto` a `en_atencion` dentro de la transacción.

#### Tareas de frontend

- [ ] Alinear las etiquetas “plan de atención” y “plan de tratamiento”.
- [ ] Mostrar fecha de consulta cuando sea un dato editable.
- [ ] Permitir diagnóstico u observación según la regla acordada.
- [ ] Mantener la evaluación inicial de la primera consulta sin confundirla con el plan de cada consulta.

#### Pruebas asignadas

- [ ] Registro autorizado en el área propia.
- [ ] Rechazo desde otra área.
- [ ] Rechazo sobre expediente cerrado.
- [ ] Rechazo sobre expediente no autorizado.
- [ ] Validación de fecha.
- [ ] Validación de plan de atención.
- [ ] Validación de diagnóstico u observación.
- [ ] Cifrado de todos los campos clínicos.
- [ ] Lectura por dos profesionales autorizados de la misma área.
- [ ] Auditoría de autor y fecha.

#### Entregable para Marcos

- Estructura final de la consulta y sus campos.
- Parámetros disponibles para los filtros de HU-09.
- Regla definitiva para determinar una consulta activa en HU-11.

### Eduardo — HU-11 / PAN-18: Asignar próxima cita

**Prioridad:** P0  
**Puntos:** 5  
**Dependencia:** HU-08 corregida

#### Tareas de backend y base de datos

- [ ] Agregar `consulta_id` a las citas con su clave foránea.
- [ ] Definir la conservación de trazabilidad si una consulta se elimina lógicamente.
- [ ] Cambiar la creación de citas para exigir una consulta origen válida.
- [ ] Verificar que consulta, expediente, paciente y área correspondan entre sí.
- [ ] Definir e implementar qué significa “consulta activa”.
- [ ] Mantener la validación de fecha futura.
- [ ] Confirmar si el conflicto es por timestamp exacto o por intervalo de duración.
- [ ] Mantener la creación y auditoría dentro de una transacción.

#### Tareas de frontend

- [ ] Abrir el agendamiento desde la consulta activa.
- [ ] Conservar visualmente el contexto del paciente y consulta origen.
- [ ] Evitar que el modal se utilice fuera del flujo permitido.
- [ ] Mostrar errores de conflicto y fecha sin cerrar el formulario.

#### Pruebas asignadas

- [ ] Cita creada desde una consulta activa.
- [ ] Asociación correcta con consulta, expediente, paciente y profesional.
- [ ] Rechazo sin consulta origen.
- [ ] Rechazo de consulta ajena o perteneciente a otro expediente.
- [ ] Rechazo de fecha pasada.
- [ ] Rechazo de conflicto de horario.
- [ ] Rechazo de expediente cerrado.
- [ ] Auditoría de la creación.

#### Entregable para Marcos

- Estructura final de la cita y relación `consulta_id`.
- Estados y reglas definitivas que utilizará HU-13.
- Datos disponibles para las vistas de HU-14.

### Eduardo — HU-12 / PAN-19: Registrar asistencia o ausencia

**Prioridad:** P0  
**Puntos:** 5  
**Dependencia:** HU-11 corregida

#### Tareas de backend y base de datos

- [ ] Sustituir la comparación por día por una comparación del timestamp completo.
- [ ] Impedir registrar asistencia antes de la hora permitida.
- [ ] Definir si se usa hora de inicio o finalización de la cita.
- [ ] Centralizar los estados `asistida`/`ausente` mediante Enum o constantes.
- [ ] Implementar el evento o dato agregado que alimentará las estadísticas preventivas.
- [ ] Registrar estado, autor y timestamp dentro de una operación segura.
- [ ] Evitar doble registro mediante bloqueo transaccional o actualización condicional.

#### Tareas de frontend

- [ ] Deshabilitar las acciones antes de la hora permitida.
- [ ] Usar etiquetas consistentes “Asistió” y “Ausente”.
- [ ] Mostrar quién y cuándo registró el resultado cuando corresponda.
- [ ] Refrescar el estado de la cita tras la operación.

#### Pruebas asignadas

- [ ] Cita pasada permite registrar asistencia.
- [ ] Cita futura del mismo día se rechaza.
- [ ] Cita de un día futuro se rechaza.
- [ ] Cita no programada se rechaza.
- [ ] Profesional no asignado recibe 403.
- [ ] Se guardan autor, fecha y hora.
- [ ] Ausencia queda disponible para estadísticas.
- [ ] Doble solicitud no genera estados inconsistentes.
- [ ] Corregir o eliminar la prueba actual que acepta asistencia antes de la hora.

#### Entregable para Marcos

- Estados definitivos de asistencia.
- Consulta o evento disponible para los filtros e historial de HU-14.

### Orden de ejecución de Eduardo

```text
1. HU-07
2. HU-08
3. HU-11
4. HU-12
5. Prueba integral de su flujo
6. Entrega de contratos a Marcos
```

## 8.4 Trabajo asignado a Marcos

### Resumen

Marcos es responsable de completar los flujos de consulta, cierre, cambios de agenda y visualización:

```text
Consultar historial → Actualizar/cerrar expediente → Reprogramar/cancelar → Consultar agenda e historial
```

Puede avanzar con interfaz, Requests y diseño de pruebas desde el inicio, pero debe integrar los contratos finales entregados por Eduardo antes de cerrar HU-09, HU-13 y HU-14.

### Marcos — HU-09 / PAN-16: Historial multidisciplinario

**Prioridad:** P1  
**Puntos:** 8  
**Dependencia:** Estructura final de HU-08

#### Tareas de backend

- [ ] Agregar filtros por fecha inicial y fecha final.
- [ ] Agregar filtro por área autorizada.
- [ ] Definir y agregar filtro por tipo de atención.
- [ ] Validar todos los filtros mediante Form Request.
- [ ] Limitar el rango máximo cuando sea necesario.
- [ ] Conservar filtros en paginación.
- [ ] Confirmar y documentar el orden cronológico.
- [ ] Reforzar el aislamiento por área ante parámetros manipulados.

#### Tareas de frontend

- [ ] Crear controles de período, área y tipo de atención.
- [ ] Mostrar solo áreas autorizadas.
- [ ] Mostrar filtros activos y una acción para limpiarlos.
- [ ] Diferenciar historial vacío de resultados vacíos por filtros.
- [ ] Mantener fecha, área y profesional claramente visibles.

#### Pruebas asignadas

- [ ] Orden cronológico estable.
- [ ] Filtro por período.
- [ ] Filtro por área autorizada.
- [ ] Rechazo o resultado vacío para área no autorizada.
- [ ] Filtro por tipo de atención.
- [ ] Combinación de filtros.
- [ ] Bloqueo de sysadmin y roles no clínicos.

#### Dependencia que recibe de Eduardo

- Campos definitivos de consulta.
- Tipo o clasificación de atención.
- Regla de autorización consolidada.

### Marcos — HU-10 / PAN-17: Actualizar o cerrar expediente

**Prioridad:** P0/P1  
**Puntos:** 5  
**Dependencias:** HU-08 y acuerdo de Product Owner sobre el rol autorizado

#### Tareas de backend y base de datos

- [ ] Confirmar si puede cerrar un especialista, un coordinador o ambos.
- [ ] Ajustar Policy y pruebas a la decisión aprobada.
- [ ] Agregar el campo cifrado `resultado_final`.
- [ ] Definir campos permitidos para la actualización de expediente.
- [ ] Exigir motivo para cada actualización.
- [ ] Preservar valor anterior, valor nuevo, autor y fecha en auditoría.
- [ ] Bloquear toda escritura clínica no permitida después del cierre.
- [ ] Mantener lectura autorizada del expediente cerrado.

#### Tareas de frontend

- [ ] Crear o completar el formulario de actualización.
- [ ] Agregar resultado final al cierre.
- [ ] Mostrar confirmación y consecuencias del cierre.
- [ ] Cambiar la pantalla cerrada a modo de consulta.
- [ ] Ocultar acciones de consulta, cita y edición que ya no procedan.

#### Pruebas asignadas

- [ ] Actualización de un campo permitido.
- [ ] Auditoría con valores anterior y nuevo.
- [ ] Rechazo desde otra área.
- [ ] Rechazo sin autorización vigente.
- [ ] Cierre con resultado final.
- [ ] Cierre sin resultado final rechazado.
- [ ] Resultado final cifrado.
- [ ] Expediente cerrado visible en lectura.
- [ ] Escrituras posteriores rechazadas.

#### Coordinación con Eduardo

- Revisar que las rutas de consulta y creación de cita de Eduardo respeten el modo de consulta después del cierre.

### Marcos — HU-13 / PAN-20: Reprogramar o cancelar cita

**Prioridad:** P0/P1  
**Puntos:** 5  
**Dependencia:** Estructura final de HU-11

#### Tareas de backend y base de datos

- [ ] Exigir que la cita original sea futura para cancelar y reprogramar.
- [ ] Agregar motivo obligatorio de reprogramación.
- [ ] Cifrar el motivo cuando contenga datos clínicos o personales.
- [ ] Guardar motivo, autor y fecha.
- [ ] Mantener la liberación del horario anterior.
- [ ] Verificar conflicto de la nueva fecha.
- [ ] Bloquear la cita original dentro de la transacción.
- [ ] Evitar dos reprogramaciones simultáneas.
- [ ] Confirmar que cita anterior y nueva pueden reconstruirse en historial.

#### Tareas de frontend

- [ ] Agregar motivo al formulario de reprogramación.
- [ ] No mostrar acciones para citas pasadas o cerradas.
- [ ] Mostrar nueva fecha, motivo y confirmación.
- [ ] Presentar la relación entre cita original y nueva cuando corresponda.

#### Pruebas asignadas

- [ ] Reprogramación futura con motivo.
- [ ] Cancelación futura con motivo.
- [ ] Rechazo de cita pasada en ambos flujos.
- [ ] Rechazo de motivo vacío.
- [ ] Rechazo de nueva fecha pasada.
- [ ] Rechazo de conflicto.
- [ ] Liberación del horario anterior.
- [ ] Historial con usuario y fecha.
- [ ] Prueba de concurrencia.

#### Dependencia que recibe de Eduardo

- Relación final de cita con consulta origen.
- Enum o constantes de estados.
- Regla de conflicto de horario.

### Marcos — HU-14 / PAN-21: Agenda e historial de asistencia

**Prioridad:** P1  
**Puntos:** 5  
**Dependencias:** HU-11, HU-12 y HU-13 corregidas

#### Tareas de backend

- [ ] Implementar consulta diaria.
- [ ] Implementar consulta semanal.
- [ ] Agregar filtro por período.
- [ ] Agregar filtro por paciente usando identificación segura.
- [ ] Mantener filtro por profesional con validación de área.
- [ ] Agregar filtro por resultado de asistencia.
- [ ] Conservar filtros en navegación y paginación.
- [ ] Impedir que UUID manipulados revelen citas ajenas.
- [ ] Alinear la respuesta del sysadmin con el contrato de seguridad acordado.

#### Tareas de frontend

- [ ] Crear selector de vista diaria/semanal.
- [ ] Agregar navegación al día o semana anterior/siguiente.
- [ ] Agregar acción para volver a hoy.
- [ ] Mostrar fecha, hora, paciente permitido y estado.
- [ ] Agregar filtros por período, paciente, profesional y resultado.
- [ ] Mantener diseño y experiencia del área clínica.
- [ ] No utilizar el calendario administrativo como sustituto de esta vista.

#### Pruebas asignadas

- [ ] Vista diaria.
- [ ] Vista semanal.
- [ ] Navegación temporal.
- [ ] Filtro por período.
- [ ] Filtro por paciente.
- [ ] Filtro por profesional dentro del área.
- [ ] Filtro por resultado.
- [ ] Combinación de filtros.
- [ ] Aislamiento ante parámetros manipulados.
- [ ] Comportamiento de especialista, coordinador y sysadmin.

#### Dependencias que recibe de Eduardo

- Relación consulta-cita de HU-11.
- Estados y timestamp de asistencia de HU-12.
- Marcos integra además su propia trazabilidad de HU-13.

### Orden de ejecución de Marcos

```text
1. HU-10 (puede iniciar sin esperar cambios estructurales de citas)
2. Preparar interfaz y filtros de HU-09
3. Integrar estructura final de HU-08 y terminar HU-09
4. Integrar estructura final de HU-11 y terminar HU-13
5. Integrar HU-12 y HU-13 para terminar HU-14
6. Prueba integral de su flujo
```

## 8.5 Archivos compartidos que requieren coordinación

Los siguientes archivos o áreas tienen alta probabilidad de ser modificados por ambos:

| Área compartida | Responsable principal | Regla de coordinación |
|---|---|---|
| `routes/web.php` | Eduardo durante HU-07/HU-08/HU-11/HU-12 | Marcos agrega sus rutas después de integrar los cambios de Eduardo o en commits pequeños |
| `app/Models/Expediente.php` | Marcos | Eduardo comunica campos/referencias requeridos por HU-07/HU-08 antes de que Marcos cierre HU-10 |
| `app/Models/Consulta.php` | Eduardo | Marcos consume su contrato para HU-09, evitando modificarlo sin coordinación |
| `app/Models/Cita.php` | Eduardo hasta cerrar HU-12 | Marcos integra HU-13 después del contrato final de estados y relaciones |
| `app/Policies/ExpedientePolicy.php` | Marcos | Eduardo entrega reglas requeridas por HU-07/HU-08 |
| `app/Policies/CitaPolicy.php` | Eduardo inicialmente | Marcos añade HU-13 conservando reglas de HU-11/HU-12 |
| `resources/js/Pages/Pacientes/Show.vue` | Eduardo durante flujo clínico | Marcos integra modo consulta del expediente cerrado después |
| Migraciones | Cada autor de historia | Usar timestamps distintos y revisar orden antes de integrar |
| Seeders | Marcos | Integrar datos de prueba acordados por ambos al final |
| `sprint_2_backlog.md` y documentación | Ambos | Actualización final conjunta después de que las pruebas pasen |

## 8.6 Puntos de integración obligatorios

### Integración A — Después de HU-07 y HU-08

Eduardo entrega:

- estructura final de expedientes y consultas;
- reglas de autorización;
- migraciones y pruebas verdes de HU-07/HU-08.

Marcos valida:

- que HU-09 puede filtrar los nuevos campos;
- que HU-10 respeta los estados y permisos acordados.

### Integración B — Después de HU-11 y HU-12

Eduardo entrega:

- relación consulta-cita;
- estados definitivos;
- regla temporal de asistencia;
- mecanismo estadístico de ausencia.

Marcos valida:

- reprogramación y cancelación sobre el modelo final;
- filtros y vistas de HU-14.

### Integración C — Cierre conjunto

Ambos deben:

- levantar el entorno PostgreSQL;
- ejecutar la suite completa;
- resolver regresiones;
- ejecutar el flujo de presentación;
- revisar evidencia de los 24 criterios de aceptación;
- actualizar backlog interno y Jira después de validar.

## 8.7 Revisión cruzada

| Implementación | Revisor | Objetivo de la revisión |
|---|---|---|
| HU-07 — Eduardo | Marcos | Flujo del referente, formulario y duplicados |
| HU-08 — Eduardo | Marcos | Campos clínicos, experiencia y presentación en historial |
| HU-11 — Eduardo | Marcos | Trazabilidad de la cita para HU-13/HU-14 |
| HU-12 — Eduardo | Marcos | Estados, fecha/hora y dato estadístico |
| HU-09 — Marcos | Eduardo | Autorización, filtros y aislamiento de datos |
| HU-10 — Marcos | Eduardo | Bloqueos backend tras el cierre |
| HU-13 — Marcos | Eduardo | Transacciones, conflicto e historial de cambios |
| HU-14 — Marcos | Eduardo | Filtros manipulados, permisos y datos expuestos |

## 8.8 Definición de entrega individual

Una historia asignada no se considera entregada hasta que su responsable incluya:

- [ ] Código backend completo.
- [ ] Interfaz completa.
- [ ] Migraciones reversibles.
- [ ] Policies y validaciones.
- [ ] Pruebas positivas y negativas.
- [ ] Pruebas de seguridad y concurrencia cuando correspondan.
- [ ] Evidencia de compilación.
- [ ] Evidencia de ejecución sobre PostgreSQL.
- [ ] Descripción breve de decisiones técnicas.
- [ ] Revisión cruzada aprobada.

## 8.9 Tablero resumido de asignaciones

### Eduardo

- [ ] HU-07 / PAN-14 — Derivación segura y expediente activo único.
- [ ] HU-08 / PAN-15 — Consulta clínica alineada con Jira.
- [ ] HU-11 / PAN-18 — Próxima cita vinculada a consulta.
- [ ] HU-12 / PAN-19 — Asistencia con regla temporal y estadísticas.
- [ ] Entregar contratos de modelos a Marcos.
- [ ] Revisar HU-09, HU-10, HU-13 y HU-14.

### Marcos

- [ ] HU-09 / PAN-16 — Historial con filtros completos.
- [ ] HU-10 / PAN-17 — Actualización, cierre, versionado y modo consulta.
- [ ] HU-13 / PAN-20 — Reprogramación/cancelación conforme a Jira.
- [ ] HU-14 / PAN-21 — Agenda diaria/semanal e historial filtrable.
- [ ] Integrar contratos entregados por Eduardo.
- [ ] Revisar HU-07, HU-08, HU-11 y HU-12.

### Ambos

- [ ] Resolver decisiones ambiguas con Product Owner.
- [ ] Ejecutar suite completa.
- [ ] Preparar datos de demostración.
- [ ] Realizar ensayo de presentación.
- [ ] Adjuntar evidencia y actualizar Jira.

---

# 9. Flujo integral que debe demostrarse en la presentación

## Escenario principal exitoso

1. Un referente autorizado busca un paciente.
2. Lo deriva a Psicología registrando el motivo.
3. El sistema crea un único expediente activo.
4. Un especialista de Psicología abre el expediente.
5. Registra una consulta con fecha, notas/observación, diagnóstico cuando corresponda y plan de atención.
6. Los datos quedan cifrados y auditados.
7. Desde la consulta asigna la próxima cita.
8. La cita queda vinculada a esa consulta y aparece en la agenda diaria/semanal.
9. Al llegar la hora, el especialista registra `Asistió` o `Ausente`.
10. Si registra `Ausente`, el dato queda disponible para estadísticas preventivas.
11. Si se necesita un cambio, reprograma con motivo; la cita anterior queda trazable y su horario se libera.
12. El historial multidisciplinario muestra las atenciones permitidas y aplica filtros.
13. El usuario autorizado actualiza o cierra el expediente con resultado final.
14. El expediente cerrado permanece consultable, pero bloquea nuevas operaciones clínicas.

## Escenarios negativos que deben demostrarse o probarse

- Un referente no autorizado no puede derivar un paciente ajeno.
- No se puede crear un segundo expediente activo para la misma área.
- Un especialista de otra área no puede ver ni modificar el expediente.
- No se puede crear una próxima cita sin consulta origen.
- No se puede registrar asistencia antes de la hora permitida.
- No se puede reprogramar o cancelar una cita pasada.
- No se puede cerrar sin resultado final.
- Los filtros no revelan pacientes, profesionales o áreas no autorizadas.

---

# 10. Elementos que no deben confundirse con el cierre del Sprint 2

## Funcionalidad adicional no sustitutiva

- El calendario administrativo puede conservarse como mejora adicional, pero no sustituye las vistas diaria y semanal requeridas para el profesional clínico en HU-14.
- Tener auditoría automática en el modelo no sustituye las pruebas del contenido requerido por Jira.
- Tener una pantalla o botón no significa que el criterio esté completo si la restricción no existe en backend.
- Tener pruebas verdes no es suficiente si las pruebas validan reglas diferentes a Jira.

## Fuera de prioridad inmediata del Sprint 2

- Rediseños estéticos amplios que no sean necesarios para completar los flujos.
- Nuevas analíticas no solicitadas, excepto el dato mínimo de ausencias requerido por HU-12.
- Exportación PDF/Excel.
- Bitácora administrativa completa de sprints posteriores.
- Alertas preventivas completas si pertenecen a otro sprint; sí debe quedar preparado el evento/dato de ausencia exigido en HU-12.
- Funcionalidades de HU-15 en adelante, salvo dependencias técnicas estrictamente necesarias.

---

# 11. Lista final de control antes de declarar completo el Sprint 2

## Producto

- [ ] HU-07 cumple sus tres criterios de Jira.
- [ ] HU-08 cumple sus tres criterios de Jira.
- [ ] HU-09 cumple sus tres criterios de Jira.
- [ ] HU-10 cumple sus tres criterios de Jira.
- [ ] HU-11 cumple sus tres criterios de Jira.
- [ ] HU-12 cumple sus tres criterios de Jira.
- [ ] HU-13 cumple sus tres criterios de Jira.
- [ ] HU-14 cumple sus tres criterios de Jira.

## Seguridad e integridad

- [ ] Todos los campos clínicos nuevos están cifrados.
- [ ] Autorizaciones verificadas en backend.
- [ ] Aislamiento por área comprobado.
- [ ] No existen expedientes activos duplicados.
- [ ] No existen conflictos de agenda ni dobles actualizaciones.
- [ ] Auditoría conserva autor, fecha y cambios requeridos.

## Pruebas

- [ ] Docker/PostgreSQL de pruebas está disponible.
- [ ] Suite completa ejecutada sin fallos.
- [ ] Pruebas actuales contradictorias con Jira fueron corregidas.
- [ ] Pruebas positivas, negativas y de concurrencia agregadas.
- [ ] Compilación frontend exitosa.
- [ ] Prueba manual integral completada.

## Presentación y gestión

- [ ] Datos de demostración preparados para todos los pasos.
- [ ] Se puede ejecutar el flujo completo sin edición manual en base de datos.
- [ ] Existe evidencia por criterio de aceptación.
- [ ] Backlog interno coincide con Jira.
- [ ] Subtareas Backend, Frontend y QA reflejan el estado real.
- [ ] Historias de Jira se cambian a completadas solo después de la aceptación.

## Criterio de salida

El Sprint 2 puede declararse completo cuando las ocho historias cumplen todos sus criterios de Jira, la suite automatizada pasa sobre PostgreSQL, el flujo integral se demuestra sin intervención técnica y las evidencias están asociadas a las incidencias correspondientes.
