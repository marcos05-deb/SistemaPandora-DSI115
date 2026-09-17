# Lecciones Aprendidas — Estándar de Prompts para Historias de Usuario (Sistema PANDORA)

Este documento resume lo aprendido durante la ejecución de las historias US-08, US-09 y US-10, y define la estructura obligatoria que debe tener todo prompt de implementación de una historia de usuario en este proyecto. El objetivo es que ninguna historia futura repita los problemas ya detectados y corregidos.

---

## 1. Incidentes y hallazgos reales de este sprint (contexto)

| # | Qué pasó | Historia | Lección |
|---|----------|----------|---------|
| 1 | Fuga de PHI cross-área en `PacienteController@show` por `withoutGlobalScope(AreaScope::class)` sin restricción de columnas | US-09 | Todo bypass de `AreaScope` debe auditarse como si fuera un hallazgo de seguridad, no una decisión técnica menor |
| 2 | Test de `ConsultaTest` "pasaba" falsamente porque `actingAs()` bypaseaba el flujo real y nunca inyectaba `_sym_key` en sesión — los escenarios de rechazo cross-área y expediente cerrado nunca se verificaron de verdad | US-08 | Un test en verde no es evidencia de nada si el fixture bypasea el mecanismo real que se supone debe probar |
| 3 | Agente modificó unilateralmente `sprint_2_backlog.md` para insertar una historia nueva (US-06c) sin autorización | US-09 | El agente puede **proponer** cambios de alcance, nunca ejecutarlos sobre archivos de planeación sin aprobación explícita |
| 4 | Commit de fix de seguridad hecho directo sobre `dev` sin pasar por rama + PR | US-09 | GitHub Flow es innegociable incluso para fixes triviales o urgentes |
| 5 | Roles verificados con `v-if` hardcodeado en Vue (`roles.includes('area_coordinator')`) | US-10 (primer intento) | Viola `ESTANDARES.md` §2 explícitamente; toda autorización debe resolverse en backend vía Policy y viajar como prop `can.*` |
| 6 | Policy escrita contra una relación (`$user->areas`, plural) que no coincidía con lo verificado en la fase de auditoría (`area_id` singular vía `profesional`) | US-10 (primer intento) | El código de la Policy debe citar y coincidir exactamente con la relación confirmada en la Fase de Verificación, no con una asunción "razonable" |
| 7 | Colisión de códigos de error: la Policy y el controlador competían por decidir 403 vs 422 para el mismo caso | US-10 (primer intento) | Cada código de error debe tener una única fuente de verdad explícita y declarada antes de codear |
| 8 | Bug de sintaxis (`!==` en un `where()` de Collection) que habría fallado en runtime | US-10 (segundo intento) | Los planes en Markdown no se ejecutan; siempre verificar sintaxis real antes de aprobar |
| 9 | Uso de `User` en vez de `Especialista` como type-hint, dependiente de `config('auth.providers.users.model')` | Recurrente | Cualquier type-hint de modelo autenticable debe verificarse contra la configuración real, no asumirse por convención de Laravel |
| 10 | `DerivacionTest` fallaba intermitentemente (🔴 en un diagnóstico, 🟢 en otro) por diferencias de entorno de ejecución (credenciales DML vs DDL en `RefreshDatabase`), sin que el agente marcara la inconsistencia como hallazgo hasta que se le exigió correr el mismo comando 3 veces | Auditoría de tests | Un resultado de test que cambia entre corridas sin cambios de código de por medio es en sí mismo un hallazgo crítico (no determinismo), y debe reportarse como tal, no explicarse post-hoc como "no hay problema" |
| 11 | El fix de infraestructura de testing (mover `RefreshDatabase` a `TestCase.php`) tuvo un efecto colateral real: destapó un bug preexistente en `LoginTest` (JWT) que estaba oculto porque el `setUp()` moría antes de llegar a la aserción real | Auditoría de tests | Cuando un test empieza a fallar justo después de un cambio de infraestructura, no se asume "es ajeno" — se compara explícitamente el comportamiento antes/después del cambio antes de archivarlo como no relacionado |
| 12 | Un hallazgo de "falso positivo" en ISO 27001 se basó en inspeccionar el `.env` del host en vez de las variables reales del contenedor Docker donde corre el test | Auditoría de tests | Para reclamos de infraestructura (roles de BD, permisos, variables de entorno) siempre verificar el entorno de ejecución real, nunca el archivo de configuración que el agente asume que aplica |
| 13 | El agente propuso un cambio estructural mayor (consultas obligatoriamente ligadas a una cita previa) a mitad de sprint, sin analizar el impacto en historias ya cerradas (US-08, US-11, US-12) ni resolver sus propias preguntas de negocio abiertas antes de proponer el esquema de base de datos | Propuesta fuera de sprint | Todo cambio de arquitectura o de reglas de negocio que afecte historias ya implementadas debe presentarse primero como análisis de impacto (qué historias se reabren, qué preguntas de negocio quedan sin responder) — nunca como plan técnico de migración antes de que el dueño del producto decida si el cambio procede y cuándo |
| 14 | La propuesta de citas obligatorias sugería `migrate:fresh` como solución aceptable "porque estamos en desarrollo", a pesar de que ya existían datos de seed acumulados de varias historias | Propuesta fuera de sprint | Nunca tratar la pérdida de datos de desarrollo/seed como algo trivial; cualquier migración estructural debe plantearse como transformación de datos, no como borrón y cuenta nueva, incluso en entornos de desarrollo |
| 15 | El agente solo extendía el seeder con los datos de la historia nueva en cada entrega. Como el trabajo normal de desarrollo (migraciones, `RefreshDatabase` en tests, `migrate:fresh` local) borra la base de datos completa, el usuario terminaba con un seed parcial y tenía que reconstruir manualmente los datos de historias anteriores para poder probar de punta a punta | Recurrente desde US-08 | El seeder debe ser siempre un **único punto de verdad acumulativo**: cada entrega debe dejar sembrados los datos de **todas** las historias completadas hasta el momento (pacientes, expedientes en distintos estados, consultas, citas en sus distintas variantes), no solo los de la historia actual. Nunca asumir que el usuario conservará el seed de la ronda anterior |
| 16 | Al implementar US-13 se descubrió que el constraint único de `citas` creado en US-11 (`unique(profesional_id, fecha_hora)`) era incondicional, lo que bloquearía permanentemente un horario en cuanto una cita se cancelara o reprogramara — un bug real ya presente en una historia previamente aprobada y cerrada | US-13 | Al modificar el esquema de una tabla ya existente, siempre revisar si las restricciones/constraints creados en historias anteriores siguen siendo válidos con los nuevos estados o campos que se introducen. Un bug encontrado así se trata como corrección retroactiva explícita (se declara qué historia lo introdujo), no como un simple ajuste de la historia actual |
| 17 | Antes de aplicar un cambio de esquema que agrega una restricción única/parcial sobre una tabla con datos ya sembrados, se exigió verificar con una consulta SQL directa (`GROUP BY ... HAVING COUNT(*) > 1`) que no existan filas que ya violen la nueva regla, en vez de asumir que el seed está limpio | US-13 | Toda migración que agregue una restricción de unicidad (total o parcial) sobre una tabla con datos existentes debe ir precedida de una verificación SQL directa de colisiones, para saber si hace falta limpieza/migración de datos antes de aplicar el cambio de esquema |
| 18 | Un fix de infraestructura de testing (`GRANT ALL PRIVILEGES`) resolvía el síntoma pero violaba directamente el principio de mínimo privilegio que el propio test de compliance (ISO 27001 A.14) existía para proteger | US-14 | Todo fix de infraestructura debe revisarse contra los controles de compliance que ya existen en el proyecto antes de aplicarse — "hace que el test pase" no es lo mismo que "cumple el principio que el test verifica" |
| 19 | Al quedarse sin diagnóstico claro, el agente entró en un ciclo largo de prueba-y-error (~15+ ejecuciones del mismo test, edición de archivos no relacionados como `app.php`, `web.php`, `TestCase.php`, incluso `DROP SCHEMA public CASCADE` manual por consola) sin aislar la causa raíz, agotando tokens sin resolver el problema | US-14 | Ante un test que falla repetidamente, exigir SIEMPRE el mensaje de error y stack trace literal completo antes de permitir un segundo intento de fix; cambiar una sola variable a la vez; nunca permitir más de 2-3 intentos de "prueba y error" sin exigir un diagnóstico explícito de causa raíz |
| 20 | La causa raíz real de una sesión de debugging larga resultó ser algo tan simple como ejecutar los tests en un contexto de red distinto al habitual (fuera del contenedor Docker en vez de `docker exec pandora-app ...`), lo que hacía que el hostname interno `postgres` no resolviera — el agente casi crea un `.env.testing` permanente con IP/puerto de host, lo cual habría introducido una divergencia de configuración nueva sin necesidad | US-14 | Cuando un error de conectividad a servicios (DB, cache, etc.) aparece de la nada en mitad de una sesión, confirmar primero si el comando se está ejecutando en el mismo contexto (contenedor vs. host) que todas las ejecuciones anteriores exitosas, antes de crear configuración nueva permanente para "solucionarlo" |
| 21 | Se encontró una relación Eloquent inexistente (`Profesional::usuario()`) usada en un `with()`; la tentación inicial fue agregar un alias duplicado (`usuario()` como copia de `especialista()`) en vez de corregir el código que llamaba a la relación equivocada | US-14 | Cuando el código llama a una relación/método que no existe, la primera opción a evaluar es corregir la llamada para usar la relación real ya existente — no crear un alias nuevo que duplique significado y genere ambigüedad futura sobre cuál usar |

---

## 2. Estructura obligatoria de todo prompt de implementación de HU

Todo prompt para implementar una historia de usuario en PANDORA debe forzar al agente a pasar por las siguientes fases, en este orden, sin saltarse ninguna:

### Fase 1 — Verificación con evidencia (no asunciones)
- Confirmar que las historias prerrequisito están implementadas y que **su suite de tests corre realmente en verde hoy**, no según lo que diga el backlog.
- Citar código real (archivo + líneas) para todo lo que se dé por sentado: modelos, relaciones, scopes, casts, convenciones de sesión/autenticación.
- Si algo se verificó en una historia anterior pero el código pudo haber cambiado desde entonces, se vuelve a verificar — no se reutiliza la verificación anterior de memoria.

### Fase 2 — Auditoría de riesgo específica de la historia
- Identificar si la historia requiere algún `withoutGlobalScopes()` / bypass de scope. Si sí, aplicar la regla ya documentada en `ESTANDARES.md`: limitarlo a `exists()`/`count()`/columnas explícitas no sensibles, con comentario justificando por qué es seguro, y presentarlo para aprobación antes de escribirlo.
- Declarar explícitamente, antes de codear, cuál capa (Policy, Form Request, Controller) es la única fuente de verdad para cada código de error (403 vs 404 vs 422), de forma que no compitan ni se pisen.
- Señalar cualquier verificación de rol o permiso que vaya a tocar el frontend, y confirmar que se resolverá en backend (prop `can.*`), nunca con roles hardcodeados en Vue.

### Fase 3 — Plan y decisiones de diseño (requiere aprobación antes de codear)
- Presentar el plan completo de cambios (migraciones, modelos, controlador, Form Request, Policy, rutas, vista, tests).
- Toda decisión de UI (modal vs. página completa, ubicación de botones, flujo de confirmación) debe justificarse explícitamente contra la sección de patrones de UI de `ESTANDARES.md`, citando la regla concreta — no "porque se parece a otra historia".
- Cualquier ambigüedad debe presentarse como pregunta abierta con una recomendación, nunca resolverse en silencio.
- **No se escribe código de producción hasta que el plan de esta fase esté aprobado explícitamente.**

### Fase 4 — Tests (ver sección 3, más estricta a partir de ahora)

### Fase 5 — Seed de base de datos antes de presentar resultados (nuevo, y ahora acumulativo)
Antes de dar por terminada la historia y presentar resultados:
- El seeder debe dejar la base de datos con **datos de prueba de todas las historias completadas hasta el momento**, no solo de la historia actual. El trabajo normal de desarrollo (migraciones nuevas, `RefreshDatabase` en tests, `migrate:fresh` local) borra la base completa, así que un seed parcial obliga al usuario a reconstruir manualmente lo que ya se había probado antes — esto no es aceptable.
- Concretamente, cada entrega debe verificar y, si falta, **agregar** al seeder (no reemplazar) los datos de: pacientes con distintos estados de expediente (abierto, en_atención, cerrado), consultas ya registradas, citas en sus distintas variantes relevantes (programada pasada, programada hoy, programada futura, asistida, no asistida, cancelada, reprogramada — según qué historias ya estén implementadas), y usuarios de cada rol (especialista, coordinador, referente psicosocial).
- Antes de presentar resultados, ejecutar `migrate:fresh --seed` (o el comando equivalente del proyecto) desde cero y confirmar que el sistema queda listo para probar **todas** las historias completadas hasta ahora de punta a punta, no solo la más reciente.
- Confirmar en el resumen final qué credenciales/usuarios de prueba corresponden a qué rol y qué escenario de cada historia, para que no haya que adivinar ni reconstruir nada.
- Si el seeder ya existía, extenderlo — no crear un seeder paralelo duplicado ni dejar huérfanos los datos de historias anteriores.

### Fase 6 — Control de versiones
- Todo el trabajo va en una rama nueva desde `dev`. Nunca commits directos a `dev` o `main`, sin excepción, incluso para fixes triviales o urgentes.
- El merge se hace vía Pull Request.
- El agente puede *proponer* cambios de alcance al backlog (nuevas historias, reordenamientos), pero nunca los escribe directamente sobre los archivos de planeación sin aprobación explícita.

---

## 2.1 Manejo de propuestas de cambio estructural o de alcance a mitad de sprint

Cuando el agente (o el propio equipo) identifica una posible mejora de arquitectura o de reglas de negocio que **afecta historias ya implementadas o el flujo general del sistema**, no se trata como una historia más a implementar de inmediato. Se sigue este proceso:

1. **Nunca se presenta primero como plan técnico.** Antes de proponer migraciones, modelos o controladores, se presenta como un análisis de impacto:
   - Qué historias ya cerradas quedarían afectadas o tendrían que reabrirse.
   - Qué preguntas de negocio deben responderse antes de que el esquema de datos tenga sentido (si esas preguntas quedan sin responder, es señal de que el diseño no está listo).
   - Si el cambio es una restricción fuerte (ej. relación `unique`/`NOT NULL` nueva) o un acoplamiento suave (ej. relación opcional), y por qué se eligió ese nivel de rigidez.
2. **Se evalúa si el problema real tiene una solución más simple ya prevista en el backlog** antes de proponer una restricción estructural nueva. Muchas veces el backlog ya contempla el mecanismo correcto en otra historia (ej. el seguimiento de adherencia vía asistencia a citas, en vez de bloquear el registro clínico con una cita obligatoria).
3. **Nunca se usa `migrate:fresh` o cualquier operación destructiva como solución "porque estamos en desarrollo"** si ya existen datos de seed acumulados útiles para pruebas manuales. Toda migración estructural se diseña como transformación de datos.
4. **Se documenta como propuesta separada** (archivo aparte, nunca escrito directo sobre el backlog activo) y se decide explícitamente si entra al sprint actual o queda para uno futuro — nunca se asume que "ya que se identificó, hay que resolverlo ya".
5. El dueño del producto decide el timing; terminar el sprint en curso sobre una base ya validada tiene prioridad sobre absorber cambios estructurales a mitad de camino, salvo que el hallazgo sea un problema de seguridad o cumplimiento (en cuyo caso aplica el protocolo de incidente, no este proceso).

---

## 3. Estándar estricto de testing (regla anti-trampa)

Esta es la regla más importante de este documento y debe citarse literalmente en cada prompt de HU:

> **Si un test falla, la respuesta por defecto es corregir el código de producción, no el test.**
>
> Modificar o relajar un test **solo** es válido cuando se demuestra, con evidencia concreta, que el test en sí está mal escrito — por ejemplo:
> - Usa un dato, endpoint o campo que ya no existe o cambió de nombre (ej. `codigo` vs `carnet`).
> - Tiene un error de sintaxis o de aserción que no corresponde a ningún criterio de aceptación real.
> - Bypasea el mecanismo real que se supone debe probar (como pasó con `_sym_key` en `ConsultaTest`), ocultando falsos positivos.
>
> **No es válido modificar un test porque:**
> - "Es más rápido cambiar el test que investigar por qué falla."
> - El test revela un comportamiento que no esperábamos pero que **sí es requerido por el criterio de aceptación Gherkin de la historia**.
> - El fallo es intermitente y no se investigó la causa raíz.
>
> Cada vez que se modifique un test ya existente (no uno nuevo), el agente debe declarar explícitamente:
> 1. Qué decía el test antes.
> 2. Por qué esa expectativa era incorrecta (con evidencia: código real, criterio de aceptación, o especificación del backlog).
> 3. Confirmación de que el cambio no reduce la cobertura de ningún escenario Gherkin de la historia.
>
> Si existe la más mínima duda de si el test está mal o el código está mal, se trata como si el código estuviera mal hasta demostrar lo contrario.

Adicionalmente:
- Todo test debe usar Pest con sintaxis `expect()` y patrón AAA — prohibido usar aserciones estáticas de PHPUnit.
- Todo test de autenticación debe reproducir el flujo real de sesión (si el sistema depende de datos inyectados en sesión durante el login real, el test debe inyectarlos explícitamente en su `beforeEach`, no asumir que `actingAs()` es suficiente).
- Los tests deben cubrir **todos** los escenarios Gherkin de la historia, sin excepción, incluyendo los de rechazo/error, no solo el camino feliz.
- Cuando la historia lo amerite (mutación de datos clínicos, cambios de estado, acciones irreversibles), el test debe verificar explícitamente el registro correspondiente en el audit log — no asumir que el trait `Auditable` lo cubre "automáticamente" sin comprobarlo.
- Antes de dar una historia por cerrada, se debe correr la suite completa (`vendor/bin/pest`), no solo el archivo de test nuevo, y reportar si hay regresiones nuevas frente a la última línea base conocida.

---

## 4. Certificaciones y estándares de cumplimiento (Compliance)

Todo prompt de implementación de HU debe recordar explícitamente que el pipeline de CI y el propio diseño del sistema auditan de forma incondicional el cumplimiento de:

- **OWASP ASVS (Application Security Verification Standard) Nivel 2/3**, en particular:
  - **V4 — Control de Acceso**: toda autorización pasa por Policies, nunca por lógica ad-hoc en controlador o frontend.
  - **V6 — Criptografía en Reposo**: todo campo con PHI se cifra vía `EncryptedFieldCast` con la clave de área correspondiente, nunca se expone sin cifrar en un Resource/prop de Inertia.
  - **V11 — Lógica de Negocio**: las transiciones de estado (ej. `abierto` → `en_atencion` → `cerrado`) deben validarse explícitamente, rechazando transiciones inválidas.
  - **V14 — Configuración Segura**: variables de entorno sensibles (`AREA_KEY_SECRET`, etc.) nunca se hardcodean ni se loguean.
- **HIPAA**: acceso a PHI limitado estrictamente al área/rol autorizado; toda lectura y escritura de expedientes debe quedar trazable.
- **ISO/IEC 27001 (Anexo A.14)**: segregación de privilegios de infraestructura de base de datos — ninguna migración, seeder, ni consulta de aplicación debe requerir o asumir privilegios DDL cuando solo necesita DML.

Cada plan de implementación debe declarar explícitamente contra cuál(es) de estos controles impacta la historia (por ejemplo, "esta historia toca V4 y V6 porque introduce un nuevo campo cifrado y una nueva regla de autorización"), y el testing debe incluir al menos un caso que verifique ese control específico, no solo la funcionalidad de negocio.

---

## 5. Plantilla resumida de prompt (usar como base para cada nueva HU)

```
Implementa la historia de usuario **US-XX: [título]** definida en `sprint_2_backlog.md`,
siguiendo `ESTANDARES.md` completo. No escribas código hasta completar y obtener aprobación
de las Fases 1 a 3.

FASE 1 — Verificación con evidencia:
- Confirma prerrequisitos implementados y con tests EN VERDE HOY (ejecuta la suite y pega la salida).
- Cita código real (archivo + líneas) de todo lo que asumas: modelos, relaciones, scopes, sesión.

FASE 2 — Auditoría de riesgo:
- Declara si necesitas bypass de AreaScope y justifícalo según la regla de ESTANDARES.md.
- Declara una única fuente de verdad por cada código de error (403/404/422).
- Confirma que ninguna verificación de rol/permiso quedará hardcodeada en Vue.

FASE 3 — Plan y diseño (requiere mi aprobación antes de codear):
- Presenta el plan completo de cambios.
- Justifica toda decisión de UI contra la sección de patrones de ESTANDARES.md.
- Señala ambigüedades como preguntas abiertas, no las resuelvas en silencio.

FASE 4 — Tests (regla estricta):
- Si un test falla: por defecto se corrige el CÓDIGO, no el test.
- Solo se modifica un test si se demuestra que el test está mal escrito (con evidencia).
- Todo cambio a un test existente debe declarar: qué decía antes, por qué estaba mal,
  y confirmación de que no se pierde cobertura de ningún escenario Gherkin.
- Pest + expect() + AAA. Cobertura de TODOS los escenarios Gherkin, incluidos los de rechazo.
- Verifica explícitamente el audit log cuando la historia mute datos clínicos o estado.
- Corre la suite COMPLETA antes de reportar terminado, no solo el archivo nuevo.

FASE 5 — Seed antes de presentar resultados:
- Actualiza o extiende el seeder para que existan datos de prueba de esta historia
  (camino feliz Y casos de rechazo).
- Indícame qué usuarios/roles de prueba usar para probar manualmente de punta a punta.

FASE 6 — Control de versiones:
- Rama nueva desde `dev`, PR para mergear. Nunca commits directos a dev/main.
- Cambios de alcance al backlog se PROPONEN, nunca se escriben directo sin mi aprobación.

COMPLIANCE:
- Declara contra qué controles de OWASP ASVS (V4/V6/V11/V14), HIPAA e ISO 27001 A.14
  impacta esta historia, y qué test verifica específicamente ese control.
```

---

*Documento vivo — actualizar tras cada historia que revele un problema nuevo no cubierto aquí.*