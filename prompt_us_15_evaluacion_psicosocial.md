# Prompt de Implementación - US-15: Refinamiento Criptográfico de Evaluación Psicosocial y Registro de Consultas

**Atención Agente:** Eres el responsable de implementar esta historia de usuario en el Sistema PANDORA. No tienes acceso al historial de la conversación previa, por lo que todas las directrices, reglas arquitectónicas y el estándar de trabajo están definidos íntegramente en este documento. 

Implementa la historia de usuario **US-15** como continuación del Sprint 2. Debes seguir los documentos `ESTANDARES.md` y `LECCIONES_APRENDIDAS_PROMPTS.md` del repositorio al pie de la letra. 

⚠️ **REGLA ESTRICTA:** No escribas código de producción hasta completar explícitamente y obtener aprobación del usuario para las **Fases 1 a 3** descritas al final de este documento.

---

## Especificación de la Historia de Usuario (US-15)

**Épica:** PAN-3 (Expediente multidisciplinario) · **Puntos:** 8 · **Prioridad:** Alta

### Contexto y Justificación Arquitectónica:
El requerimiento original planteaba almacenar etiquetas de motivos de consulta (ej. "Ideas suicidas", "Violencia Familiar") y observaciones estructuradas en texto plano o columnas dispersas. Esto es inaceptable porque viola el cifrado en reposo (Deuda C-09 y US-06b). Para cumplir estrictamente con HIPAA y OWASP ASVS V6, esta historia implementa campos consolidados que se serializan como JSON y posteriormente se cifran con XSalsa20-Poly1305 antes de tocar el disco en PostgreSQL.

### Descripción:
"Como Especialista Clínico, quiero registrar antecedentes narrados, una matriz de evaluación inicial (solo en la primera cita), la técnica utilizada y etiquetas categorizadas de motivo de consulta, para documentar rigurosamente el proceso clínico garantizando que toda la información quede cifrada bajo la llave de mi área."

### Criterios de Aceptación:

```gherkin
Dado que el Especialista está registrando la PRIMERA consulta de un expediente
Cuando accede al formulario de nueva consulta
Entonces el sistema exige obligatoriamente rellenar la "Evaluación Inicial"
  Y la evaluación incluye: Apariencia externa, Voz, Patrones de habla, Expresiones faciales, Ademanes, Actitudes hacia el tratamiento, Impresión del estudiante, Plan de tratamiento y Pronóstico
  Y debe registrar la técnica utilizada, los antecedentes narrados y las etiquetas de motivo (ej. Violencia Familiar, Ideas Suicidas, etc.)

Dado que el Especialista está registrando la SEGUNDA (o posterior) consulta de un expediente
Cuando accede al formulario
Entonces el sistema oculta y NO exige la matriz de "Evaluación Inicial"
  Y solo requiere actualizar evolución, técnica utilizada, etiquetas de motivo y antecedentes (si aplica)

Dado que se confirma el guardado de la consulta
Cuando los datos se envían al backend
Entonces el sistema serializa las etiquetas y la matriz de evaluación inicial en formato JSON
  Y cifra absolutamente todos estos campos de texto utilizando el AREA_KEY_SECRET antes de persistirlos en PostgreSQL

Dado que un administrador de base de datos o atacante inspecciona la tabla "consultas"
Entonces los campos "etiquetas_motivo", "antecedentes_problema", "evaluacion_inicial" y "tecnica_utilizada" se observan como cadenas ilegibles de bytes (Base64)
```

### Tareas Técnicas Obligatorias:

1. **Base de Datos y Migraciones:**
   * Crear migración para añadir a la tabla `consultas`: 
     * `tecnica_utilizada` (TEXT)
     * `antecedentes_problema` (TEXT)
     * `etiquetas_motivo` (TEXT) - *Almacenará un JSON array cifrado.*
     * `evaluacion_inicial` (TEXT, nullable) - *Almacenará un JSON object cifrado. Solo se llena en la primera consulta.*

2. **Capa Eloquent y Criptografía:**
   * En el modelo `Consulta`, aplicar `EncryptedFieldCast` a las cuatro nuevas columnas.
   * **Atención técnica:** Dado que `etiquetas_motivo` y `evaluacion_inicial` son arreglos/objetos, el flujo de mutación debe ser: Array PHP -> `json_encode` -> Cifrado Libsodium -> BD. Al recuperar: BD -> Descifrado Libsodium -> `json_decode` -> Array PHP. Asegura que `EncryptedFieldCast` soporte o se adapte a esta doble transformación (serialización + cifrado) sin fallar.

3. **Lógica de Negocio y Validación (Backend):**
   * Modificar `ConsultaStoreRequest`. 
   * Inyectar lógica condicional (`$this->route('expediente')->consultas()->exists()`). Si devuelve `false` (es la primera cita), las reglas de validación para el array `evaluacion_inicial` (apariencia, voz, plan, pronóstico, etc.) pasan a ser obligatorias (`required`). Si es `true`, deben ignorarse o permitirse nulas.
   * Restringir el array de `etiquetas_motivo` a un diccionario estricto en el backend (Violencia Familiar, Violencia Docente, Violencia Pareja, Ideas Suicidas, Duelo, Problemas Académicos, Dificultades Socioeconómicas, Problemas de Adaptación, Conflictos Interpersonales). Rechazar peticiones con etiquetas no reconocidas.

4. **Interfaz de Usuario (Frontend / Vue 3):**
   * Refactorizar `resources/js/Pages/Consultas/Create.vue`.
   * Implementar un componente de selección múltiple tipo "Pills/Badges" interactivo para `etiquetas_motivo`.
   * Para no abrumar la carga cognitiva (Estándar Nord), la "Evaluación Inicial" no debe ser un muro de texto. Implementa un componente `Accordion` o `Tabs` que agrupe: 1. Observaciones Físicas y Conductuales, 2. Impresión y Diagnóstico, 3. Plan y Pronóstico.
   * Mostrar este bloque de "Evaluación Inicial" **únicamente** si una prop inyectada desde el controlador indica que `esPrimeraConsulta === true`.

---

## Fases de Ejecución Obligatorias

Debes responder a este prompt ejecutando estrictamente las fases en orden. **Detente después de la Fase 3 y pide autorización para comenzar a codificar.**

### FASE 1 — Verificación con evidencia:
- Confirma que las historias previas (US-06b a US-14) están implementadas y con tests EN VERDE HOY. Ejecuta la suite completa (`vendor/bin/pest`) y pega la salida en tu respuesta.
- Cita el código real (archivo + líneas) del modelo `Consulta` y `Expediente` actuales, especialmente verificando el funcionamiento del `EncryptedFieldCast` y el `AreaScope`.

### FASE 2 — Auditoría de riesgo:
- Confirma que utilizarás `EncryptedFieldCast` inyectado con el `AREA_KEY_SECRET` para persistir los nuevos datos sensibles de esta historia.
- Declara explícitamente cómo evitarás fugas de memoria o bypass del `AreaScope` al consultar estos nuevos campos en el historial.
- Confirma que existirá una única fuente de verdad para la validación de si es la "primera consulta" y los roles.

### FASE 3 — Plan y diseño (Requiere aprobación del usuario antes de codear):
- Presenta tu plan de arquitectura detallado paso a paso (Migraciones, adaptaciones a Casts, Request, Controller, Vista).
- Justifica cómo integrarás los nuevos campos extensos en `Consultas/Create.vue` usando el ecosistema Vue 3 y Tailwind v4 (paleta Nord) sin saturar visualmente al usuario.
- Plantea cualquier ambigüedad u obstáculo técnico como una pregunta directa al usuario.

**(ESPERA APROBACIÓN AQUÍ ANTES DE ESCRIBIR CÓDIGO DE PRODUCCIÓN)**

### FASE 4 — Tests (Regla estricta anti-trampas):
- Si un test falla: por defecto se corrige el CÓDIGO, no el test.
- Solo se modifica un test si se demuestra que el test original está mal escrito (con evidencia y cita del código). Todo cambio a un test existente debe declarar: qué decía antes, por qué estaba mal, y confirmación de que no se pierde cobertura de ningún escenario Gherkin.
- Usa Pest + `expect()` + AAA. 
- Debes crear un test de integridad criptográfica: Inserta una consulta usando la API, recupera el modelo directamente usando DB facade (`DB::table('consultas')->first()`), y verifica con `expect()->not->toContain()` que los textos planos de las etiquetas o la evaluación no existen en la base de datos cruda.
- Corre la suite COMPLETA antes de reportar terminado.

### FASE 5 — Seed antes de presentar resultados:
- Actualiza el `ExpedienteSeeder` (o equivalente) para que existan datos de prueba acumulativos con estas nuevas características (ej: pacientes con primera cita donde se llenó la matriz, pacientes en su segunda cita, uso de etiquetas).
- Indica en tu reporte final qué usuarios y contraseñas de prueba usar para probar esto manualmente en el navegador de forma inmediata.

### FASE 6 — Control de versiones:
- Asegúrate de trabajar en una rama nueva desde `dev` y prepara los cambios para un PR. Nunca hagas commits directos a dev/main.

### COMPLIANCE:
- En tu reporte final, declara cómo esta historia cumple con los controles de OWASP ASVS V6 (Criptografía) al encriptar las etiquetas de búsqueda y los JSON, y V4 (Control de Acceso) en las Policies.