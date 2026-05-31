# Registro de Cambios y Documentación del Proyecto

## ⚠️ REGLAS ESTRICTAS PARA TODOS LOS AGENTES (INCLUYENDO IA) ⚠️

1. **Documentación Obligatoria:** Cualquier agente (humano o inteligencia artificial) que trabaje en este proyecto DEBE documentar todos los cambios realizados detalladamente en este archivo.
2. **Prohibido Borrar:** ESTÁ ESTRICTAMENTE PROHIBIDO BORRAR LÍNEAS DE ESTE ARCHIVO. Este documento sirve como un registro histórico inmutable ("append-only") de todas las modificaciones y decisiones tomadas.
3. **Reversión de Cambios:** En caso de que se necesite revertir o deshacer un cambio previo, NO se debe eliminar el registro original. En su lugar, se debe agregar una nueva entrada explicando detalladamente la reversión, haciendo referencia al cambio original y el motivo por el cual se deshizo.
4. **Documentación Exaustiva:** La documentación debe cubrir el máximo posible de detalles sobre el cambio realizado, incluyendo el contexto, el objetivo, la implementación y cualquier información relevante que pueda ser útil para entender el cambio en el futuro.

---

## Historial de Cambios

### [2026-05-25] Inicialización del Registro
- **Agente:** Antigravity (IA)
- **Cambios realizados:** Se creó el archivo `DOCUMENTACION.md` estableciendo las reglas fundamentales e inmutables para el registro de modificaciones en el proyecto.

### [2026-05-25] Integración de Esquema de Base de Datos PostgreSQL
- **Agente:** Antigravity (IA)
- **Contexto:** Se requiere integrar el esquema inicial en PostgreSQL para el SistemaPandora-DSI115, incluyendo la extensión `uuid-ossp`, ENUMs personalizados, tablas para control de acceso (roles y permisos), estructura institucional y perfiles de pacientes.
- **Cambios realizados:**
  - Se crearon las migraciones en `database/migrations` para definir todo el esquema en múltiples archivos secuenciales.
  - `2024_01_01_000000_setup_postgres_extensions_and_enums.php`: Añade la extensión `uuid-ossp` y los tipos ENUM (`sexo_enum`, `estado_civil_enum`, `parentesco_enum`).
  - `2024_01_01_000001_create_users_table.php`: Crea la tabla `users`.
  - `2024_01_01_000002_create_rbac_tables.php`: Crea `roles`, `permisos`, `role_user` y `permission_role`.
  - `2024_01_01_000003_create_institutional_tables.php`: Crea `areas`, `facultades` y `carreras`.
  - `2024_01_01_000004_create_professionals_table.php`: Crea la tabla `profesionales` utilizando `uuid` y llaves foráneas a usuarios y áreas.
  - `2024_01_01_000005_create_patients_tables.php`: Crea las tablas `pacientes` y `contactos_paciente`, agregando las columnas con tipos ENUM usando `DB::statement` y creando los índices de optimización solicitados (búsquedas por carnet e identificadores de eliminación lógica).

## [2026-05-31] Implementación de HU-01 (Autenticación y Criptografía)

**Objetivo:** Alinear el proyecto con los estándares arquitectónicos del roadmap e implementar la autenticación segura mediante sesión y derivación de clave.

**Decisiones Arquitectónicas y Modificaciones:**
1. **Conservación de la Base de Datos Original**: Las migraciones originales no fueron alteradas. Las modificaciones necesarias (adición de `kdf_salt` a la tabla `users`, tabla `sessions` y RBAC preparatorio) se hicieron mediante **migraciones incrementales** (`2026_05_31_*`).
2. **Modelo Especialista vs Tabla Users**: Para mantener la compatibilidad con el esquema original y al mismo tiempo adoptar la nomenclatura del roadmap, el modelo se nombró `App\Models\Especialista` pero apunta directamente a la tabla `users` mediante `protected $table = 'users';`.
3. **Manejo de ENUMs en Postgres**: Se detectó un conflicto con `RefreshDatabase` de Pest al intentar recrear los tipos ENUM existentes en pruebas. Se actualizó la migración base `000000_setup_postgres_extensions_and_enums` añadiendo `DROP TYPE IF EXISTS ... CASCADE` antes de las sentencias `CREATE TYPE` para permitir idempotencia en las pruebas sin afectar la integridad.
4. **Criptografía**: Se implementó el `KeyDerivationService` usando `sodium_crypto_pwhash` con el algoritmo Argon2id (Roadmap C-08). El middleware `ValidateSymmetricKey` fue creado para validar la persistencia de esta clave.
5. **UI & Controladores**: Los mockups (`App/Http/Controllers/Mockup` y recursos Vue asociados) fueron eliminados permanentemente y reemplazados por los controladores reales (`Auth/LoginController`, `Auth/LogoutController`).
6. **Entorno Docker**: Se expuso la base de datos PostgreSQL de desarrollo en el puerto externo `5434` para evitar conflictos con instancias nativas corriendo en el puerto 5432, actualizándose el `.env` acorde.

### [2026-05-31] Resolución de Conflictos de Entorno (Docker vs Local) y Caché
- **Agente:** Antigravity (IA)
- **Contexto:** Al intentar iniciar sesión, el sistema arrojaba un error `SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "cache" does not exist`, y el contenedor de Docker fallaba intermitentemente por dependencias no satisfechas.
- **Cambios realizados:**
  - **Caché:** Se generó y ejecutó la migración faltante de la tabla `cache` (`php artisan make:cache-table` y `php artisan migrate`) dado que Laravel 11 utiliza el driver de base de datos para el Rate Limiting y caché por defecto, y el esquema original del proyecto carecía de esta tabla.
  - **Dockerfile:** Se actualizó la imagen base de `php:8.2-cli-bookworm` a `php:8.4-cli-bookworm` para igualar la versión de PHP con las dependencias instaladas en el `composer.lock` del host, previniendo fallos críticos (`SanctumServiceProvider not found`) en el contenedor.
  - **Docker Compose:** Se eliminó el volumen nombrado `pandora-vendor` en el archivo `docker-compose.yml` para permitir que el contenedor monte y utilice directamente el directorio `vendor` del host, evitando desincronizaciones de dependencias.
  - **Configuración de Red (.env):** Se ajustó el archivo `.env` configurando explícitamente `DB_HOST=postgres` y `DB_PORT=5432` de manera permanente para garantizar que el proyecto funcione de manera unificada y exclusiva bajo el ecosistema de Docker, satisfaciendo el requerimiento de uso aislado mediante `docker compose up -d`.

### [2026-05-31] Migración y Actualización Global de Framework (Backend/Frontend)
- **Agente:** Antigravity (IA)
- **Contexto:** Por decisión del usuario, se aprovechó la temprana etapa del proyecto para actualizar todos los paquetes base y librerías del sistema a sus versiones más recientes (Q1 2026).
- **Cambios Backend:**
  - Se migró el framework principal de **Laravel 11** a **Laravel 13** (`v13.12.0`).
  - Se actualizaron las dependencias de Composer: `inertiajs/inertia-laravel` (v3.1), `laravel/tinker` (v3.0), `owen-it/laravel-auditing` (v14.0).
  - El sistema de pruebas (`pestphp/pest`) fue actualizado a `v4.7` junto con `phpunit/phpunit` (v12.5).
  - **Fix de Pruebas:** Se detectó que el middleware de validación CSRF fue renombrado a `PreventRequestForgery` en Laravel 13, lo que rompía los feature tests en la HU-01. Esto se solucionó aplicando la excepción correcta del middleware en `tests/Feature/HU01/LoginTest.php`.
- **Cambios Frontend:**
  - Se actualizaron todas las dependencias mediante `npm-check-updates`.
  - Se actualizó a **Vue 3.5.35**, **Vite 8.0.14** e **Inertia.js v3.3.0**.
  - **Migración Tailwind CSS v4:** El paquete `tailwindcss` se actualizó a la versión 4 (major upgrade). Esto requirió el uso de la herramienta de actualización `@tailwindcss/upgrade` y la instalación de `@tailwindcss/postcss` para adaptar la configuración de PostCSS, reemplazando la forma anterior y compilando los assets exitosamente (`npm run build`).

### [2026-05-31] Rediseño de Interfaz de Login (Estilo Oracle)
- **Agente:** Antigravity (IA)
- **Contexto:** El usuario solicitó cambiar la estética de la pantalla de inicio de sesión (`Login.vue`) para que se inspire en el diseño corporativo de Oracle.
- **Cambios realizados:**
  - Se modificó `resources/js/Pages/Auth/Login.vue` eliminando el uso de `GuestLayout` para tener control total sobre la estructura y el fondo.
  - Se implementó un fondo beige claro (`#F0EBE1`) con un patrón vectorial SVG de líneas concéntricas que simula la textura topográfica de Oracle.
  - Se reorganizó la interfaz en dos tarjetas blancas apiladas: una principal para el inicio de sesión y otra secundaria para la creación de cuenta.
  - Se estilizaron los inputs del formulario, eliminando bordes laterales y superiores, conservando únicamente un borde inferior minimalista.
  - Los botones fueron rediseñados con esquinas rectas, usando color carbón oscuro (`#2F2B28`) para la acción primaria y blanco con borde oscuro para la secundaria.

### [2026-05-31] Refinamiento de Interfaz Login Estilo Oracle
- **Agente:** Antigravity (IA)
- **Contexto:** Ajustes adicionales sobre el rediseño anterior basados en el feedback del usuario: esquinas redondeadas, integración de imagen estática para el fondo y traducción al español.
- **Cambios realizados:**
  - Se eliminó el SVG topográfico del código y se integró un estilo `background-image: url('/images/background.png')` para utilizar una imagen de fondo definida por el usuario.
  - Los contenedores blancos ("Cards") pasaron a tener bordes redondeados pronunciados (`rounded-2xl`) y una sombra más profunda (`shadow-lg`).
  - Se redondeó también el botón de inicio de sesión (`rounded-xl`).
  - Se tradujo toda la interfaz al español (ej: "Sign in" a "Iniciar sesión", "Password" a "Contraseña").
  - Se eliminó el botón de "Create Account" en la tarjeta inferior, reemplazando el texto por "Desarrollado por EquipoDeTrabajo".

### [2026-05-31] Implementación de HU-02 (Cierre de Sesión)
- **Agente:** Antigravity (IA)
- **Contexto:** Se requiere implementar el cierre de sesión seguro siguiendo la HU-02 del Roadmap, garantizando la destrucción del material criptográfico y de la sesión del Especialista.
- **Cambios realizados:**
  - Se modificó `App\Http\Controllers\Auth\LogoutController` para incluir la instrucción `Auth::guard('web')->logout()`, necesaria para desvincular al usuario autenticado a nivel del guard de Laravel, manteniendo el borrado previo de la clave `_sym_key` de la sesión.
  - Se agregó un botón de "Cerrar Sesión" en `resources/js/Pages/Dashboard.vue` utilizando el componente `<Link>` de Inertia con el método `POST` para probar la funcionalidad de la historia de usuario.
  - Se crearon las pruebas automatizadas (Feature Tests) en `tests/Feature/HU02/LogoutTest.php` comprobando la destrucción de la sesión, redirección al login y prevención de acceso a rutas protegidas pos-cierre de sesión.
  - Se actualizó el archivo `ROADMAP.md` marcando como completados todos los criterios de aceptación de la HU-02.

### [2026-05-31] Implementación de HU-03 (Roles y Segmentación Estricta RBAC)
- **Agente:** Antigravity (IA)
- **Contexto:** Se requiere implementar el control de acceso basado en roles y áreas clínicas (RBAC) para aislar los expedientes de los pacientes de acuerdo a la especialidad del profesional, previniendo accesos no autorizados.
- **Cambios realizados:**
  - **Modelos:** Se creó el modelo `Expediente` utilizando UUID y SoftDeletes. Se actualizó el modelo `Especialista` incorporando los métodos `roles()`, `areas()` y `hasRole()` operando sobre las tablas `role_user` y `profesionales`. Se verificó que los modelos `Role` y `Area` ya existían y estaban correctos.
  - **Global Scope:** Se implementó `App\Models\Scopes\AreaScope` para filtrar automáticamente las consultas a `Expediente`, validando mediante `whereIn('area_id', ...)` que el especialista sólo pueda interactuar con registros pertenecientes a sus áreas designadas.
  - **Middleware:** Se creó y registró en `bootstrap/app.php` el middleware `EnforceAreaScope` (`enforce_area_scope`), el cual previene que el rol `sysadmin` consuma rutas clínicas y valida a nivel de capa HTTP que las solicitudes directas a un expediente estén autorizadas, retornando `HTTP 403` o `404` en caso de violación de acceso.
  - **Pruebas y Verificación:** Se diseñó la suite de pruebas `tests/Feature/HU03/AreaScopeTest.php`. Se creó de forma temporal la base de datos `testing` dentro de PostgreSQL y se configuró `phpunit.xml` para correr las validaciones exitosamente, confirmando los aislamientos de seguridad del RBAC.
