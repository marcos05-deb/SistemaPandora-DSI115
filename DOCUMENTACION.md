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

### [2026-05-31] Implementación de Dashboard de Administrador y Gestión de Usuarios (UI/UX)
- **Agente:** Antigravity (IA)
- **Contexto:** Se requiere implementar un dashboard específico para el administrador (`sysadmin`) que permita visualizar y crear usuarios del sistema, adoptando la misma estética "Oracle" del login y asegurando una buena UI/UX.
- **Cambios realizados:**
  - **Base de Datos:** Se creó la migración `2026_05_31_154920_add_name_to_users_table.php` para añadir la columna `name` a la tabla `users` permitiendo una mejor identificación visual en la interfaz. Se actualizó `Especialista` y `DatabaseSeeder`.
  - **Enrutamiento y Middlewares:** Se creó `EnsureIsSysadmin` para proteger el grupo de rutas `/admin/*`. Se modificó `routes/web.php` separando el dashboard clínico (`/dashboard`) del administrativo (`/admin/dashboard`). Se actualizó `LoginController` para redirigir según el rol.
  - **Controladores:** Se crearon `Admin\DashboardController` (para la vista principal del dashboard) y `Admin\UserController` (para manejar el CRUD de usuarios, generación de `kdf_salt`, hash Argon2id, asignación de roles y creación del perfil en `profesionales`).
  - **Frontend (UI/UX):** Se diseñó `AdminLayout.vue` con la estética corporativa (fondo beige, tarjetas blancas redondeadas). Se implementaron las vistas `Admin/Dashboard.vue` y `Admin/Users/Index.vue` con una tabla limpia de usuarios y un formulario dinámico que solicita los datos necesarios según el rol seleccionado.
  - **Pruebas:** Se ejecutó `php artisan test` validando que la integración de estos cambios no afectara la autenticación base, el cierre de sesión ni las reglas estrictas de RBAC (HU-01, HU-02, HU-03).

### [2026-05-31] Implementación de Edición de Personal (Full CRUD)
- **Agente:** Antigravity (IA)
- **Contexto:** Se requiere completar el ciclo CRUD para la gestión de usuarios, permitiendo al administrador editar detalles y reasignar roles, manteniendo la restricción estricta de no alterar contraseñas para preservar la criptografía de los historiales clínicos. Además, se solicitó invertir el orden visual de Área y Especialidad en la tabla.
- **Cambios realizados:**
  - **Lógica de Edición:** Se implementó el método `update` en `UserController` que permite sincronizar los datos básicos, los roles y manejar la lógica condicional para el perfil profesional (crear, actualizar o eliminar la relación en `profesionales` dependiendo de si el rol seleccionado es clínico o `sysadmin`).
  - **Ajustes de UI (Frontend):** En `Index.vue` se agregó un estado dinámico que transforma el formulario de creación en uno de edición al pulsar el botón "Editar". Se ocultó el botón "Eliminar" para el propio usuario en sesión.
  - **Bloqueo de Contraseña:** Se desactivó el campo de contraseña en el modo edición, mostrando un mensaje de advertencia fundamentado en la Deuda Técnica del Sprint 3 (HU-05) para prevenir la corrupción de la clave derivada `_sym_key`.
  - **Diseño de Tabla:** Se ajustó la renderización de la tabla para mostrar el "Área" como texto principal oscuro, y la "Especialidad" como un subtexto gris, facilitando la identificación visual de departamentos.

### [2026-05-31] Refactorización UX/UI del Dashboard Administrativo
- **Agente:** Antigravity (IA)
- **Contexto:** Las críticas de usabilidad señalaron que la estética con textura, los formularios en línea y la falta de paginación restaban eficiencia y seriedad al sistema. Se solicitó rediseñar la vista para soportar mejor la carga cognitiva y escalar con grandes volúmenes de usuarios.
- **Cambios realizados:**
  - **Limpieza Estética:** Se eliminó el fondo texturizado y se aplicó un color gris institucional sólido (`#F4F6F8`) en `AdminLayout.vue`.
  - **Paginación y Filtros:** Se modificó `UserController.php@index` para usar paginación en lugar de colección entera y se implementó lógica de filtrado (`search` y `roleFilter`). En el frontend se añadió una barra de búsqueda y selectores interactivos.
  - **Arquitectura Modal:** El formulario de Creación/Edición se migró a un componente flotante (`Modal.vue`), evitando alterar el flujo visual de la tabla principal. Se reemplazaron placeholders por etiquetas de accesibilidad (`<label>`).
  - **Contraseñas Seguras Automatizadas:** Se eliminó el campo de captura manual de contraseñas. El backend ahora autogenera una cadena segura temporal (`Str::random(16)`) y la retorna en la sesión para ser mostrada una sola vez al administrador de sistema tras la creación de un perfil.
  - **Mejoras Visuales (Zebra-Striping y Badges):** Se aplicó `even:bg-gray-50` a las tablas y se cambió el indicador del administrador principal por un componente visual destacado (`StatusBadge`).

### [2026-05-31] Extracción de Formulario a Página Dedicada
- **Agente:** Antigravity (IA)
- **Contexto:** Se solicitó cambiar el formulario de creación/edición de un Modal flotante a una página dedicada para reducir la carga cognitiva visual. Además, se pidió ajustar los inputs de búsqueda para que empataran con la estética limpia del resto del sistema.
- **Cambios realizados:**
  - **Nuevas Rutas y Vista:** Se añadieron rutas de creación y edición (`create` y `edit`) y se extrajo el formulario al nuevo componente de página completa `Form.vue`.
  - **Ajustes Estéticos de Búsqueda:** Se removieron los bordes cuadrados (`border`) de la barra de búsqueda y selector de rol en `Index.vue`, reemplazándolos por líneas inferiores sutiles (`border-b`) y fondos transparentes para asimilar la estética original "Oracle".

### [2026-05-31] Candado de Seguridad: Cambio de Contraseña Forzado
- **Agente:** Antigravity (IA)
- **Contexto:** Las contraseñas temporales generadas automáticamente deben operar como OTPs (One-Time Passwords). Al usarla por primera vez, el sistema debe exigir la configuración de una contraseña definitiva para proteger el acceso.
- **Cambios realizados:**
  - **Estructura DB:** Se introdujo la bandera booleana `must_change_password` en la tabla `users` (activa por defecto). El seeder del sysadmin la desactiva por defecto para la cuenta raíz.
  - **Middleware (`RequirePasswordChange`):** Intercepta todas las rutas bajo el grupo `auth` y si la bandera está activa, fuerza la redirección al flujo de configuración de contraseña.
  - **Lógica Criptográfica (`PasswordSetupController`):** Al establecer la nueva clave, el controlador regenera el `kdf_salt` en la base de datos y **re-deriva la clave maestra `_sym_key`** de la sesión utilizando el nuevo hash, garantizando que el usuario esté habilitado inmediatamente para usar el sistema sin corromper la criptografía.

### [2026-06-01] Aseguramiento de Reglas de Negocio (RBAC Estricto)
- **Agente:** Antigravity (IA)
- **Contexto:** Se detectó una falla en la lógica de negocio donde el sistema permitía registrar múltiples especialistas con el rol de `area_coordinator` dentro de una misma Área Clínica.
- **Cambios realizados:**
  - Se inyectó una **Closure de validación** (regla personalizada) en los métodos `store` y `update` de `UserController.php` para el campo `area_id`.
  - Esta regla detecta si se está intentando asignar el rol de coordinador y verifica, vía Eloquent (`whereHas('especialista.roles')`), si ya existe un perfil activo con ese cargo en el departamento clínico especificado.
  - En modo edición, la consulta inteligentemente ignora al propio usuario (`where('user_id', '!=', $user->id)`) para evitar falsos positivos.

### [2026-06-01] Refactorización UI/UX - Sistema de Diseño "Nord"
- **Agente:** Antigravity (IA)
- **Contexto:** Implementación de un sistema de diseño institucional, austero y eficiente basado en la paleta semántica "Nord", mejorando drásticamente la jerarquía visual y la ergonomía del módulo de administración. Adicionalmente se requirió adaptar el esquema de base de datos para soportar nuevos campos requeridos en el diseño.
- **Cambios realizados:**
  - **Base de Datos:** Se creó la migración `add_phone_and_is_active_to_users_table.php` para inyectar `phone` (string) y `is_active` (boolean) a la tabla `users` preservando los datos existentes. Se actualizó el modelo `Especialista` y el array `$fillable`.
  - **CSS Nativo:** Se declararon los tokens oficiales en `resources/css/app.css` (`--nord0`, `--frost4`, `--aurora-red`, etc.) y se erradicó el uso de colores arbitrarios.
  - **Layout Principal:** `AdminLayout.vue` fue adaptado a la estética Nord, con una cabecera rígida de 44px en color `--nord0`, una barra lateral con identificadores `--frost2` para la página activa, y un fondo de confort visual en `--nord6`.
  - **Gestión de Usuarios (Index):**
    - Se incluyó un bloque analítico de tres tarjetas que exponen métricas procesadas desde el backend (Total, Activos, Ratio Coordinadores/Áreas).
    - La tabla fue rediseñada usando encapsulamiento estricto ("pill badges") para roles, incluyendo sub-estados condicionales ("Activo/Inactivo") bajo el nombre de los especialistas.
    - Se implementaron Estados Vacíos (Empty States) ilustrados y guiados para cuando no hay información o las búsquedas fallan.
  - **Formulario (Form.vue):**
    - Se reestructuró la arquitectura de información en un modelo balanceado de 2 columnas (Info Básica y Roles/Perfil).
    - Se mejoró la ergonomía marcando los campos requeridos con asteriscos de color `--aurora-red` y leyendas explícitas.
    - El bloque de notificación criptográfica fue transformado en una discreta alerta estilizada con `--frost3`.

### [2026-06-01] Refinamientos Finales de Traducción y Datos Base
- **Agente:** Antigravity (IA)
- **Contexto:** Ajustes finales sobre el flujo de seguridad, correcciones de idioma, modificaciones del seeder y refactorización del cálculo de métricas en el panel.
- **Cambios realizados:**
  - **Traducción de Validación:** En el `PasswordSetupController`, se inyectó un diccionario personalizado en la función `validate()` asegurando que todas las violaciones a la complejidad de contraseña (mix de casos, números, longitud, símbolos) se presenten estrictamente en español.
  - **Métricas:** Se refactorizó la tarjeta "Administradores" por "Coordinadores de Área", inyectando la métrica como un ratio dinámico (`$coordinadores / $totalAreas`).
  - **Actualización de Seeder:** Se incorporó "Trabajo Social" al catálogo oficial del `AreaSeeder.php` para integrarla operativamente dentro del nuevo flujo.

### [2026-06-01] Implementación de HU-04 y HU-05 (Cifrado de Datos y Código de Privacidad)
- **Agente:** Antigravity (IA)
- **Contexto:** Se requiere implementar el cifrado de datos de registros clínicos en reposo utilizando `sodium_crypto_secretbox` (XSalsa20-Poly1305) y asignar un código único anonimizado para pacientes, manteniendo compatibilidad estricta con el esquema final de base de datos y la normativa HIPAA / ISO 27001.
- **Cambios realizados:**
  - **Decisión de Negocio (HU-04):** A solicitud expresa, no se implementó un código PND externo. El identificador anónimo de búsqueda e integración será nativamente el UUID autogenerado (`codigo`) de la tabla `pacientes`, garantizando entropía y unicidad sin alterar el esquema existente ni crear índices ciegos (Blind Index) que no son necesarios para UUIDs.
  - **Migración Incremental:** Se creó `2026_05_31_202108_modify_columns_for_encryption_in_patients_tables.php` para cambiar los tipos de columnas que debían cifrarse a `TEXT`. Esto afectó `fecha_nacimiento` en `pacientes`, y `telefono_personal` y `telefono_casa` en `contactos_paciente`, previniendo errores de longitud (Base64) y de tipo estricto (`DATE`) en PostgreSQL.
  - **Servicios Criptográficos:**
    - `DecryptionException.php`: Excepción para fallos de integridad o falta de sesión.
    - `EncryptionContextService.php`: Abstracción inyectable para acceder a la `_sym_key` almacenada en la sesión y mantener el código testeable y seguro.
  - **Casts de Eloquent:** Se implementó `EncryptedFieldCast` que cifra y descifra automáticamente atributos al vuelo usando la clave derivada de la contraseña del especialista autenticado.
  - **Modelado:** Se crearon los modelos faltantes `Paciente` y `ContactoPaciente` y se modificó `Expediente` integrando sus respectivas relaciones, aplicando `EncryptedFieldCast` a todos sus campos que contienen Información Protegida de Salud (PHI) (ej: `motivo_consulta`, `diagnostico`, `notas_clinicas`, `nombre_completo`).
  - **Manejo de Excepciones:** Se confirmó que `bootstrap/app.php` maneje silenciosamente la `DecryptionException`, expulsando al usuario con un `HTTP 401` o redirigiéndolo al Login ante cualquier acceso anómalo.
  - **Pruebas:** Se creó `EncryptionTest.php` validando la consistencia e integridad del cifrado y decodificación. Las pruebas pasaron exitosamente.

### [2026-06-01] Adición de Rol: Referente Psicosocial
- **Agente:** Antigravity (IA)
- **Contexto:** A solicitud del usuario, se requirió agregar un nuevo rol intermedio dentro del sistema de Control de Acceso Basado en Roles (RBAC).
- **Cambios realizados:**
  - Se modificó `database/seeders/RoleSeeder.php` agregando el rol "Referente Psicosocial" con el slug `psychosocial_referent` y nivel `20` (intermedio entre Especialista y Coordinador).
### [2026-06-01] Refactorización de Dashboard Clínico y Restricción de Roles
- **Agente:** Antigravity (IA)
- **Contexto:** Se solicitó estandarizar el dashboard de los especialistas clínicos utilizando la estética del Sistema de Diseño Nord (previamente aplicada al panel admin) y añadir una restricción en la que únicamente el `Referente Psicosocial` pueda registrar pacientes.
- **Cambios realizados:**
  - **Inyección Global de Roles:** Se modificó `app/Http/Middleware/HandleInertiaRequests.php` para incluir en las variables globales de sesión (`page.props.auth.user.roles`) los identificadores de rol del usuario, permitiendo el control de acceso desde el Frontend (Vue/Inertia).
  - **Nuevo Layout:** Se creó `resources/js/Layouts/ClinicalLayout.vue` heredando todas las directrices UI/UX, tokens CSS (colores `--nord`) y estructura general de `AdminLayout.vue` pero con un contexto de navegación clínico.
  - **Rediseño de Dashboard:** Se reemplazó completamente el componente `resources/js/Pages/Dashboard.vue` por una interfaz limpia con tarjetas métricas ("Total Pacientes" y "Expedientes Activos") y una tabla responsiva de "Pacientes Recientes".
  - **Control de UI por Roles:** Se encapsuló el botón "Registrar Paciente" dentro de un condicional reactivo (`v-if="canCreatePatient"`) que valida si el usuario cuenta con el rol `psychosocial_referent`.
  - **Lógica Backend:** Se actualizó la clausura del endpoint `/dashboard` en `routes/web.php` para retornar los datos reales utilizando Eloquent (`Paciente::with('expedientes')` y `Paciente::count()`).

### [2026-06-01] Automatización de Perfiles Clínicos en Seeder
- **Agente:** Antigravity (IA)
- **Contexto:** Se solicitó poblar la base de datos automáticamente con usuarios de prueba para todos los roles clínicos, evitando la necesidad de crearlos manualmente desde el panel de administrador.
- **Cambios realizados:**
  - Se modificó `database/seeders/DatabaseSeeder.php` incorporando la creación de tres perfiles de prueba fijos: Coordinador de Área, Referente Psicosocial y Especialista.
  - Se implementó la instanciación de `KeyDerivationService` dentro del seeder para asegurar que los usuarios de prueba cuenten con un `kdf_salt` válido, permitiendo que la derivación de contraseñas y el cifrado XSalsa20-Poly1305 funcionen correctamente durante sus sesiones.
  - Se automatizó la creación de los registros obligatorios en la tabla `profesionales` vinculándolos al `Área General` con sus respectivas especialidades.

### [2026-06-01] Implementación del Flujo de Registro de Pacientes
- **Agente:** Antigravity (IA)
- **Contexto:** Se solicitó desarrollar la vista y la lógica subyacente para permitir el registro de nuevos pacientes en el sistema, asegurando que la estética "Nord" se mantenga y cumpliendo las restricciones de acceso establecidas en la HU-03/HU-05.
- **Cambios realizados:**
  - **Lógica de Controlador y Enrutamiento:** Se creó `PacienteController.php` con métodos `create` y `store`. Se inyectó una verificación dura (`abort(403)`) para bloquear cualquier solicitud POST o GET proveniente de usuarios que no posean el rol `psychosocial_referent`.
  - **Transacción Atómica (BD):** Se implementó `DB::transaction` en el método `store` para insertar de forma segura los datos en la tabla `pacientes` y luego en `contactos_paciente`, previniendo datos huérfanos en caso de fallos. El `código` (UUID) se hereda automáticamente.
  - **Cifrado Transparente:** Al usar los modelos de Eloquent configurados previamente con `EncryptedFieldCast`, toda la Información de Salud Protegida (PHI) como la fecha de nacimiento o el nombre del contacto de emergencia viajan y se almacenan automáticamente cifrados.
  - **Frontend / UX:** Se diseñó `resources/js/Pages/Pacientes/Create.vue` como un formulario a dos columnas integrado a `ClinicalLayout.vue`. Cuenta con selectores dinámicos para Facultades/Carreras y maneja reactividad de errores nativamente con `useForm` de Inertia.js.

### [2026-06-01] Segmentación Estricta de Datos en el Dashboard Clínico (RBAC)
- **Agente:** Antigravity (IA)
- **Contexto:** Aplicación del Control de Acceso Basado en Roles (RBAC) directamente sobre las consultas que pueblan el Dashboard de los especialistas, asegurando la privacidad de la información incluso en listas de pacientes "recientes".
- **Cambios realizados:**
  - **Lógica de Filtro por Creador:** Se modificó la clausura de la ruta `/dashboard` en `routes/web.php`. Ahora, cuando un `psychosocial_referent` ingresa, la consulta `Paciente::with('expedientes')` se filtra automáticamente por `creado_por_profesional_id`, limitándolo a ver estrictamente sus propios registros y bloqueando el acceso a pacientes creados por otros Referentes.
  - **Métricas Aisladas:** Las estadísticas de la parte superior del Dashboard (`$stats['total']` y `$stats['activos']`) ahora calculan valores locales en lugar de globales.
  - **Bloqueo a otros Especialistas:** Cualquier usuario con un rol diferente (Ej. `specialist` o `area_coordinator`) recibirá colecciones vacías (`[]` y `0`), ya que por regla de negocio actual, no tienen permitido visualizar ningún expediente a menos que el administrador se lo asigne explícitamente (Funcionalidad pendiente para el próximo sprint).
\n### Sprint 1 - Mejoras a HU-05 (Datos Ampliados y Multicontacto)\n* **Esquema de Base de Datos:** Se inyectaron `nombre_completo` y `direccion` (cifrados) a la tabla `pacientes`.\n* **Contactos Condicionales:** Se rediseñó el backend y frontend para soportar hasta 3 perfiles simultáneos: Padre, Madre y Responsable Legal. `PacienteController@store` implementa validación dinámica según la selección del responsable, obligando a proveer dirección y teléfono del responsable legal designado.\n* **Auditoría (Admin):** Se implementó `Admin\PacienteController@index` y la ruta `/admin/pacientes` que expone exclusivamente la tabla de códigos UUID y fechas de ingreso de los pacientes.\n* **RBAC Dashboard:** La ruta protegida del dashboard para el `psychosocial_referent` se modificó para enviar y renderizar en su tabla el `carnet` y el `nombre_completo` del paciente (dejando atrás el opaco `codigo`).\n
### [2026-06-01] Implementación de HU-06 (Búsqueda de Pacientes por Carnet)
- **Agente:** Antigravity (IA)
- **Contexto:** Se solicitó resolver el enlace roto en el menú de "Pacientes" y formalizar el cumplimiento de la HU-06 implementando un buscador. Esta funcionalidad permite localizar un expediente mediante un "Blind Index" no cifrado, preservando la arquitectura criptográfica.
- **Cambios realizados:**
  - **Lógica de Búsqueda:** Se agregó el método `index` en `PacienteController` para recibir peticiones GET con el parámetro `carnet`. Realiza una validación exacta (`where('carnet', ...)`), ya que el carnet no está cifrado en base de datos.
  - **Restricción RBAC:** Se aplicó validación cruzada garantizando que el expediente localizado pertenezca exclusivamente al profesional que lo creó (`creado_por_profesional_id`).
  - **Flujo Visual:** Si el carnet es correcto, el backend redirige inmediatamente al expediente (`pacientes.show`). De lo contrario, retorna un error de validación genérico protegiendo la existencia o no de registros ajenos.
  - **Interfaz Minimalista:** Se desarrolló `resources/js/Pages/Pacientes/Index.vue` con un buscador central estilizado y un diseño limpio alineado a la paleta Nord, priorizando el enfoque en la acción principal.

### [2026-06-01] Correcciones de Enrutamiento, UI y Ampliación de Búsqueda
- **Agente:** Antigravity (IA)
- **Contexto:** Durante las pruebas del flujo de búsqueda (HU-06) y la visualización de datos, se detectaron fallos en la resolución de rutas en Laravel, errores de Javascript al utilizar directivas Ziggy faltantes y comportamientos anómalos de renderizado CSS debido a incompatibilidades de Tailwind v4 con opacidades sobre variables CSS nativas.
- **Cambios realizados:**
  - **Fallo de Enrutamiento (404/Not Found):** Se corrigió un problema de discrepancia en `web.php` donde el *Implicit Route Model Binding* (`{paciente:carnet}`) intentaba resolver el modelo automáticamente e interfería con la lógica de *Blind Index* del controlador. Se cambió a un parámetro estándar (`{carnet}`) delegando el control manual y el chequeo RBAC a `PacienteController@show`.
  - **Fallo de Javascript en Búsqueda:** Se reemplazó el helper `route('pacientes.index')` por una ruta relativa (`/pacientes`) en `Pacientes/Index.vue` que estaba provocando un `ReferenceError` silenciado al presionar Enter o enviar el formulario.
  - **Arreglos de CSS (Tailwind v4):** Los modificadores `bg-opacity-*` sobre variables de color como `var(--aurora-orange)` y `var(--aurora-red)` renderizaban como colores sólidos 100% opacos, haciendo invisible el texto interno. Se eliminaron dichos fondos y se rediseñaron la etiqueta de "Responsable Principal" y el "Mensaje de Error" como bloques ahuecados usando solo contornos (`border`).
  - **Mejora de UX en Búsqueda:** Se implementó un evento reactivo (`@input`) en el buscador de pacientes que borra dinámicamente los errores (`form.clearErrors()`) devolviendo la interfaz a su estado limpio instantáneamente al teclear.
  - **Ampliación de Búsqueda Clínica:** Se removió el bloqueo estricto (403) que impedía a otros roles clínicos acceder a la vista de búsqueda `/pacientes`. El backend fue ajustado para forzar un resultado vacío temporal (`1 = 0`) para roles no psicosociales hasta que se implemente la asignación de expedientes en el Sprint 2. El botón de "Nuevo Paciente" fue restringido visualmente en la misma vista usando los atributos Reactivos de sesión (`canCreatePatient`).
  - **Búsqueda Administrativa en Tiempo Real (UUID):** Se implementó un buscador interactivo en la vista `Admin/Pacientes/Index.vue`. Utilizando `lodash/debounce` nativo (300ms) y llamadas `router.get` preservando el estado, el administrador puede escribir parcial o totalmente un código UUID y filtrar dinámicamente la tabla. El backend en `Admin/PacienteController.php` captura el parámetro y emplea una cláusula `ILIKE` sobre la columna castada a texto `codigo::text`.

### [2026-06-03] Correccion HU-06: Busqueda Segura para Especialista
- **Agente:** Antigravity (IA)
- **Contexto:** La HU-06 requeria que el rol Especialista pudiera buscar expedientes por codigo unico sin comprometer identidad. Se detecto que la funcionalidad no estaba implementada: el especialista estaba bloqueado (`whereRaw('1 = 0')`), no existia ruta `/busqueda-segura`, la vista `SecureSearch/Index.vue` era un mockup sin backend, y `PacienteController@show` rechazaba a todos los roles no-referentes con 403.
- **Cambios realizados:**
  - **Nuevo Controlador:** `app/Http/Controllers/SecureSearchController.php` con `index()` (renderiza pagina) y `search()` (busca por UUID exacto, aplica filtro de area via `whereHas('expedientes')` para specialist/coordinator, y `creado_por_profesional_id` para referentes).
  - **Nuevas Rutas:** `GET /busqueda-segura` y `POST /busqueda-segura` en `routes/web.php`.
  - **Correccion RBAC en PacienteController@show:** Se reemplazo el `abort(403)` generico por verificacion de area para specialist/coordinator (`$paciente->expedientes()->exists()` aprovechando el AreaScope global).
  - **Correccion RBAC en PacienteController@index:** Se reemplazo `whereRaw('1 = 0')` por `whereHas('expedientes')` para specialist/coordinator, permitiendoles buscar por carnet cuando tengan expedientes en su area.
  - **Frontend SecureSearch/Index.vue:** Reescribio con diseno Nord (alineado a Pacientes/Index.vue), input para UUID con validacion de formato, resultados anonimizados mostrando solo el codigo encontrado + botones "Ver Expediente" y "Historial de Citas".
  - **Navegacion:** Actualizado `MockupNavigation::specialist()` con rutas funcionales.
   - **Tests:** Creado `tests/Feature/HU06/SecureSearchTest.php` con 8 pruebas cubriendo busqueda por UUID, filtro cross-area, validacion de formato, y acceso a detalle de paciente.

### [2026-06-04] Documentación de Setup y Corrección de Configuración
- **Agente:** Antigravity (IA)
- **Contexto:** Un colaborador reportó error `Firebase\JWT\JWT not found` al clonar el repositorio e intentar hacer login. Se detectó que la documentación carecía de pasos de troubleshooting para este escenario y que `.env.example` estaba desincronizado con la configuración real de Docker.
- **Cambios realizados:**
  - **README.md — Troubleshooting JWT:** Se agregó entrada en "Resolución de Problemas Comunes" documentando el error `Firebase\JWT\JWT not found`, su causa (`vendor/` git-ignorado) y dos soluciones: con Docker (`docker compose down -v && docker compose up --build`) y sin Docker (`composer install --no-dev --optimize-autoloader && php artisan key:generate`).
  - **README.md — BLIND_INDEX_SECRET:** Se documentó el comando de generación de la clave de índice ciego, ausente tanto en el `entrypoint.sh` como en la documentación previa. Se agregaron instrucciones para Docker (`docker compose exec app php -r ...`) y para instalación local (`php -r "echo base64_encode(random_bytes(32));"`).
  - **.env.example — Sincronización con Docker:** Se corrigió `DB_HOST=127.0.0.1` → `DB_HOST=postgres` y `DB_PASSWORD=` → `DB_PASSWORD=pandora` para alinear el archivo de ejemplo con la configuración del contenedor Docker declarada en `docker-compose.yml:34-38` y con lo documentado en el historial [2026-05-31] "Resolución de Conflictos de Entorno".
- **Nota:** El `entrypoint.sh` no genera `BLIND_INDEX_SECRET`. Se recomienda en el futuro agregar esta generación al script de aprovisionamiento para automatizar completamente el setup.
