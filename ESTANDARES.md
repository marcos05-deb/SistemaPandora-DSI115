# ESTÁNDARES DE ARQUITECTURA Y CONVENCIONES DE CÓDIGO - PANDORA

## 1. Stack Tecnológico y Entorno

El desarrollo del Sistema Pandora debe realizarse estrictamente sobre las siguientes tecnologías y versiones.

*   **Backend:** PHP 8.4
*   **Framework:** Laravel 13 (v13.12.0)
*   **Base de Datos:** PostgreSQL 16 (con extensión `pgcrypto` y `uuid-ossp`)
*   **Frontend:** Vue 3.5.35
*   **Integración Frontend/Backend:** Inertia.js v3.3.0
*   **Empaquetador de Assets:** Vite 8.0.14
*   **Estilos:** Tailwind CSS v4
*   **Entorno de Desarrollo:** Docker Engine (Linux) / Docker Desktop (Windows/macOS) con Docker Compose v2+
*   **Dependencias Adicionales Obligatorias:** `laravel/sanctum` (^4.0), `owen-it/laravel-auditing` (^14.0), `pestphp/pest` (^4.7)

## 2. Arquitectura de Software y Patrones (Laravel)

*   **Controladores:** Se prohíbe terminantemente la lógica de negocio en los controladores ("Fat Controllers"). Los controladores deben manejar únicamente el flujo HTTP (recepción de la petición y retorno de vistas Inertia o respuestas JSON). Si la lógica interna supera las 5 líneas, se debe delegar obligatoriamente a clases de servicios o acciones.
*   **Validación:** Se fuerza el uso exclusivo de Form Requests para la validación de datos. Se prohíbe terminantemente la validación *inline* (uso de `$request->validate()`) dentro de los métodos del controlador.
*   **Autorización:** Se obliga el uso de Policies de Laravel vinculadas al sistema RBAC. Se prohíben las verificaciones de roles codificadas estáticamente (*hardcoded*) en las vistas de Vue o en las lógicas de los controladores. Toda evaluación de acceso debe transcurrir orgánicamente a través del modelo unificado (`$this->authorize()`).

## 3. Convenciones de Código y Nomenclatura

### Backend (PHP)
*   **Estándar y Tipado:** Se exige el cumplimiento estricto del estándar PSR-12. Todo archivo PHP debe incluir la directiva `declare(strict_types=1);` en la primera línea hábil. Es obligatorio el tipado de retorno explícito en todos los métodos.
*   **Nomenclatura:** Las clases (Modelos, Controladores, Servicios, etc.) deben escribirse en `PascalCase`. Las variables y métodos deben escribirse en `camelCase`. Las constantes deben usar obligatoriamente `SCREAMING_SNAKE_CASE`. Las Interfaces deben utilizar el sufijo `Contract` o adjetivos descriptivos (ej. `Authenticatable`).

### Base de Datos
*   **Migraciones y Tablas:** Las reglas para migraciones son inmutables. El nombre de las tablas debe estar obligatoriamente en `snake_case` plural (ej. `pacientes`, `expedientes`).
*   **Relaciones:** Las llaves foráneas deben llevar siempre el sufijo `_id`. Para el mapeo relacional con UUIDs, se debe usar explícitamente el método `$table->foreignUuid('columna_id')`.
*   **Transacciones:** Es obligatorio el uso de transacciones de base de datos (`DB::transaction`) para toda operación de escritura que involucre múltiples tablas, previniendo datos huérfanos.

### Frontend (Vue)
*   **API y Estructura:** Se fuerza el uso exclusivo de Composition API con la directiva `<script setup>`.
*   **Nomenclatura:** Se definen convenciones estrictas para el nombrado de componentes usando `PascalCase` (ej. `StatusBadge.vue`, `AdminLayout.vue`).
*   **Props y Eventos:** El manejo de propiedades y eventos debe realizarse de forma tipada mediante el uso de macros (`defineProps`, `defineEmits`).

## 4. Seguridad y Criptografía

*   **Gestión de Clave Compartida:** Para resolver el cifrado multi-especialista (Deuda C-09), el protocolo exacto exige que cada Área Clínica tenga una clave simétrica derivada del secreto de aplicación (`AREA_KEY_SECRET`). La clave del Especialista se usará únicamente para la autenticación; el cifrado y descifrado de los expedientes operará estrictamente con la clave de Área asignada.
*   **Cifrado de Datos Clínicos:** Todo campo con información de salud protegida (PHI) debe cifrarse en reposo de manera obligatoria utilizando el algoritmo XSalsa20-Poly1305, integrado mediante casteos de Eloquent y la librería libsodium nativa de PHP.
*   **Auditoría (Audit Log):** Existe la obligación técnica de registrar todo evento de escritura o lectura de expedientes. Para ello, se exige únicamente la implementación del Trait `\OwenIt\Auditing\Auditable` de la librería `owen-it/laravel-auditing` en los modelos correspondientes. Se prohíbe el uso de Observers adicionales para auditoría con el fin de evitar registros redundantes y cargas duplicadas.

## 5. UI/UX y Estandarización Visual

*   **Tema Nord y CSS:** La interfaz debe construirse rigurosamente implementando el sistema de diseño basado en el tema "Nord". La paleta Nord debe registrarse estrictamente dentro del bloque `@theme` del archivo CSS principal (ej. `app.css`), lo que permitirá a Vite generar las clases de utilidad automáticamente (ej. `bg-nord-0`). Se prohíbe el uso de variables CSS sueltas o arbitrarias fuera del motor CSS-first.
*   **Gestión de Estados Asíncronos:** El frontend debe estandarizar su respuesta en cualquier interacción con el servidor:
    *   **Loading:** Todo elemento interactivo debe presentar indicadores visuales de carga (ej. deshabilitar botones durante las peticiones).
    *   **Errores 422:** El manejo de errores de validación (HTTP 422) debe centralizarse en los formularios y presentarse bajo los inputs correspondientes de manera unificada usando `useForm` de Inertia.
    *   **Notificaciones:** Cualquier acción exitosa o crítica del sistema debe retroalimentarse al usuario a través de un manejo centralizado de notificaciones tipo *toast*.

### 5.1 Patrones de UI Existentes (Auditoría)

Tras una auditoría del código Vue, se establecen los siguientes patrones reales en uso dentro del repositorio:

*   **Layouts (`resources/js/Layouts/`):** El sistema divide la interfaz principal en áreas mediante layouts dedicados (ej. `AdminLayout.vue` y `ClinicalLayout.vue`). Las páginas se envuelven utilizando `defineOptions({ layout: ClinicalLayout });` al inicio de `<script setup>` (ej. `Pacientes/Index.vue`).
*   **Componentes Reutilizables (`resources/js/Components/UI/`):** Los elementos base residen en la carpeta `UI` (ej. `PrimaryButton.vue`, `StatusBadge.vue`, `Modal.vue`, `Breadcrumbs.vue`, `FieldTooltip.vue`). Su convención principal es el uso estricto de `defineProps` y estilos predefinidos (no utilitarios Tailwind arbitrarios).
*   **Estructura de Páginas (`resources/js/Pages/`):** Se organizan en directorios por módulo (ej. `Pacientes/`). Mantienen una convención CRUD de archivos: `Index.vue` para búsqueda/listados, `Create.vue` para formularios completos multi-paso, y `Show.vue` para detalle de registros.
*   **Formularios y Estados Asíncronos:**
    *   **Uso de `useForm`:** Todos los formularios de Inertia se manejan con esta utilidad (ej. `const form = useForm({...})` en `Pacientes/Create.vue`).
    *   **Errores 422:** Se muestran condicionalmente bajo cada input (ej. `v-if="form.errors.carnet"`) usando la clase `text-[var(--aurora-red)]` y un SVG de advertencia. El input modifica su borde a rojo simultáneamente.
    *   **Loading:** Se usa la propiedad `form.processing` para inhabilitar botones (`:disabled="form.processing"`) e intercambiar el ícono del botón por un SVG giratorio (`animate-spin`).
*   **Modales vs. Páginas Completas:** Formularios principales y flujos largos (ej. Registrar Paciente en `Create.vue`) se diseñan como **páginas completas** con navegación interna y `Breadcrumbs.vue`. Los componentes de tipo modal (`Modal.vue`) se reservan exclusivamente para la capa de presentación de información rápida o confirmaciones (evitando sobrecargar el DOM y facilitar navegación por URL).
*   **Paleta Nord y Clases CSS:** En lugar de usar clases Tailwind puras (ej. `bg-blue-500`), el sistema inyecta y utiliza intensivamente variables CSS personalizadas del tema Nord. Ejemplos reales: `bg-[var(--surface)]`, `text-[var(--nord3)]`, `border-[var(--aurora-red)]`. Además, destaca el uso constante de gradientes personalizados para elementos prominentes (ej. `background: linear-gradient(135deg, var(--frost4) 0%, var(--nord9) 100%);` en botones primarios).
*   **Iconografía:** No se emplea una librería externa tipográfica. Se utilizan cadenas crudas de íconos SVG inline en cada componente (similar a Heroicons integrados manualmente).

#### 5.1.1 Inconsistencias Detectadas
*   **Notificaciones Toast no abstraídas:** Aunque existe un componente `FlashBanner.vue` en `Components/UI/` para banners estáticos, la lógica asíncrona (barras de progreso y temporizadores) y el diseño visual de los "Toasts" flotantes de éxito/error están programados directamente dentro de la plantilla y lógica de `AdminLayout.vue` (líneas 85-117). Esto rompe el principio DRY, ya que debería ser un componente global independiente (ej. `ToastNotification.vue`).

## 6. Testing y Control de Calidad

*   **Pruebas de Regresión:** Se establece como estándar obligatorio que ninguna funcionalidad llegue a producción sin pruebas automatizadas. La redacción de *Feature Tests* (bajo Pest) es un requisito estricto y bloqueante para todo endpoint nuevo.
*   **Patrón de Pruebas:** Se exige la implementación rigurosa del patrón AAA (Arrange, Act, Assert) en la estructuración de todos los tests. Además, se exige el uso exclusivo de la sintaxis `expect()` (Expectation API) de Pest para las aserciones, prohibiendo tajantemente el uso de las aserciones estáticas heredadas de PHPUnit (como `$this->assertTrue()`).

## 7. Control de Versiones

*   **Historial de Cambios:** Se exige el uso estricto del formato *Conventional Commits* (`feat:`, `fix:`, `chore:`, etc.) para todo el historial de Git, asegurando trazabilidad semántica.
*   **Estrategia de Ramas:** Se fuerza el uso exclusivo de la estrategia **GitHub Flow**. Debe mantenerse una rama principal `main` que sea siempre desplegable. El desarrollo de funcionalidades se realizará mediante ramas de características efímeras que se fusionan a `main` únicamente a través de *Pull Requests*. Se prohíbe cualquier otra alternativa (como Git Flow o Trunk-Based simple) debido al tamaño del equipo y la velocidad de los Sprints.
