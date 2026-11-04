# Guion de defensa — Sprint 3 (Sistema Pandora, DSI115)

Despliegue: https://sistemapandora-dsi115.onrender.com (Render, plan gratuito, arranque en frío de ~50 s).
Épicas: PAN-5 Alertas y seguimiento preventivo (HU-15, HU-16, HU-17), PAN-6 Analítica y reportes (HU-18, HU-19, HU-20), PAN-7 Auditoría y trazabilidad (HU-21, HU-22, HU-23).
Este guion complementa `docs/defensa-sprint3.md` (tablas de demo, evidencia técnica y preguntas). Todas las horas son de El Salvador. Los textos de pantalla se verificaron contra las páginas Vue y las rutas de `routes/web.php`; lo que no pudo verificarse se marca con "(verificar en ensayo)".

Convenciones: [Integrante 1] = PAN-5, [Integrante 2] = PAN-6, [Integrante 3] = PAN-7. Las contraseñas nunca se escriben aquí: "contraseña definida por el equipo".

---

# PARTE A — Qué debe existir en el sistema antes de la defensa

## A.0 Hallazgos de la verificación contra el código (leer primero)

1. **Producción no siembra datos por sí sola.** `docker/entrypoint.sh` solo ejecuta `migrate --database=pgsql_admin --force` y `pandora:reconciliar-datos-lab2`; no ejecuta seeders. Las migraciones crean únicamente la fila `rubricas` ("riesgo_preventivo", sin versiones) y renombran los roles (`2026_11_03_120000_standardize_role_display_names`), pero no crean roles, áreas, carreras ni usuarios. `RubricaDemoSeeder` y `DashboardCasosDemoSeeder` además se saltan solos fuera de `APP_ENV` local/testing.
2. **Ruta principal acordada: comando de preparación.** Otro integrante del equipo está construyendo el comando idempotente `php artisan pandora:preparar-sprint3 --force`, que el entrypoint ejecuta tras las migraciones cuando existen las variables de entorno `SEED_SPRINT3=1` y `SPRINT3_DEMO_PASSWORD` (nombres exactos y salida en logs: verificar en ensayo). Este documento describe ese comando según lo acordado; no se verificó su código.
3. **Crear usuarios desde la interfaz NO sirve para la demo.** `Admin\UserController@store` crea el usuario con `Str::random(64)` como contraseña y envía por correo un enlace firmado a `password.setup`; pero esa ruta está dentro del grupo `auth` (`routes/web.php`), así que un invitado con el enlace es enviado al login, y no puede iniciar sesión porque no conoce la contraseña. Además el proyecto no tiene `config/mail.php` ni variables `MAIL_*` en `.env.example`, por lo que el envío del correo probablemente falla o solo se escribe en el log (verificar en ensayo). La pantalla incluso promete "Podrás copiarla en la siguiente pantalla" y el controlador no lo implementa. Conclusión: las cuentas deben crearse con el comando o con el seeder, no con "Nuevo Usuario". Tampoco existe cambio de contraseña desde el panel de administración.
4. **La rúbrica sí puede crearse desde la interfaz**, pero solo con la cuenta del Coordinador de área (`RubricaPolicy`: `hasRole('area_coordinator')` con perfil profesional). El Administrador del sistema no puede.
5. **Los límites de ritmo (throttle) afectan la demo**: `/reportes/generar` 10 por minuto; `/reportes/exportar/pdf` y `/excel` 5 por minuto; `/admin/bitacora/exportar` 5 por minuto; `/admin/bitacora` 30 por minuto. No repetir exportaciones en ráfaga.
6. **La asistencia de una cita solo puede registrarse cuando su hora ya pasó** (`CitaAsistenciaRequest`: error "No se puede registrar asistencia antes de la hora programada"), y una cita solo se crea a futuro (`after:now`) y desde una consulta activa del expediente. Por eso la ruta de ausencias necesita citas preparadas con antelación (el comando las deja ya vencidas y pendientes).

## A.1 Ruta principal: ejecutar el comando de preparación en Render

Qué crea, según lo acordado (verificar en ensayo el detalle exacto):
- Roles, áreas (Psicología, Medicina General, Fisioterapia, Nutrición, Trabajo Social) y catálogos de facultades y carreras.
- Las 6 cuentas demo (tabla A.4), con la contraseña tomada de `SPRINT3_DEMO_PASSWORD`.
- Una versión vigente y validada de la rúbrica: ausencias umbral 1 en 30 días (basta una inasistencia), crisis psicológica umbral 1 en 90 días, tendencia suicida umbral 1.
- Volumen para analítica: al menos 6 pacientes en Psicología y algunos en otras áreas.
- Un paciente de Psicología sin alerta, con 2 citas pasadas pendientes de asistencia, para la alerta en vivo de HU-16. Su carnet se imprime en los logs del despliegue.
- Pacientes de respaldo: uno con alerta activa y otro con alerta cerrada.

Pasos en el panel de Render (servicio web → Environment):
1. Agregar `SEED_SPRINT3=1` y `SPRINT3_DEMO_PASSWORD=<contraseña definida por el equipo>`. Debe cumplir la política de contraseñas de la app (mínimo 8, mayúscula, minúscula, número y símbolo; ver `PasswordSetupController`).
2. Confirmar que el despliegue incluye el comando (merge de `feat/ui` a `main`, ver A.2) y lanzar un deploy manual.
3. Abrir Logs y confirmar, en orden: migraciones sin errores, ejecución de `pandora:preparar-sprint3`, y la línea con el **carnet del paciente de la alerta en vivo** (formato exacto: verificar en ensayo). Anotar ese carnet.
4. **Eliminar `SEED_SPRINT3`** de las variables de entorno y redeplegar o reiniciar, para que no se vuelva a ejecutar (el comando es idempotente, pero no conviene dejarlo activo). Conservar o eliminar `SPRINT3_DEMO_PASSWORD` según decida el equipo.
5. Iniciar sesión con cada cuenta (tabla A.4) para comprobar que entran sin pedir cambio de contraseña (verificar en ensayo: el seeder local deja `must_change_password = false`; si el comando deja `true`, completar `/password/setup` una vez por cuenta y antes del día de la defensa).

Si el comando falla o no está disponible, usar la ruta de respaldo A.5.

## A.2 Checklist ordenado por momento

### Días antes
- [ ] Merge de `feat/ui` a `main`; confirmar en Render que el último commit desplegado es el esperado.
- [ ] Definir `SEED_SPRINT3=1` y `SPRINT3_DEMO_PASSWORD`; desplegar; revisar logs (A.1). Quitar `SEED_SPRINT3`.
- [ ] Revisar en logs que arrancó `schedule:work` (entrypoint, solo si `RUN_SCHEDULER` no es 0). Verificar en ensayo: el log del scheduler solo muestra salida cuando hay tareas que ejecutar; la tarea `alertas:reevaluar` corre cada hora.
- [ ] Iniciar sesión con las 6 cuentas y recorrer una pantalla de cada una.
- [ ] Comprobar en `/rubricas` (cuenta coordinador) que hay una versión con etiqueta **Vigente**, con las tres señales y los umbrales esperados.
- [ ] Comprobar volumen en `/indicadores` (coordinador): Psicología muestra números, no "Oculto por privacidad" (ver A.3.3).
- [ ] Guardar capturas y un video corto de cada flujo fuera del navegador.
- [ ] Preparar el plan B local (A.6).
- [ ] Verificar privilegios de BD: que el usuario de la app (`pandora_app`) no sea propietario de `audits` (si lo es, los REVOKE y triggers no protegen). Anotar el resultado para las preguntas del jurado.

### El día anterior
- [ ] **Ensayo completo en producción, con una restricción**: NO guardar la versión de rúbrica "Programada" del paso 1.3 del guion; en el ensayo basta enviar el formulario incompleto para ver el error de validación. Una versión guardada fija una vigencia posterior y la versión de la demo real tendría que ser aún más tardía (regla: la vigencia debe ser posterior a la última versión).
- [ ] No marcar las dos citas pendientes del paciente en vivo en el ensayo (se consumirían). Para ensayar la alerta usar el paciente de respaldo o repetir el comando solo si el equipo confirma que reinicia ese escenario (verificar en ensayo).
- [ ] Anotar la hora del ensayo: los eventos del ensayo aparecerán en la bitácora (sirven de actividad, pero conviene que el filtro del paso 3 use la fecha de hoy).
- [ ] Cargar el celular/portátil con respaldo local y comprobar que levanta.

### 30 minutos antes
- [ ] Abrir la URL y recorrer `/login` para despertar Render (~50 s). Repetir a los 2 minutos.
- [ ] Abrir una ventana de incógnito por cuenta y dejar sesión iniciada (tabla A.4).
- [ ] Tener a mano el carnet del paciente en vivo y los archivos de respaldo (capturas, video, `docs/evidencia/HU-15.md` … `HU-23.md`, `docs/auditoria/matriz_cobertura.md`).
- [ ] Confirmar que el reloj del equipo y el de los filtros están en hora de El Salvador.
- [ ] Verificar que el navegador permite descargas y que la carpeta de descargas está vacía y visible.

### 2 minutos antes
- [ ] Refrescar una pestaña de cada ventana (mantiene la sesión activa; el tiempo de sesión es 120 min).
- [ ] Silenciar notificaciones del sistema operativo y cerrar pestañas ajenas a la demo.
- [ ] Cada integrante confirma en qué ventana empieza.

## A.3 Datos que deben existir (con volumen)

### A.3.1 Catálogos
Áreas, facultades y carreras vienen de `AreaSeeder` y `FacultadCarreraSeeder`; el comando los incluye. Si el registro de pacientes muestra el selector de carrera vacío, el catálogo no existe (síntoma a verificar en ensayo).

### A.3.2 Pacientes y expedientes por escenario
| Escenario | Qué debe existir | Quién lo registra |
|---|---|---|
| Alerta en vivo (HU-16) | 1 paciente de Psicología, sin alertas, con 2 citas pasadas en estado "programada" pendientes de asistencia, ambas del mismo expediente | Lo deja el comando (carnet en logs). Manualmente: Referente psicosocial registra el paciente y lo deriva a Psicología (HU-07); Especialista de Psicología registra una consulta con crisis = No y agenda 2 citas cercanas; esperar a que pasen sus horas |
| Alerta de respaldo y seguimiento (HU-17) | 1 alerta activa y 1 cerrada en pacientes distintos | Lo deja el comando |
| Analítica (HU-18/19/20) | Psicología con volumen suficiente; otras áreas con pocos casos (sirven para explicar la supresión) | Comando |
| Bitácora (HU-21/22/23) | Actividad reciente de varios usuarios | La genera la propia demo y el ensayo |

### A.3.3 Cuánto volumen hace falta para que los números aparezcan
`config/indicadores.php` define `min_celda = 5` y `PrivacyThreshold` nunca baja de 2. Una celda con menos de 5 casos se muestra "Oculto por privacidad" y, además, se oculta otra celda complementaria para que no se deduzca por resta. Para que Psicología muestre números:
- al menos **5 pacientes distintos** (recomendado 6 a 8, como acuerda el comando) con expediente en Psicología dentro del rango de fechas;
- al menos **5 consultas** registradas en el período (recomendado 10 o más); para "Citas asistidas" y "Citas no asistidas" al menos 5 citas de cada estado, o aceptar que esas filas aparezcan ocultas;
- indicadores de riesgo (alertas por señal) tendrán pocos casos: es normal que salgan ocultos y sirven para explicar la privacidad.
Ajustar el rango a un período que contenga los datos (máximo 366 días). Confirmar en ensayo qué indicadores salen visibles con los datos reales.

## A.4 Cuentas y quién las usa

Contraseña de todas: definida por el equipo (`SPRINT3_DEMO_PASSWORD`).

| Cuenta | Rol | Área | Quién la usa en la demo |
|---|---|---|---|
| admin@pandora.com | Administrador del sistema | — | [Integrante 2] en `/indicadores` y `/reportes`; [Integrante 3] en `/admin/bitacora` |
| coordinador@pandora.com | Coordinador de área | Psicología | [Integrante 1] (rúbrica, alertas, cierre); [Integrante 2] (indicadores y reportes); [Integrante 3] (denegado en bitácora, búsqueda segura) |
| especialista-psi@pandora.com | Especialista | Psicología | [Integrante 1] (ausencias que generan la alerta); [Integrante 3] (abre expediente, genera lectura auditada) |
| especialista@pandora.com | Especialista | Medicina General | [Integrante 1] (no ve la alerta de otra área); [Integrante 2] (acceso denegado a `/reportes`) |
| especialista-nutri@pandora.com | Especialista | Nutrición | Reserva |
| psicosocial@pandora.com | Referente psicosocial | Trabajo Social | Reserva; solo si hay que registrar y derivar un paciente en vivo |

## A.5 Plan de respaldo para preparar producción (si el comando falla)

Orden recomendado:

**R1. Sembrar desde un equipo local contra la base de Render.** Render gratuito no ofrece shell. Desde un equipo con el repositorio, configurar `.env` con los datos de conexión externa de la base de Render (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSLMODE=require`), con **los mismos `APP_KEY`, `AREA_KEY_SECRET` y `BLIND_INDEX_SECRET` que usa Render** (si difieren, los datos cifrados quedarán ilegibles para el servicio desplegado). Ejecutar `php artisan db:seed --force`. Esto crea roles, áreas, catálogos, las 6 cuentas y expedientes de ejemplo (`DatabaseSeeder`, contraseña definida en el seeder; ver el archivo, no se escribe aquí; cambiarla después si se desea). Con `APP_ENV=production` local, `RubricaDemoSeeder` y `DashboardCasosDemoSeeder` se omiten; con `APP_ENV=local` crean versiones DEMO (histórica, vigente, futura +30 días), alertas y 15 pacientes de Psicología (DG24001–015), pero la versión futura DEMO impedirá crear otra versión real durante 30 días. Elegir según convenga. Verificar en ensayo.

**R2. Rúbrica manual por interfaz** (si no hay versión vigente). Solo con `coordinador@pandora.com`:
1. Menú **Rúbrica de riesgo** → botón **Crear versión** (`/rubricas/versiones/crear`).
2. **Vigente desde (hora de El Salvador)**: el formulario sugiere ahora + 1 minuto. La vigencia debe ser futura y posterior a la última versión. Dejar la sugerencia o poner 5 a 10 minutos adelante.
3. **Fecha de validación**: hoy o una fecha pasada (no puede ser futura).
4. **Referencia documental de validación**: texto obligatorio, por ejemplo "Acta de validación del comité clínico, 2026" (dato ficticio solo si el equipo lo acepta como demostración).
5. **Justificación del cambio**: solo aparece y es obligatoria si ya existe una versión anterior.
6. Señales (el formulario ya trae estos valores): **Ausencia a cita** (Frecuencia, ventana 30 días, ponderación 1, umbral 1); **Crisis psicológica** (Frecuencia, ventana 90 días, ponderación 1, umbral 1); **Tendencia suicida** (indicador `ideas_suicidas`, Puntaje, ponderación 1, umbral 1). Las tres señales son obligatorias.
7. **Guardar versión**. Esperar a que pase la hora de vigencia, refrescar `/rubricas` y confirmar la etiqueta **Vigente**. Sin versión vigente no se generan alertas (HU16-E07).
Hacerlo el día anterior con vigencia de minutos, o con unas horas de margen.

**R3. Pacientes manuales**: Referente psicosocial → **Pacientes** → registrar paciente (asistente de 3 pasos: "Datos del Estudiante", "Familiares y Responsable", "Revisar y Guardar"; obligatorios Carnet, Nombre Completo, Dirección, Carrera, Sexo, Estado Civil, Fecha de Nacimiento, Motivo de Consulta) → en la ficha **Derivar a Área** → Psicología con motivo. Especialista de Psicología: **Registrar Consulta Clínica** (crisis psicológica = No), luego **Agendar Próxima Cita** con 2 horarios muy cercanos. Hacerlo con tiempo para que las horas hayan pasado antes de la defensa. El volumen de A.3.3 es mucho trabajo manual: preferir R1.

**R4. Si nada de lo anterior es posible**: demo 100 % local (A.6) y mostrar producción solo para login y navegación.

## A.6 Infraestructura y plan B
- [ ] `feat/ui` fusionado a `main` y desplegado.
- [ ] Logs de Render revisados: migraciones correctas, comando de preparación ejecutado, `schedule:work` iniciado (A.2).
- [ ] Calentamiento: abrir `/login` 30 y 2 minutos antes.
- [ ] Una ventana de incógnito por rol, sesión iniciada.
- [ ] Plan B local: `php artisan migrate --seed` y `npm run build` (o `npm run dev`), `php artisan serve`; con `APP_ENV=local` el seeder crea los datos DEMO. Cambiar al local de inmediato si producción no responde.
- [ ] Capturas por HU, video completo y salida de `./vendor/bin/pest --filter="HU1[5-9]|HU2[0-3]"` abiertos en una pestaña.

---

# PARTE B — Guion completo, minuto a minuto

## B.0 Criterios de aceptación de referencia (texto de Jira)

| HU | CA |
|---|---|
| HU-15 (PAN-22) | CA-1: la rúbrica contempla como señales la frecuencia de crisis psicológicas, las ausencias a citas y los indicadores de tendencia suicida definidos institucionalmente. CA-2: cada versión establece variables, reglas o ponderaciones y los umbrales que determinan cuándo generar una alerta. CA-3: toda modificación crea una nueva versión con responsable y fecha de vigencia, sin sobrescribir la rúbrica usada en evaluaciones anteriores. |
| HU-16 (PAN-23) | CA-1: el sistema recalcula la evaluación al registrar una crisis, una ausencia o una respuesta de tendencia suicida. CA-2: se genera una alerta si la frecuencia, acumulación o puntaje alcanza el umbral de la versión vigente. CA-3: la alerta registra paciente, señal, valor, umbral, versión y fecha, y notifica únicamente a los roles autorizados. |
| HU-17 (PAN-24) | CA-1: el profesional autorizado consulta la señal, el valor calculado y la versión que originaron la alerta, respetando permisos. CA-2: el seguimiento exige valoración, acción preventiva, responsable y fecha de próxima revisión. CA-3: la alerta solo se cierra con un resultado documentado; también puede descartarse justificando un posible falso positivo. |
| HU-18 (PAN-25) | CA-1: indicadores con datos agregados y totales coherentes con los registros autorizados. CA-2: filtro por período, área y tipo de indicador. CA-3: la visualización no expone nombres, identificadores directos ni notas clínicas. |
| HU-19 (PAN-26) | CA-1: el reporte exige período, área y al menos un indicador antes de generarse. CA-2: totales y agrupaciones coinciden con los filtros y la fuente autorizada. CA-3: el contenido respeta el rol y permisos del usuario. |
| HU-20 (PAN-27) | CA-1: la exportación conserva los filtros activos y los resultados visibles. CA-2: el archivo incluye fecha de generación y encabezados que identifican período e indicadores. CA-3: el archivo no contiene información sensible que el usuario no pueda consultar. |
| HU-21 (PAN-28) | CA-1: se registran creación, lectura, actualización y eliminación lógica sobre información sensible. CA-2: cada evento contiene usuario, acción, objeto, fecha y hora, e IP si está disponible. CA-3: la aplicación no permite actualizar ni eliminar eventos ya almacenados. |
| HU-22 (PAN-29) | CA-1: filtrar por usuario, acción, período y registro afectado. CA-2: resultados paginados y ordenados desde el más reciente. CA-3: solo los roles expresamente autorizados acceden a la bitácora. |
| HU-23 (PAN-30) | CA-1: la exportación contiene únicamente los eventos del rango y filtros seleccionados. CA-2: identifica al usuario que la generó y la fecha y hora. CA-3: la propia exportación queda registrada como nuevo evento de auditoría. |

## B.1 Reparto de tiempos

| Tramo | Quién | Minutos | Acumulado |
|---|---|---|---|
| Apertura | [Integrante 1] | 2 | 0:00 – 2:00 |
| Épica PAN-5 Alertas | [Integrante 1] | 9 | 2:00 – 11:00 |
| Transición | [Integrante 1] → [Integrante 2] | 0,5 | 11:30 |
| Épica PAN-6 Analítica | [Integrante 2] | 8 | 11:30 – 19:30 |
| Transición | [Integrante 2] → [Integrante 3] | 0,5 | 20:00 |
| Épica PAN-7 Auditoría | [Integrante 3] | 8 | 20:00 – 28:00 |
| Cierre | [Integrante 1] y [Integrante 3] | 3 | 28:00 – 31:00 |
| Preguntas del jurado | Quien corresponda | reserva | — |

Si el tiempo total es menor, recortar: en PAN-5 el paso 1.3 (crear versión); en PAN-6 la vista de administrador; en PAN-7 la segunda lectura auditada.

## B.2 Apertura (0:00 – 2:00) — [Integrante 1]

**Rol activo:** ninguno (diapositiva o pestaña de login ya abierta).
**Pantalla:** https://sistemapandora-dsi115.onrender.com/login con la app ya despierta.
**Qué decir (literal):**
- "Buenos días. Somos el equipo de Sistema Pandora, un sistema de expedientes clínicos para el servicio de bienestar universitario. En este Sprint 3 incorporamos tres capacidades sobre los datos clínicos que ya existían: detectar riesgo de forma preventiva, analizar la información sin exponer a ningún estudiante y dejar un rastro de auditoría que nadie puede alterar."
- "Cada uno presentará una épica: yo, alertas y seguimiento preventivo; [Integrante 2], analítica y reportes; y [Integrante 3], auditoría y trazabilidad. Todo lo que van a ver corre en el sistema desplegado, no en una maqueta."
- "Los criterios de aceptación de cada historia de usuario aparecen en pantalla o los mencionaremos al demostrarlos."
**Qué debe verse:** la pantalla de inicio de sesión.
**Si algo falla:** si el sitio no responde, esperar los ~50 s de arranque en frío y decirlo: "es una limitación del hosting gratuito"; si sigue sin responder, pasar a la instancia local.

---

## B.3 Épica PAN-5 — Alertas y seguimiento preventivo — [Integrante 1]

**Objetivo:** mostrar que el riesgo se evalúa con una rúbrica versionada, que una alerta se genera sola con evidencia y notifica solo a quien corresponde, y que no se cierra sin documentar.
**Cuentas:** coordinador@pandora.com (ventana A), especialista-psi@pandora.com (ventana B), especialista@pandora.com (ventana C, otra área).
**Dato necesario:** el carnet del paciente de la alerta en vivo (logs de Render).

### Paso 1.1 — La rúbrica vigente (HU-15)
- **Rol activo:** Coordinador de área (ventana A).
- **Pantalla/URL:** menú lateral **Rúbrica de riesgo** → `/rubricas`, título "Rúbrica institucional de riesgo".
- **Acción exacta:** mostrar la lista de versiones y señalar las etiquetas **Vigente**, **Programada** (si existe) e **Histórica**. Pulsar **Ver versión** en la vigente (`/rubricas/versiones/{id}`).
- **Qué decir:** "La universidad necesita criterios uniformes para decidir cuándo un estudiante está en riesgo. Esta rúbrica define tres señales: ausencias a citas, crisis psicológicas y tendencia suicida, cada una con su umbral. Aquí vemos la versión vigente: ausencias con umbral 1 en 30 días, crisis con umbral 1 en 90 días y tendencia suicida con umbral 1."
- **Qué debe verse:** secciones "Vigencia y validación" (con fecha de validación y referencia documental) y "Señales y umbrales" con las tres señales.
- **Criterio que se demuestra:** HU-15 CA-1 (señales) y CA-2 (variables, ponderaciones y umbrales por versión).
- **Si algo falla:** si no hay versión vigente, no continuar con la alerta en vivo; mostrar el video o aplicar la ruta R2 de la Parte A.

### Paso 1.2 — Validación obligatoria (HU-15)
- **Rol activo:** Coordinador de área.
- **Pantalla/URL:** botón **Crear versión** → `/rubricas/versiones/crear` ("Nueva versión N").
- **Acción exacta:** sin completar nada, pulsar **Guardar versión**. Debe aparecer el aviso "No se pudo guardar la versión" y errores en **Fecha de validación**, **Referencia documental de validación** y **Justificación del cambio**.
- **Qué decir:** "Una versión no se puede activar sin validación institucional: pide fecha, referencia documental y, si ya existe una versión previa, la justificación del cambio. Esto evita que un umbral clínico cambie sin respaldo."
- **Qué debe verse:** mensajes de error en rojo bajo cada campo.
- **Criterio que se demuestra:** HU-15 CA-2 (reglas por versión) y dependencia de validación institucional (descripción de PAN-22).
- **Si algo falla:** continuar con el paso 1.3 sin esta parte.

### Paso 1.3 — Nueva versión sin sobrescribir la anterior (HU-15)
- **Rol activo:** Coordinador de área.
- **Pantalla/URL:** el mismo formulario `/rubricas/versiones/crear`.
- **Acción exacta:** completar **Vigente desde** con mañana a las 08:00 (debe ser posterior a la última versión), **Fecha de validación** = hoy, **Referencia documental de validación** = "Acta comité clínico (demostración)", **Justificación del cambio** = "Ajuste de umbral de ausencias para el próximo ciclo". En la señal **Ausencia a cita** cambiar **Umbral** de 1 a 2. Pulsar **Guardar versión**. Volver a `/rubricas`.
- **Qué decir:** "Si el comité decide cambiar un umbral, no se edita la versión anterior: se crea una versión nueva, con su responsable y su fecha de vigencia. La versión vigente hoy no se modifica, y cada alerta futura guardará con qué versión se evaluó."
- **Qué debe verse:** la nueva versión con etiqueta **Programada**; la anterior sigue **Vigente** con sus valores.
- **Criterio que se demuestra:** HU-15 CA-3 (toda modificación crea una versión sin sobrescribir).
- **Si algo falla:** si el servidor rechaza la vigencia ("debe ser posterior a la última versión"), ajustar la fecha a un día posterior a la versión más tardía. No repetir en ensayos (ver A.2). Si es una repetición, omitir este paso.

### Paso 1.4 — Disparar la alerta en vivo (HU-16, ausencias)
- **Rol activo:** Especialista, Psicología (ventana B).
- **Pantalla/URL:** menú **Pacientes** → buscar el carnet del paciente en vivo → ficha (`/pacientes/{carnet}`), sección de citas pendientes.
- **Acción exacta:** en la primera cita pendiente pulsar **Ausente**. Al registrarse la inasistencia se genera la alerta (umbral 1).
- **Qué decir:** "Esta cita ya pasó y el estudiante no asistió. Al registrar la inasistencia, el sistema recalcula la evaluación con la rúbrica vigente: la primera ausencia alcanza el umbral y se genera la alerta, sin que nadie tenga que revisarlo a mano."
- **Qué debe verse:** mensaje de confirmación de asistencia registrada y la alerta creada (se comprueba en el paso siguiente).
- **Criterio que se demuestra:** HU-16 CA-1 (recálculo al registrar ausencia) y CA-2 (alerta al alcanzar el umbral).
- **Si algo falla:** (a) si no aparece **Ausente**, la hora de la cita aún no ha pasado o falta el permiso (verificar en ensayo). (b) Ruta alterna de crisis: **Registrar Consulta Clínica** en un expediente de Psicología sin alerta de crisis, marcar **Crisis psicológica = Sí** (obligatorio) y **Guardar Consulta**; con umbral 1 genera alerta al instante. (c) Alerta de respaldo ya sembrada en `/alertas`.

### Paso 1.5 — La alerta y la notificación (HU-16)
- **Rol activo:** Coordinador de área (ventana A).
- **Pantalla/URL:** icono **Notificaciones** del encabezado y menú **Alertas** → `/alertas` ("Alertas clínicas").
- **Acción exacta:** abrir **Notificaciones** (campana), verificar la nueva alerta, y entrar a `/alertas`. Mostrar la tarjeta: **Señal detectada**, **Valor calculado**, **Umbral aplicado**, **Versión de rúbrica**, **Generada**.
- **Qué decir:** "El coordinador recibe la notificación sin ningún dato clínico: solo avisa que hay una alerta. Al abrirla vemos exactamente qué la originó: la señal, el valor calculado, el umbral y la versión de la rúbrica con la que se evaluó."
- **Qué debe verse:** notificación en la campana y alerta en estado **Activa**.
- **Criterio que se demuestra:** HU-16 CA-3 (la alerta registra paciente, señal, valor, umbral, versión y fecha).
- **Si algo falla:** si la campana no muestra la alerta, recargar; si sigue sin aparecer, entrar a `/alertas` y mostrar la alerta de respaldo.

### Paso 1.6 — Solo los roles autorizados (HU-16)
- **Rol activo:** Especialista, Medicina General (ventana C).
- **Pantalla/URL:** `/alertas`.
- **Acción exacta:** abrir **Alertas** y comprobar que la alerta de Psicología no aparece (lista vacía o solo alertas de su área).
- **Qué decir:** "La alerta pertenece al área de Psicología. Un especialista de Medicina General no la ve, y tampoco la ven el administrador del sistema ni el referente psicosocial. Solo notificamos y mostramos a coordinadores y especialistas del área."
- **Qué debe verse:** estado vacío "No hay alertas registradas" o sin la alerta en vivo.
- **Criterio que se demuestra:** HU-16 CA-3 ("notifica únicamente a los roles autorizados").
- **Si algo falla:** omitir este paso y mencionarlo con la prueba HU16-E12.

### Paso 1.7 — Seguimiento preventivo (HU-17)
- **Rol activo:** Especialista Psicología (ventana B) o Coordinador (ventana A). Solo el responsable de la alerta o el coordinador pueden cerrarla.
- **Pantalla/URL:** `/alertas/{id}` (clic en la alerta; pantalla "Origen de la alerta").
- **Acción exacta:** mostrar **Origen de la alerta** (Señal, Valor calculado, Umbral aplicado, Versión de rúbrica, Vigente desde). En **Registrar seguimiento** pulsar **Guardar seguimiento** vacío para ver los errores; completar **Valoración** ("Estudiante con dos ausencias consecutivas, referido por ansiedad"), **Acción preventiva** ("Llamada de contacto y reprogramación prioritaria"), **Próxima revisión** (una fecha futura) y pulsar **Guardar seguimiento**. El campo **Responsable** se asigna solo.
- **Qué decir:** "Ver la alerta no basta: hay que actuar y dejarlo escrito. El seguimiento exige valoración, acción preventiva, responsable y fecha de próxima revisión, y queda permanente en una línea de tiempo. Intento guardar vacío y el sistema no lo permite."
- **Qué debe verse:** errores bajo "Revisa los campos marcados"; después el registro en **Seguimientos preventivos** y estado **En seguimiento**.
- **Criterio que se demuestra:** HU-17 CA-1 (señal, valor y versión de origen) y CA-2 (campos obligatorios).
- **Si algo falla:** si la alerta no se abre, usar la alerta de respaldo activa; si el responsable no coincide con el usuario, usar la cuenta de coordinador.

### Paso 1.8 — Cierre y descarte (HU-17)
- **Rol activo:** Coordinador de área (o el responsable).
- **Pantalla/URL:** `/alertas/{id}`.
- **Acción exacta:** pulsar **Cerrar alerta**, intentar **Confirmar cierre** sin **Resultado** (error), escribir "Se contactó al estudiante y se reprogramó la cita; sin riesgo inmediato" y **Confirmar cierre**. Luego abrir la alerta activa de respaldo y mostrar **Descartar alerta**: escribir una **Justificación del descarte** ("Ausencias por suspensión de transporte; falso positivo") y **Confirmar descarte**.
- **Qué decir:** "Una alerta no puede cerrarse sin un resultado documentado. Si el sistema detectó un falso positivo, se descarta, pero también con justificación. En ambos casos el registro queda en modo solo lectura."
- **Qué debe verse:** estado **Cerrada** o **Descartada** y el aviso "Esta alerta está cerrada o descartada. Solo puedes consultar sus datos."
- **Criterio que se demuestra:** HU-17 CA-3.
- **Si algo falla:** si el botón no aparece, la cuenta no es responsable ni coordinadora; cambiar a la ventana del coordinador.

### Transición literal ([Integrante 1] → [Integrante 2])
"Hasta aquí vimos cómo el sistema detecta riesgo individual y obliga a documentarlo. Ahora [Integrante 2] mostrará cómo esos mismos datos, agregados y anonimizados, sirven a la institución para decidir y se pueden exportar."

### Preguntas del jurado — PAN-5
1. **¿Qué pasa si falla la evaluación al registrar una ausencia?** El registro de la cita se conserva y el fallo queda en el log sin datos sensibles. El comando `alertas:reevaluar` corre cada hora (`routes/console.php`, `schedule:work` en `docker/entrypoint.sh`) y crea la alerta pendiente sin duplicarla. Limitación honesta: en el plan gratuito de Render el scheduler se pausa mientras el servicio duerme, así que no prometemos recuperación inmediata.
2. **¿Cambiar la rúbrica reescribe el historial?** No. Cada versión es inmutable (triggers y REVOKE en BD) y cada evaluación guarda `rubrica_version_id` (prueba HU15-E08).
3. **¿Quién ve las alertas y la notificación?** Coordinador y especialistas del área; no el sysadmin ni el referente psicosocial. La notificación no lleva datos clínicos (HU16-E09, E12; HU17-E02).
4. **¿Están validados clínicamente los umbrales?** Los valores de demostración sí están marcados `es_demo`; una versión real exige fecha y referencia de validación institucional, con restricción CHECK en BD. La validación real corresponde a la institución.
5. **¿Se puede cerrar sin documentar?** No, ni en la aplicación ni en la base de datos (HU17-E06).

---

## B.4 Épica PAN-6 — Analítica y reportes — [Integrante 2]

**Objetivo:** mostrar indicadores agregados con privacidad, un reporte parametrizado validado y su exportación fiel al reporte mostrado.
**Cuentas:** coordinador@pandora.com (ventana A), admin@pandora.com (ventana D), especialista@pandora.com (ventana C).
**Límites:** `/reportes/generar` 10 por minuto, exportaciones 5 por minuto.

### Paso 2.1 — Indicadores del coordinador (HU-18)
- **Rol activo:** Coordinador de área.
- **Pantalla/URL:** menú **Indicadores** → `/indicadores` ("Indicadores de bienestar").
- **Acción exacta:** mostrar **Resultados agregados** con el período por defecto. Cambiar **Desde** y **Hasta** al rango con datos, dejar **Área** (solo Psicología disponible), elegir en **Indicadores** (por ejemplo "Consultas registradas", "Citas asistidas", "Citas no asistidas") y pulsar **Aplicar filtros**.
- **Qué decir:** "La dirección de bienestar necesita cifras para decidir, sin conocer a nadie. Estos indicadores se calculan en el servidor con datos agregados. Se pueden filtrar por período, área e indicador. Fíjense que no hay nombres, carnets ni notas clínicas."
- **Qué debe verse:** totales por indicador y área; el coordinador solo ve su área.
- **Criterio que se demuestra:** HU-18 CA-1 (agregados coherentes), CA-2 (filtros) y CA-3 (sin identificadores).
- **Si algo falla:** si todo sale "Oculto por privacidad", ampliar el rango de fechas; si persiste, falta volumen (A.3.3), mostrar capturas.

### Paso 2.2 — Privacidad de celdas pequeñas (HU-18)
- **Rol activo:** Coordinador de área.
- **Pantalla/URL:** `/indicadores`.
- **Acción exacta:** señalar una celda que diga **Oculto por privacidad** (por ejemplo, alertas por señal) y el texto de ayuda "Oculto porque el grupo es pequeño" o "Oculto para evitar deducir otros conteos".
- **Qué decir:** "Cuando un grupo tiene menos de cinco personas, el sistema oculta la cifra, porque con números tan chicos se podría reconocer a alguien. Y oculta también una celda complementaria, para que no se pueda deducir restando los totales."
- **Qué debe verse:** celdas ocultas con su explicación.
- **Criterio que se demuestra:** HU-18 CA-3 (la visualización no expone identificadores).
- **Si algo falla:** si no hay ninguna celda oculta, explicar la regla y citar la prueba HU18-E02/E09.

### Paso 2.3 — Vista institucional (HU-18)
- **Rol activo:** Administrador del sistema (ventana D).
- **Pantalla/URL:** menú **Indicadores** (`/indicadores`).
- **Acción exacta:** dejar **Área** = "Todas las áreas permitidas" y **Aplicar filtros**.
- **Qué decir:** "El administrador ve todas las áreas. Cuando un paciente fue atendido en dos áreas, el total institucional lo cuenta una sola vez."
- **Qué debe verse:** filas por área y un **Total del alcance**.
- **Criterio que se demuestra:** HU-18 CA-1.
- **Si algo falla:** omitir y continuar con el coordinador.

### Paso 2.4 — Reporte exige parámetros (HU-19)
- **Rol activo:** Coordinador de área (o Administrador).
- **Pantalla/URL:** menú **Reportes** → `/reportes` ("Reportes personalizados", "Parámetros del reporte").
- **Acción exacta:** desmarcar todos los indicadores y pulsar **Generar reporte**.
- **Qué decir:** "Un reporte sin indicadores no tiene sentido; el sistema exige período, área y al menos un indicador antes de generar nada."
- **Qué debe verse:** bloque "Revisa los parámetros" y error bajo **Indicadores**.
- **Criterio que se demuestra:** HU-19 CA-1.
- **Si algo falla:** si el reporte se genera con valores por defecto, marcar manualmente un campo vacío (por ejemplo, borrar **Desde**).

### Paso 2.5 — Generar reporte parametrizado (HU-19)
- **Rol activo:** Coordinador de área.
- **Pantalla/URL:** `/reportes`.
- **Acción exacta:** **Desde**/**Hasta** = rango con datos, **Área** = Psicología, **Indicadores**: "Consultas registradas" y "Citas no asistidas"; **Agrupar por (máximo dos)**: "Mes" y "Indicador". Pulsar **Generar reporte**.
- **Qué decir:** "Elijo período, área, indicadores y cómo agrupar. El sistema calcula en el servidor y muestra los totales del período y los grupos. Estos valores coinciden con los de `/indicadores` para el mismo filtro y el mismo universo de datos autorizado."
- **Qué debe verse:** el bloque "Reporte generado", "Totales del período" y la tabla de "Grupos".
- **Criterio que se demuestra:** HU-19 CA-2 (totales y agrupaciones coinciden con filtros y fuente) y CA-3 (según el rol).
- **Si algo falla:** si dice "No hay registros que coincidan", ampliar el rango; si sale "Se superó el límite", esperar un minuto (throttle).

### Paso 2.6 — Exportar PDF y Excel (HU-20)
- **Rol activo:** Coordinador de área.
- **Pantalla/URL:** `/reportes` con el reporte ya generado.
- **Acción exacta:** pulsar **Descargar PDF**, esperar el mensaje "PDF descargado.", abrir el archivo; pulsar **Descargar Excel** ("Excel descargado.") y abrirlo. Señalar fecha de generación, período, indicadores y filtros en el encabezado.
- **Qué decir:** "La exportación usa los parámetros del reporte que está en pantalla, no lo que haya en el formulario. El archivo trae la fecha de generación y encabezados con período e indicadores, y ningún dato de pacientes. Además registramos un hash SHA-256 del archivo para poder verificar su integridad."
- **Qué debe verse:** PDF y Excel con encabezados y totales iguales a la pantalla.
- **Criterio que se demuestra:** HU-20 CA-1, CA-2 y CA-3.
- **Si algo falla:** si el navegador bloquea la descarga, permitirla; si hay error de límite de ritmo, esperar un minuto; abrir los archivos pregenerados como respaldo.

### Paso 2.7 — La exportación advierte si cambiaron los filtros (HU-20)
- **Rol activo:** Coordinador de área.
- **Pantalla/URL:** `/reportes`.
- **Acción exacta:** sin pulsar **Generar reporte**, cambiar **Hasta** o desmarcar un indicador. Observar el aviso de la pantalla; pulsar **Descargar PDF** y verificar que el archivo conserva los filtros del reporte mostrado. Para exportar otros filtros, generar de nuevo.
- **Qué decir:** "Si alguien cambia un filtro y exporta sin regenerar, no se exporta algo distinto de lo que está viendo. El sistema usa los parámetros del reporte mostrado y lo avisa."
- **Qué debe verse:** aviso de que la descarga usa el reporte mostrado (texto exacto: verificar en ensayo).
- **Criterio que se demuestra:** HU-20 CA-1 (conserva los filtros activos).
- **Si algo falla:** omitir este paso y mencionarlo como evidencia técnica (`exportQuery` y `filtersChanged` en `Reportes/Index.vue`).

### Paso 2.8 — Permisos por rol (HU-19)
- **Rol activo:** Especialista, Medicina General (ventana C).
- **Pantalla/URL:** escribir `/reportes` en la barra de direcciones.
- **Acción exacta:** intentar abrir la ruta.
- **Qué decir:** "El menú ni siquiera muestra Reportes a un especialista, y si intenta entrar escribiendo la dirección, el sistema se lo niega. Solo coordinadores y administradores acceden a la analítica."
- **Qué debe verse:** página de acceso denegado (403).
- **Criterio que se demuestra:** HU-19 CA-3.
- **Si algo falla:** si por alguna razón no aparece el error, citar HU19-E08.

### Transición literal ([Integrante 2] → [Integrante 3])
"Vimos alertas individuales y cifras agregadas. Todo eso son accesos y decisiones sobre información sensible, y [Integrante 3] mostrará cómo cada uno de ellos queda registrado en un rastro que nadie puede modificar."

### Preguntas del jurado — PAN-6
1. **¿Por qué hay celdas ocultas?** Para evitar reidentificar a personas en grupos pequeños (k-anonimato, mínimo 5). Si se oculta una celda, se oculta también la complementaria para que no se deduzca por resta.
2. **¿La exportación conserva los filtros?** Sí. La URL se construye con `parametros_aplicados` del reporte mostrado, no con el formulario, y la pantalla avisa si los filtros cambiaron.
3. **¿Quién ve reportes?** Coordinador (solo su área) y administrador (todas). No especialista ni referente.
4. **¿Los totales son correctos?** Las pruebas los comparan con SQL independiente (HU18-E01, HU19-E04).
5. **¿Puede alterarse un archivo exportado?** Se registra su SHA-256 en una tabla append-only para verificar integridad; no impedimos que alguien edite su copia, pero podemos detectarlo.
6. **¿Qué pasa con períodos vacíos?** Totales en cero sin error (HU18-E06, HU19-E05).

---

## B.5 Épica PAN-7 — Auditoría y trazabilidad — [Integrante 3]

**Objetivo:** mostrar que los accesos y cambios sobre datos sensibles quedan registrados, que solo el administrador consulta la bitácora, que no hay forma de editarla y que la evidencia se exporta de forma verificable.
**Cuentas:** especialista-psi@pandora.com (ventana B), coordinador@pandora.com (ventana A), admin@pandora.com (ventana D).

### Paso 3.1 — Generar actividad auditada (HU-21)
- **Rol activo:** Especialista, Psicología (ventana B).
- **Pantalla/URL:** menú **Pacientes** → ficha del paciente (`/pacientes/{carnet}`) y, si hay tiempo, **Registrar Consulta Clínica** (abrir el formulario sin guardar).
- **Acción exacta:** abrir la ficha de un paciente de Psicología (lectura del expediente) y abrir el formulario de nueva consulta. Si se desea un cambio de datos, guardar una consulta de prueba (en producción deja un dato más, evaluarlo antes).
- **Qué decir:** "Un especialista abre un expediente y el formulario de una consulta. No hizo nada fuera de lo normal, pero para un hospital o una universidad la pregunta es siempre quién miró qué y cuándo. Cada lectura, creación y cambio de datos sensibles genera un evento."
- **Qué debe verse:** la ficha y el formulario cargan con normalidad.
- **Criterio que se demuestra:** HU-21 CA-1 (lectura/creación/actualización sobre información sensible; la lectura del formulario es el evento `view_consulta_form`).
- **Si algo falla:** usar la actividad de login y la generada por los ensayos y por las épicas anteriores (el login también se audita).

### Paso 3.2 — Búsqueda segura auditada (HU-21)
- **Rol activo:** Coordinador de área (ventana A).
- **Pantalla/URL:** menú **Buscar expediente por código anónimo** → `/busqueda-segura`.
- **Acción exacta:** buscar un código anónimo existente de Psicología (de dónde se obtiene el código en pantalla: verificar en ensayo) y pulsar **Buscar**.
- **Qué decir:** "Incluso la búsqueda por código anónimo queda auditada, con el evento `view_patient_secure_search`, y no guardamos el código buscado cuando no hay resultado."
- **Qué debe verse:** el expediente encontrado.
- **Criterio que se demuestra:** HU-21 CA-1.
- **Si algo falla:** omitir y explicar que el evento existe (prueba `AuditoriaLecturasTest`).

### Paso 3.3 — Consultar la bitácora (HU-21, HU-22)
- **Rol activo:** Administrador del sistema (ventana D).
- **Pantalla/URL:** menú **Bitácora de auditoría** → `/admin/bitacora` ("Bitácora de auditoría").
- **Acción exacta:** mostrar la tabla con columnas **Fecha**, **Usuario**, **Acción**, **Objeto**, **Ruta**, **Detalle**. Localizar los eventos recién generados. Pulsar **Campos** en una fila para ver "Campos tocados (solo nombres)".
- **Qué decir:** "Cada evento tiene usuario, acción, objeto afectado, fecha y hora y, cuando está disponible, la dirección IP. Se muestra del más reciente al más antiguo. Ningún valor sensible se guarda en claro: solo los nombres de los campos tocados."
- **Qué debe verse:** eventos del especialista y del coordinador, ordenados descendentemente.
- **Criterio que se demuestra:** HU-21 CA-2 (usuario, acción, objeto, fecha y hora, IP; la IP debe verse en el detalle: verificar en ensayo) y HU-22 CA-2 (orden descendente).
- **Si algo falla:** si la tabla está vacía, ampliar **Desde**/**Hasta**; si sigue vacía, usar capturas.

### Paso 3.4 — Filtros y paginación (HU-22)
- **Rol activo:** Administrador del sistema.
- **Pantalla/URL:** `/admin/bitacora`, panel **Filtros**.
- **Acción exacta:** elegir **Usuario** = el especialista de Psicología, **Acción** = una acción visible, **Desde** y **Hasta** = hoy, opcionalmente **Registro afectado**; pulsar **Aplicar filtros**. Mostrar la paginación (25 por página) si hay suficientes eventos.
- **Qué decir:** "Para investigar un incidente hay que poder acotar: por usuario, acción, período y registro afectado. El resultado aparece paginado, de 25 en 25."
- **Qué debe verse:** lista reducida a los eventos que cumplen los filtros.
- **Criterio que se demuestra:** HU-22 CA-1 y CA-2.
- **Si algo falla:** si "Sin resultados para los filtros aplicados", relajar un filtro.

### Paso 3.5 — Solo el administrador (HU-22)
- **Rol activo:** Coordinador de área (ventana A).
- **Pantalla/URL:** escribir `/admin/bitacora`.
- **Acción exacta:** intentar abrir la ruta.
- **Qué decir:** "El coordinador de un área no puede ver la bitácora. Solo el administrador del sistema tiene ese permiso."
- **Qué debe verse:** acceso denegado (403).
- **Criterio que se demuestra:** HU-22 CA-3.
- **Si algo falla:** citar HU22-E05.

### Paso 3.6 — No se puede editar ni borrar (HU-21)
- **Rol activo:** Administrador del sistema.
- **Pantalla/URL:** `/admin/bitacora`.
- **Acción exacta:** recorrer la tabla y señalar que no hay botones de editar ni de eliminar, solo consulta y exportación.
- **Qué decir:** "No existe ninguna opción para modificar o borrar un evento. Además, en la base de datos hay permisos revocados y disparadores que rechazan cualquier UPDATE o DELETE sobre la tabla de auditoría. Aclaro un límite: esa garantía en la base depende de que el usuario de la aplicación no sea propietario de la tabla."
- **Qué debe verse:** tabla de solo lectura.
- **Criterio que se demuestra:** HU-21 CA-3.
- **Si algo falla:** mostrar en terminal `./vendor/bin/pest --filter=HU21` (prueba E04 de inmutabilidad).

### Paso 3.7 — Exportar evidencia (HU-23)
- **Rol activo:** Administrador del sistema.
- **Pantalla/URL:** `/admin/bitacora`.
- **Acción exacta:** con los filtros del paso 3.4 aplicados, pulsar **Exportar evidencia**; esperar "Evidencia descargada." y abrir el CSV. Señalar el usuario generador, la fecha y hora de generación y que solo hay eventos filtrados. Volver a la bitácora, pulsar **Aplicar filtros** y localizar el nuevo evento de exportación (acción de exportación, nombre exacto: verificar en ensayo).
- **Qué decir:** "La evidencia se exporta con exactamente los filtros que estoy viendo. El archivo identifica quién lo generó y cuándo, y trae un hash SHA-256 para verificar su integridad. Y la propia exportación queda registrada como un nuevo evento: auditar al auditor."
- **Qué debe verse:** CSV con solo los eventos filtrados y, en la bitácora, el evento de exportación nuevo.
- **Criterio que se demuestra:** HU-23 CA-1, CA-2 y CA-3.
- **Si algo falla:** si el límite de 5 por minuto bloquea, esperar; usar el CSV generado en el ensayo y la captura de la bitácora.

### Cierre (literal, 28:00 – 31:00) — [Integrante 3] y luego [Integrante 1]
- [Integrante 3]: "Resumo mi parte: cada acceso y cambio queda registrado, solo el administrador lo consulta, nadie puede alterarlo desde la aplicación y la evidencia se exporta con huella digital."
- [Integrante 1]: "En conjunto, Pandora detecta el riesgo con reglas versionadas, informa a la institución con datos que no identifican a nadie y deja un rastro verificable de todo lo que ocurre. Las nueve historias del Sprint 3 están cerradas y respaldadas por pruebas automáticas. Los límites que conocemos son tres: el formato de la evidencia es CSV y no PDF, pendiente de aceptación formal del Product Owner; la inmutabilidad en la base depende de los privilegios del usuario de la aplicación; y el planificador de alertas se pausa cuando el hosting gratuito duerme. Gracias; quedamos atentos a sus preguntas."

### Preguntas del jurado — PAN-7
1. **¿Se audita toda lectura de datos clínicos?** En las vistas clínicas sí: expediente, historial, alertas, citas, búsqueda segura (`view_patient_secure_search`) y formulario de consulta (`view_consulta_form`). La búsqueda sin resultado no se registra, para no guardar el código buscado.
2. **¿Es realmente inmutable?** La aplicación no puede modificar ni borrar: Eloquent lo rechaza y la base aplica REVOKE y disparadores (error 42501). Honestamente, esa garantía depende de que el usuario de la aplicación no sea propietario de `audits`, y existe un mecanismo de limpieza para pruebas que solo funciona con privilegio de administrador de BD. La mitigación es usar un usuario sin ownership y restringir el rol administrador en producción; está pendiente verificarlo en el despliegue.
3. **¿Por qué la evidencia es CSV y no PDF?** El CSV con SHA-256 es verificable y procesable; el formato final está pendiente de aceptación del Product Owner (comentario en PAN-30).
4. **¿Quién puede consultar la bitácora?** Solo el Administrador del sistema (HU22-E05).
5. **¿La exportación se audita?** Sí, como un evento nuevo, y se excluye de su propio archivo (HU23-E04, E05).
6. **¿Se guardan datos clínicos en la bitácora?** No: los valores sensibles se redactan y las URLs se enmascaran (HU22-E06, E07).

---

## B.6 Qué no hacer
- No iniciar la demo con la aplicación dormida ni depender del primer request en frío.
- No mostrar contraseñas, `.env`, ni la variable `SPRINT3_DEMO_PASSWORD` en pantalla; cuidado con la pestaña de Render al compartir pantalla.
- No crear usuarios desde **Nuevo Usuario**: el flujo de contraseña inicial no es usable (A.0).
- No guardar versiones de rúbrica en el ensayo (bloquean la vigencia de la versión de la demo) ni consumir las dos citas pendientes del paciente en vivo antes de tiempo.
- No exportar en ráfaga: 5 descargas por minuto como máximo.
- No modificar filtros del reporte después de **Generar reporte** esperando que la exportación cambie; generar de nuevo.
- No intentar borrar o editar eventos de auditoría por SQL con el usuario administrador (existe un bypass de limpieza de pruebas).
- No prometer recuperación inmediata de alertas tras una caída: el planificador se detiene cuando Render duerme.
- No afirmar que la inmutabilidad en BD está verificada en producción hasta comprobar los privilegios de `pandora_app`.
- No improvisar datos clínicos reales: usar solo los datos de demostración.
- No decir que las historias están "100 % validadas clínicamente": la validación de umbrales es institucional.

## B.7 Plan B general
1. Cambiar de inmediato a la instancia local ya levantada (mismos pasos y usuarios; datos DEMO con `APP_ENV=local`).
2. Si falla local, mostrar el video completo y las capturas por HU.
3. Respaldo de evidencia: `docs/evidencia/HU-15.md` … `HU-23.md`, `docs/auditoria/matriz_cobertura.md` y la salida de `./vendor/bin/pest --filter="HU1[5-9]|HU2[0-3]"`.
4. Decir con claridad que el arranque en frío del hosting gratuito es una limitación de infraestructura, no del sistema.
