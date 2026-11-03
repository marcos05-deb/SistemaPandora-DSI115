# Guía de defensa y demo — Sprint 3 (Sistema Pandora, DSI115)

Despliegue: https://sistemapandora-dsi115.onrender.com (Render free tier, arranque en frío de ~50 s).
Épicas verificadas en Jira: PAN-5 Alertas y seguimiento preventivo, PAN-6 Analítica y reportes, PAN-7 Auditoría y trazabilidad. Todas las HU (PAN-22..PAN-30) figuran en estado Hecho.

## 1. Resumen

| Integrante | Épica | HU | CA cumplidos | Riesgo en demo |
|---|---|---|---|---|
| [Integrante 1] | PAN-5 Alertas y seguimiento | HU-15, HU-16, HU-17 | Todos (9/9) | Alto: en producción no hay versión de rúbrica vigente salvo que se cree con antelación; la alerta depende de datos previos |
| [Integrante 2] | PAN-6 Analítica y reportes | HU-18, HU-19, HU-20 | 9/9 | Medio: la supresión de celdas pequeñas puede dar tablas "vacías" |
| [Integrante 3] | PAN-7 Auditoría y trazabilidad | HU-21, HU-22, HU-23 | 9/9 | Medio: la inmutabilidad en BD depende del usuario de la app; HU-23 exporta CSV, no PDF |

### Orden y transiciones
1. **Alertas (Integrante 1)**: "El sistema detecta riesgo a partir de reglas versionadas y avisa al equipo clínico."
2. **Analítica (Integrante 2)**: "Esos mismos datos, agregados y anonimizados, sirven a la institución para decidir; y se pueden exportar."
3. **Auditoría (Integrante 3)**: "Todo lo anterior —alertas, lecturas, exportaciones— deja un rastro inmutable que un auditor puede consultar y exportar."

Duración: ~8 min por épica, 2 min de apertura y 3 min de cierre. Cada integrante entrega la sesión al siguiente con la frase de transición.

### Checklist común del día
- [ ] Abrir la URL 10-15 min antes y recorrer el login para despertar Render (cold start ~50 s). Repetir 2 min antes de empezar.
- [ ] Confirmar que existen datos demo (ver sección de cada épica). `RubricaDemoSeeder` y `DashboardCasosDemoSeeder` solo corren con `APP_ENV` local/testing: **no** se ejecutan solos en Render.
- [ ] Una ventana de navegador por rol (ventanas de incógnito separadas) con sesión ya iniciada.
- [ ] Credenciales entregadas por el equipo (ver `database/seeders/DatabaseSeeder.php`; no se escriben aquí).
- [ ] Respaldo local levantado (`php artisan serve` + `npm run dev` o build) con la BD sembrada.
- [ ] Capturas y video corto de cada flujo guardados fuera del navegador.
- [ ] Zona horaria: El Salvador; revisar que las fechas de los filtros sean coherentes.
- [ ] Abrir `docs/evidencia/HU-15.md` … `HU-23.md` y `docs/auditoria/matriz_cobertura.md` en pestañas, por si el jurado pide evidencia.

### Qué NO mostrar o evitar
- No editar los filtros del reporte después de "Generar" y antes de exportar (limitación conocida de HU-20 CA-1).
- No arrancar la demo con la app dormida; no depender del primer request en frío.
- No mostrar contraseñas ni el archivo `.env`.
- No intentar eliminar o editar eventos de auditoría desde SQL con el usuario administrador (existe un bypass para limpieza de pruebas).
- La búsqueda segura y la pantalla de crear consulta ya generan eventos de lectura (`view_patient_secure_search`, `view_consulta_form`); se pueden usar como evidencia en la bitácora.
- No prometer que la reevaluación de alertas es automática (el comando existe, pero no está programado).

### Cuentas demo (seeder `database/seeders/DatabaseSeeder.php`)
| Correo | Rol | Área |
|---|---|---|
| admin@pandora.com | Administrador del sistema | — |
| coordinador@pandora.com | Coordinador de área | Psicología |
| especialista-psi@pandora.com | Especialista | Psicología |
| especialista@pandora.com | Especialista | Medicina General |
| especialista-nutri@pandora.com | Especialista | Nutrición |
| psicosocial@pandora.com | Referente psicosocial | Trabajo Social |

Contraseñas: "credenciales entregadas por el equipo" (ver el seeder).

---

## 2. Épica PAN-5 — Alertas y seguimiento preventivo (HU-15, HU-16, HU-17)

### Mensaje clave
La universidad necesita detectar a tiempo a estudiantes en riesgo (crisis, ausencias repetidas, ideación suicida) con criterios uniformes y auditables. Pandora evalúa automáticamente una rúbrica versionada, genera una alerta con su evidencia y obliga a documentar el seguimiento hasta cerrarla o descartarla.

### Guion de demo (~8 min)
| Min | Rol / cuenta | Pantalla y acciones | CA |
|---|---|---|---|
| 0-1 | Coordinador (`coordinador@pandora.com`) | `/rubricas`: mostrar versiones (histórica, vigente, futura "DEMO") | HU-15 CA-2, CA-3 |
| 1-3 | Coordinador | `/rubricas/versiones/{id}` de la versión vigente: señales `ausencia_cita` (umbral 2, ventana 30 d), `crisis_psicologica` (umbral 1, 90 d), `tendencia_suicida` (indicador `ideas_suicidas`, umbral 1). Luego `/rubricas/versiones/crear`: crear una nueva versión con vigencia futura y justificación; mostrar que la anterior no cambia | HU-15 CA-1, CA-2, CA-3 |
| 3-5 | Especialista Psicología (`especialista-psi@pandora.com`) | **Disparar alerta en vivo** (ver abajo). Luego campana de notificaciones y `/alertas` | HU-16 CA-1, CA-2, CA-3 |
| 5-6 | Coordinador (otra ventana) | Mostrar que también recibió la notificación; el especialista de Medicina General **no** ve la alerta (otra área) | HU-16 CA-3 |
| 6-8 | Especialista / Coordinador | `/alertas/{id}`: señal, valor calculado, umbral y versión de rúbrica originales; registrar seguimiento (valoración, acción preventiva, responsable, próxima revisión); intentar enviar vacío (error); cerrar con resultado o descartar con justificación | HU-17 CA-1, CA-2, CA-3 |

**Cómo disparar una alerta en vivo (HU-16).** La versión vigente DEMO define ausencias con umbral 2 en 30 días y crisis psicológica con umbral 1 en 90 días. Opciones:
- *Ausencias:* en un expediente de Psicología que aún no tenga alerta, registrar la asistencia de dos citas como "ausente" (`PATCH /expedientes/{e}/citas/{c}/asistencia`, desde la ficha del expediente). Al segunda ausencia se evalúa y se crea la alerta (valor 2 >= umbral 2). Los datos demo ya traen un paciente con dos ausencias y alerta activa; usar **otro** expediente para ver la creación, o mostrar la existente como respaldo.
- *Crisis:* crear una consulta (`/expedientes/{e}/consultas/crear`) marcando crisis psicológica; basta una (umbral 1). Si se marca un indicador de tendencia suicida de la rúbrica, genera además la señal `tendencia_suicida`.
- **Destinatarios:** especialistas y coordinadores con perfil en el **mismo área** del expediente (excluye sysadmin y referente psicosocial); la notificación no lleva datos clínicos. El responsable es el profesional que registró el evento (o el coordinador del área).
- Si ya existe una alerta activa para la misma señal y paciente, no se duplica; se puede volver a generar solo tras cerrarla.

### Datos a preparar
- BD migrada y sembrada (`php artisan migrate --seed` en local). En local: `RubricaDemoSeeder` crea versiones DEMO v1 histórica, v2 vigente, v3 futura, evaluaciones, alertas activas y cerradas.
- **En Render:** el seeder demo no corre (solo local/testing) y la migración solo crea la rúbrica "riesgo_preventivo" sin versiones. Hay que crear una versión no-demo desde `/rubricas/versiones/crear` **con anticipación**: la vigencia debe ser posterior al momento actual y a la última versión, y se exige fecha y referencia de validación institucional. Crearla con al menos unos minutos de antelación y verificar que ya figure como vigente. Sin versión vigente no se genera alerta (test HU16-E07).
- Un expediente de Psicología sin alertas con citas por registrar como ausentes (o una cita pasada pendiente de asistencia).
- Cuentas: coordinador y especialista de Psicología, especialista de Medicina General.

### Evidencia técnica para el jurado
- Modelo: `app/Models/Rubrica*.php`, migraciones `2026_10_21_120000_create_versioned_risk_rubric_tables.php`, `..._130000_add_hu16_preventive_alerts.php`, `..._140000_add_hu17_preventive_followups.php`.
- Servicios: `app/Services/RubricaVersionService.php`, `RubricaResolver.php`, `EvaluadorRiesgoPreventivo.php`, `AlertaSeguimientoService.php`.
- Listeners: `app/Listeners/EvaluarRiesgoTrasAusencia.php`, `EvaluarRiesgoTrasConsulta.php`. Políticas: `AlertaPreventivaPolicy.php`, `RubricaPolicy.php`.
- Decisiones: rúbrica **append-only** (modificar = nueva versión; triggers/REVOKE impiden UPDATE/DELETE), evaluaciones fijadas a la versión usada, texto de seguimiento cifrado con la llave del área, bloqueo de fila para transiciones terminales, deduplicación de alertas.
- Pruebas: `tests/Feature/HU15/RubricaVersionadaTest.php` (E01-E13: versión 1 con autor y vigencia, 422 por señal faltante, versión 2 sin tocar la 1, rechazo de UPDATE/DELETE, evaluación fijada a la v1, fecha pasada rechazada); `tests/Feature/HU16/AlertaEstadisticaTest.php` (E01-E14: evaluación al registrar crisis/ausencia, umbrales menor/igual/mayor, notificación solo a misma área sin PHI, deduplicación, retry tras fallo) más `Hu16MigrationRollbackTest.php`; `tests/Feature/HU17/SeguimientoPreventivoTest.php` (E01-E13: versión original visible, cifrado, append-only, campos obligatorios, motivo de cierre/descarte, concurrencia).

### Preguntas probables
1. **¿Qué pasa si falla la evaluación al registrar una ausencia?** El registro de la cita/consulta se conserva y el fallo queda en el log con `cita_id`/`consulta_id` y `paciente_id`, sin datos sensibles. El comando `alertas:reevaluar` corre cada hora (`routes/console.php`, `schedule:work` iniciado en `docker/entrypoint.sh`) y crea la alerta pendiente sin duplicarla (test en `tests/Feature/HU16/AlertaEstadisticaTest.php`). Limitación: en el plan gratuito de Render el scheduler se detiene mientras el servicio duerme.
2. **¿Cómo se garantiza que cambiar la rúbrica no reescribe el historial?** Cada versión es inmutable y cada evaluación guarda `rubrica_version_id`; test HU15-E08.
3. **¿Quién ve las alertas?** Coordinador y especialistas del área; no sysadmin ni referente psicosocial. Test HU16-E12 y HU17-E02.
4. **¿Los umbrales están validados clínicamente?** La rúbrica demo está marcada `es_demo`; las versiones reales exigen fecha y referencia de validación institucional (restricción CHECK en BD).
5. **¿Se puede cerrar una alerta sin documentar?** No, ni en aplicación ni en BD (HU17-E06).
6. **¿Qué datos viajan en la notificación?** Sin PHI (HU16-E09).

### Plan B
Local con la misma secuencia y seeder demo; si falla la creación en vivo, mostrar las alertas demo ya sembradas (activa y cerrada) y el video del flujo completo.

---

## 3. Épica PAN-6 — Analítica y reportes (HU-18, HU-19, HU-20)

### Mensaje clave
La dirección de bienestar necesita cifras agregadas para decidir sin exponer a ningún estudiante. Pandora ofrece indicadores y reportes filtrables por período y área, con supresión de celdas pequeñas, y los exporta a PDF o Excel con trazabilidad.

### Guion de demo (~8 min)
| Min | Rol / cuenta | Pantalla y acciones | CA |
|---|---|---|---|
| 0-3 | Coordinador (`coordinador@pandora.com`) | `/indicadores`: totales; filtrar por período, área e indicador; mostrar que solo ve su área. Explicar celdas suprimidas ("menos de N casos") | HU-18 CA-1, CA-2, CA-3 |
| 3-4 | Administrador (`admin@pandora.com`) | `/indicadores`: ve todas las áreas, institución sin duplicar pacientes | HU-18 CA-1 |
| 4-6 | Administrador o coordinador | `/reportes`: intentar generar sin indicadores (error); luego período + área + indicadores + agrupación (mes, semana, área) y **Generar** | HU-19 CA-1, CA-2, CA-3 |
| 6-8 | Mismo usuario | **Sin modificar filtros**, exportar PDF y Excel: abrir archivos y mostrar fecha de generación, período, indicadores y filtros en el encabezado, sin datos de pacientes | HU-20 CA-1, CA-2, CA-3 |
| 8 | Especialista (`especialista@pandora.com`) | Intentar `/reportes`: acceso denegado | HU-19 CA-3 |

### Datos a preparar
- Local: sembrar `DashboardCasosDemoSeeder` y `RubricaDemoSeeder` (escenarios HU-18: paciente con 30 consultas/citas en Psicología, otro con pocas en Trabajo Social, uno cerrado). En Render: verificar que haya volumen suficiente; si no, correr los seeders con `APP_ENV=local` contra una BD de demo, o capturar pantallas locales.
- Con k mínimo = 5 (`config/indicadores.php`, piso 2 en `app/Support/PrivacyThreshold.php`), áreas con pocos casos se suprimen: tener al menos un área con volumen alto (Psicología) para mostrar números.
- Rango máximo 366 días (`config/limites.php`).

### Evidencia técnica
- Servicios: `app/Services/IndicadorService.php`, `ReporteParametrizadoService.php`, `ReporteExportService.php`; controladores `IndicadoresController.php`, `ReportesController.php`; políticas `IndicadorPolicy.php`, `ReportePolicy.php`; vistas `resources/js/Pages/Indicadores`, `Reportes/Index.vue`.
- Decisiones: agregación en servidor, solo arreglos y escalares (sin identificadores), **k-anonimato** con supresión complementaria, número fijo de consultas, rutas con `throttle`, exportación con hash **SHA-256** y registro append-only de metadatos (`ReporteExportService.php:57`), neutralización de inyección de fórmulas en Excel, tope de 5000 filas, el servidor ignora cualquier resultado enviado por el cliente.
- Pruebas: `tests/Feature/HU18/IndicadoresTest.php` (E01 coincide con SQL independiente, E02/E09 supresión, E08 sin identificadores, E10 roles, E11 consultas fijas) y `IndicadoresSeedTest.php`; `tests/Feature/HU19/ReportesTest.php` (E01 parámetros obligatorios, E04 agrupaciones vs SQL, E08 roles, E10 sin PHI, E11 reproducible); `tests/Feature/HU20/ExportarReportesTest.php` (E01 mismos totales, E02 filtros en encabezado, E03 fecha, E06 sin PHI, E09 fórmulas, E10 SHA-256).

### Preguntas probables
1. **¿Por qué hay celdas suprimidas?** Para evitar reidentificación en grupos pequeños (k-anonimato); si una celda se suprime se suprime también la complementaria para que no se deduzca por resta.
2. **¿La exportación conserva los filtros? (HU-20 CA-1)** Sí. La URL de exportación se construye con `reporte.parametros_aplicados` (los filtros del reporte mostrado), no con el formulario. Si el usuario cambia filtros sin regenerar, la pantalla avisa que la descarga usa el reporte mostrado (`Reportes/Index.vue`, `exportQuery` y `filtersChanged`).
3. **¿Quién puede ver reportes?** Coordinador (solo su área) y administrador (todas); especialista y referente, no.
4. **¿Los totales son correctos?** Se contrastan con SQL independiente en pruebas (HU18-E01, HU19-E04).
5. **¿Un archivo exportado puede alterarse?** Se registra su SHA-256 en una tabla append-only para verificar integridad.
6. **¿Qué ocurre con períodos vacíos?** Totales en cero sin error (HU18-E06, HU19-E05).

### Plan B
Capturas de `/indicadores` y `/reportes` y los PDF/Excel ya generados, abiertos en el equipo; demo local con datos sembrados.

---

## 4. Épica PAN-7 — Auditoría y trazabilidad (HU-21, HU-22, HU-23)

### Mensaje clave
Los datos clínicos son sensibles: la institución debe poder demostrar quién accedió o modificó qué, y que ese registro no se puede alterar. Pandora audita automáticamente, protege el registro contra edición y permite a un administrador consultarlo y exportarlo como evidencia.

### Guion de demo (~8 min)
| Min | Rol / cuenta | Pantalla y acciones | CA |
|---|---|---|---|
| 0-2 | Especialista (`especialista-psi@pandora.com`) | Abrir un expediente y editar o registrar un dato (creación/lectura/actualización) | HU-21 CA-1 |
| 2-5 | Administrador (`admin@pandora.com`) | `/admin/bitacora`: ver el evento reciente (usuario, acción, objeto, fecha/hora, IP); filtrar por usuario, acción, período y registro; orden descendente y paginación de 25 | HU-21 CA-2; HU-22 CA-1, CA-2 |
| 5-6 | Coordinador | Intentar `/admin/bitacora`: acceso denegado | HU-22 CA-3 |
| 6-7 | Administrador | Mostrar que no hay opción de editar o borrar eventos; explicar triggers y permisos en BD | HU-21 CA-3 |
| 7-8 | Administrador | **Exportar** (`/admin/bitacora/exportar`, CSV) con filtros activos; abrir el archivo: usuario generador, fecha/hora, solo eventos filtrados; refrescar la bitácora y mostrar el nuevo evento de exportación | HU-23 CA-1, CA-2, CA-3 |

### Datos a preparar
- Generar actividad justo antes de la demo (el login y la actividad previa de las otras épicas alimentan la bitácora: alertas, reportes exportados).
- Confirmar el rango de fechas con eventos y un filtro que devuelva pocos resultados.
- Verificar en la BD de producción que el usuario de la aplicación no sea propietario de `audits` (si lo es, los REVOKE y triggers no protegen; ver brechas).

### Evidencia técnica
- Paquete `owen-it/laravel-auditing` con trait `Auditable` en modelos sensibles; clasificación obligatoria de todos los modelos (test HU21-E05). Servicios: `ClinicalAccessAuditor.php`, `AuthAccessAuditor.php`, `BitacoraQueryService.php`, `BitacoraExportService.php` (SHA-256 en línea 103); `Admin/BitacoraController.php`; `BitacoraPolicy.php`.
- Inmutabilidad: migraciones `2026_09_12_220000_revoke_update_delete_on_audits.php` y `2026_10_24_120000_harden_audits_immutability_and_indexes.php` (REVOKE + triggers `prevent_audits_mutation`, error 42501); sanitización de carnet en URL y sin PHI en claro.
- Pruebas: `tests/Feature/HU21/AuditoriaInmutableTest.php` (E01 created, E04 inmutabilidad Eloquent y SQL, E06 sin PHI, E11 login); `tests/Feature/HU22/BitacoraAuditoriaTest.php` (E01-E04 filtros/orden/paginación, E05 solo sysadmin, E06 sin valores sensibles); `tests/Feature/HU23/ExportarEvidenciaAuditoriaTest.php` (E01 solo eventos filtrados, E03 generador y fecha, E04 la exportación queda auditada, E06 SHA-256 coincide, E09 sin PHI); además `tests/Feature/Compliance/AuditLoggingTest.php`.

### Preguntas probables
1. **¿Se audita toda lectura de datos clínicos? (HU-21 CA-1)** Sí, en las vistas clínicas: expediente, historial, alertas, citas, búsqueda segura (`view_patient_secure_search`) y formulario de consulta (`view_consulta_form`). La búsqueda sin resultado no se registra para no guardar el código buscado. Tests: `tests/Feature/HU21/AuditoriaLecturasTest.php`.
2. **¿Es realmente inmutable?** La aplicación no puede modificar ni borrar (Eloquent lo rechaza y la BD aplica REVOKE y triggers). La garantía en BD depende de que el usuario de la aplicación **no sea propietario** de la tabla; además existe una migración (`2026_10_24_150000_allow_audits_immutability_bypass_for_test_cleanup.php`) que permite un bypass por configuración de sesión solo con privilegio del administrador de BD, para limpiar pruebas. Mitigación: usuario de app sin ownership y restringir el rol admin en producción. Pendiente humano reportado en Jira: verificar triggers y privilegios en el despliegue.
3. **¿Por qué la evidencia es CSV y no PDF?** El CSV con hash SHA-256 es verificable y procesable; el formato está pendiente de aceptación formal del PO (comentario en PAN-30).
4. **¿Quién puede consultar la bitácora?** Solo el administrador del sistema (HU22-E05).
5. **¿La exportación se audita?** Sí, como nuevo evento, y se excluye de su propio archivo (HU23-E04, E05).
6. **¿Se guardan datos clínicos en la bitácora?** No; los valores sensibles se redactan y las URLs se enmascaran (HU22-E06, E07).

### Plan B
Capturas de la bitácora y del CSV abierto; demo local; mostrar el resultado de `./vendor/bin/pest --filter=HU21` (y HU22/HU23) en terminal como prueba de inmutabilidad.

---

## 5. Plan B general si falla el despliegue
1. Cambiar de inmediato a la instancia local ya levantada (misma secuencia, mismos usuarios).
2. Si falla local, mostrar el video de la demo completa y las capturas por HU.
3. Respaldo de evidencia: `docs/evidencia/HU-15.md` … `HU-23.md` y la salida de `./vendor/bin/pest --filter="HU1[5-9]|HU2[0-3]"`.
4. Decir con claridad que el arranque en frío del hosting gratuito es una limitación de infraestructura, no del sistema.

## 6. Brechas conocidas (presentarlas como siguientes pasos)
| Brecha | Ubicación | Siguiente paso |
|---|---|---|
| ~~Exportación usa valores actuales del formulario~~ | `resources/js/Pages/Reportes/Index.vue` | Resuelto: usa `parametros_aplicados` y avisa si los filtros cambiaron |
| ~~Lecturas clínicas sin auditar~~ | `SecureSearchController.php`, `ConsultaController.php` | Resuelto: auditadas con `ClinicalAccessAuditor` |
| ~~`alertas:reevaluar` sin programar~~ | `routes/console.php`, `docker/entrypoint.sh` | Resuelto: corre cada hora; en Render gratuito se pausa si el servicio duerme |
| Inmutabilidad en BD depende de usuario no propietario y de un bypass de pruebas | migración `2026_10_24_150000_allow_audits_immutability_bypass_for_test_cleanup.php` | Verificar privilegios en producción |
| Sin versión de rúbrica vigente en producción hasta crearla | `RubricaDemoSeeder.php:37` | Crear versión validada antes de la demo |
