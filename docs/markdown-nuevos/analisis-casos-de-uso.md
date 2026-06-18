# Análisis del Sistema PANDORA — Casos de Uso y Diagramas de Secuencia

> **Materia:** DSI115 — Ingeniería de Software  
> **Proyecto:** PANDORA — Sistema Integral de Gestión Clínica Universitaria  
> **Equipo:** EquipoDeTrabajo  
> **Fecha:** Junio 2026  

---

## Índice

1. [Actores del Sistema](#1-actores-del-sistema)
2. [Casos de Uso Narrados](#2-casos-de-uso-narrados)
3. [Relaciones entre Casos de Uso (extend / include)](#3-relaciones-entre-casos-de-uso-extend--include)
4. [Diagramas de Secuencia](#4-diagramas-de-secuencia)
5. [Matriz Actor vs Caso de Uso](#5-matriz-actor-vs-caso-de-uso)

---

## 1. Actores del Sistema

PANDORA define **cuatro (4) actores** con permisos estrictamente delimitados por el control de acceso basado en roles (RBAC). La separación es tal que ningún actor puede acceder a pantallas o datos fuera de su ámbito autorizado.

### Actor 1: Administrador de Sistema (`sysadmin`)

**Nivel de privilegio:** 100 (máximo)

**Propósito:** Gestionar la estructura organizativa del sistema: usuarios, áreas clínicas y roles. Es un rol puramente administrativo.

**Restricción crítica:** No puede ver datos clínicos. Por diseño tiene prohibido acceder a expedientes, diagnósticos o cualquier información de pacientes. El middleware `EnforceAreaScope` lo bloquea de rutas clínicas completamente.

**Pantallas que utiliza:** Dashboard Administrativo, Gestión de Usuarios (CRUD), Auditoría de Pacientes (solo UUIDs), Organigrama.

---

### Actor 2: Coordinador de Área (`area_coordinator`)

**Nivel de privilegio:** 50

**Propósito:** Supervisar todos los expedientes clínicos dentro de su área asignada (Psicología, Medicina General, Fisioterapia, Nutrición, Trabajo Social). Su acceso es de solo lectura.

**Restricción crítica:** Solo puede buscar y ver expedientes de pacientes que tengan archivos en su área. El `AreaScope` filtra automáticamente cualquier consulta. No puede crear ni editar pacientes. Solo puede existir un coordinador por área (regla de negocio).

**Pantallas que utiliza:** Dashboard Clínico, Búsqueda por Carnet, Ver Expediente, Búsqueda Segura por UUID.

---

### Actor 3: Referente Psicosocial (`psychosocial_referent`)

**Nivel de privilegio:** 20

**Propósito:** Es el punto de entrada de pacientes al sistema. Realiza la entrevista inicial, registra los datos demográficos y crea el primer expediente clínico. Es el único rol autorizado para registrar nuevos pacientes.

**Restricción crítica:** Solo ve los pacientes que él/ella misma registró (`creado_por_profesional_id`). No puede ver pacientes creados por otros Referentes Psicosociales. Es un rol intermedio entre Especialista y Coordinador.

**Pantallas que utiliza:** Dashboard Clínico (con métricas y pacientes reales), Registro de Paciente, Búsqueda por Carnet, Ver Expediente, Búsqueda Segura por UUID.

---

### Actor 4: Especialista (`specialist`)

**Nivel de privilegio:** 10 (mínimo)

**Propósito:** Atender pacientes dentro de su especialidad clínica (psicólogo, médico, fisioterapeuta, nutricionista, trabajador social). Realiza consultas, registra notas de evolución y diagnósticos en los expedientes.

**Restricción crítica:** Solo ve pacientes que tienen expedientes en su área clínica. Un psicólogo no puede ver expedientes de Medicina General, y viceversa. No puede registrar pacientes nuevos. Su dashboard muestra métricas vacías; solo accede a pacientes mediante búsqueda explícita.

**Pantallas que utiliza:** Dashboard Clínico, Búsqueda por Carnet, Ver Expediente, Búsqueda Segura por UUID.

---

## 2. Casos de Uso Narrados

A continuación se describen **once (11) casos de uso** del sistema, correspondientes a cada pantalla funcional implementada en el Sprint 1.

---

### CU-01: Autenticación Segura (KDF)

**Actor Principal:** Especialista, Coordinador de Área, Referente Psicosocial, Administrador de Sistema

**Precondiciones:** El usuario posee credenciales registradas (email y contraseña) y su cuenta está activa (`is_active = true`).

**Flujo Principal:**

1. El usuario accede a la ruta `/login`.
2. El sistema renderiza el formulario de inicio de sesión con campos de email y contraseña.
3. El usuario ingresa sus credenciales y pulsa "Iniciar Sesión".
4. El backend (`LoginController@store`) ejecuta las siguientes validaciones en cadena:
   - **Rate Limiting:** máximo 5 intentos por minuto (middleware `throttle:5,1`).
   - **Verificación de credenciales:** busca al usuario por email en la tabla `users` y verifica la contraseña con `Hash::check()` (Argon2id).
   - **Estado de la cuenta:** si `is_active = false`, el acceso es denegado.
   - **Bloqueo por fuerza bruta:** si `failed_login_attempts >= 3` y `locked_until > now()`, el acceso es denegado hasta que expire el bloqueo de 15 minutos.
5. Si la autenticación es exitosa:
   - Se resetea `failed_login_attempts = 0` y `locked_until = null`.
   - Se genera una **clave simétrica derivada** (`_sym_key`) mediante `KeyDerivationService` usando `sodium_crypto_pwhash` con el `kdf_salt` almacenado del usuario.
   - La `_sym_key` se almacena exclusivamente en la **sesión del servidor** (nunca en la base de datos ni en el navegador).
   - Se genera un token JWT firmado con la clave de la aplicación y se almacena en una cookie segura `pandora_token`.
   - Si el usuario tiene `must_change_password = true`, se crea la sesión pero se redirige inmediatamente a `/password/setup` (middleware `RequirePasswordChange`).
6. El sistema redirige al usuario según su rol:
   - `sysadmin` → `/admin/dashboard` (Dashboard Administrativo).
   - Roles clínicos → `/dashboard` (Dashboard Clínico).

**Flujo Alternativo:**
- Si las credenciales son incorrectas, se incrementa `failed_login_attempts`. Al llegar a 3 intentos fallidos, la cuenta se bloquea por 15 minutos (`locked_until = now() + 15 minutes`).
- Si el usuario está bloqueado, el sistema muestra: "Demasiados intentos fallidos. Intente de nuevo en 15 minutos."
- Si la cuenta está inactiva, el sistema retorna un error genérico sin revelar el estado de la cuenta.
- Si el usuario autenticado tiene `must_change_password = true`, es redirigido a la configuración de contraseña antes de acceder a cualquier otra ruta.

**Postcondiciones:** El usuario tiene una sesión activa con `_sym_key` disponible en memoria del servidor para operaciones de cifrado/descifrado. El token JWT está almacenado en cookie segura (HttpOnly, Secure, SameSite=Lax).

---

### CU-02: Ver Dashboard Clínico

**Actor Principal:** Referente Psicosocial, Coordinador de Área, Especialista

**Precondiciones:** El usuario ha iniciado sesión y posee un rol clínico. Si el usuario es sysadmin, es redirigido automáticamente al Dashboard Administrativo.

**Flujo Principal:**

1. El usuario accede a la ruta `/dashboard`.
2. El sistema verifica el rol del usuario.
3. Si el rol es **Referente Psicosocial**:
   - El sistema consulta los pacientes donde `creado_por_profesional_id` coincide con el profesional autenticado.
   - Calcula dos métricas: total de pacientes registrados y total de expedientes activos.
   - Muestra una tabla con los 10 pacientes más recientes, incluyendo nombre completo (descifrado) y carnet.
4. Si el rol es **Coordinador de Área** o **Especialista**:
   - El sistema muestra el dashboard con métricas en cero y lista de pacientes vacía.
   - Estos roles solo acceden a pacientes mediante búsqueda explícita (CU-09 o CU-11), ya que por regla de negocio no se les asigna automáticamente ningún expediente.
5. El usuario visualiza las métricas y la tabla de pacientes según su rol.

**Flujo Alternativo:** Si el usuario no tiene ningún rol clínico asignado, el sistema muestra el dashboard vacío sin errores.

**Postcondiciones:** El usuario ve su dashboard personalizado con datos segmentados por rol.

---

### CU-03: Ver Dashboard Administrativo

**Actor Principal:** Administrador de Sistema

**Precondiciones:** El usuario posee el rol `sysadmin` y ha iniciado sesión.

**Flujo Principal:**

1. El administrador accede a `/admin/dashboard`.
2. El middleware `EnsureIsSysadmin` verifica que el usuario tenga rol sysadmin (nivel 100). Si no lo tiene, retorna HTTP 403.
3. El sistema renderiza el panel de bienvenida con accesos directos a las secciones de gestión: Usuarios, Pacientes (auditoría) y Organigrama.

**Flujo Alternativo:** Si un usuario clínico intenta acceder a `/admin/*`, el middleware `sysadmin` bloquea la solicitud con HTTP 403.

**Postcondiciones:** El administrador visualiza el panel de control general del sistema.

---

### CU-04: Gestionar Usuarios

**Actor Principal:** Administrador de Sistema

**Precondiciones:** El administrador está autenticado como sysadmin.

**Flujo Principal:**

1. El administrador accede a `/admin/users`.
2. El sistema consulta todos los usuarios en la tabla `users` con sus relaciones: rol asignado, área clínica y especialidad.
3. Se renderiza una tabla paginada (15 registros por página) con las columnas: Nombre, Email, Rol, Área/Especialidad, Estado (Activo/Inactivo) y Acciones.
4. En la parte superior se muestran tres tarjetas métricas calculadas desde el backend:
   - Total de Usuarios registrados en el sistema.
   - Usuarios Activos (`is_active = true`).
   - Ratio de Coordinadores por Área (`coordinadores / total_areas`).
5. El administrador puede:
   - **Filtrar** por nombre o email usando la barra de búsqueda en tiempo real.
   - **Filtrar** por rol usando el selector desplegable.
   - **Eliminar** un usuario con soft-delete (`is_active = false`). No puede eliminarse a sí mismo.
6. Desde esta pantalla, el administrador puede navegar a **Crear Usuario** (CU-06) o **Editar Usuario** (CU-07).

**Flujo Alternativo:**
- Si no hay resultados para el filtro aplicado, se muestra un estado vacío con el mensaje "No se encontraron usuarios" y una ilustración.
- Si el administrador intenta eliminar su propio usuario, el botón está deshabilitado.

**Postcondiciones:** El administrador visualiza la lista completa de usuarios con sus métricas y puede gestionar el personal del sistema.

---

### CU-06: Crear Usuario

**Actor Principal:** Administrador de Sistema

**Relación:** Extiende a CU-04 (Gestionar Usuarios). Se activa cuando el administrador pulsa "Nuevo Usuario".

**Precondiciones:** El administrador se encuentra en la pantalla de Gestión de Usuarios (CU-04).

**Flujo Principal:**

1. El administrador pulsa el botón "Nuevo Usuario" en CU-04.
2. Navega a `/admin/users/create`. El sistema carga los catálogos de roles y áreas para los selectores del formulario.
3. Se muestra un formulario de dos columnas:
   - **Columna izquierda — Información Básica:** nombre completo, email, teléfono.
   - **Columna derecha — Roles y Perfil:** selector de rol. Si el rol seleccionado es clínico, se despliegan campos adicionales: selector de área y campo de especialidad.
4. El administrador completa los campos requeridos (marcados con asterisco rojo) y pulsa "Guardar Usuario".
5. El backend (`UserController@store`) ejecuta las siguientes validaciones:
   - **Regla de negocio:** si el rol es `area_coordinator`, verifica que no exista ya otro coordinador activo en la misma área clínica.
6. Dentro de una transacción atómica, el sistema:
   - Genera una contraseña aleatoria segura de 16 caracteres.
   - Aplica hash Argon2id a la contraseña.
   - Genera un `kdf_salt` único para la derivación de clave.
   - Crea el registro en `users` con `must_change_password = true`.
   - Asigna el rol en la tabla pivote `role_user`.
   - Si el rol es clínico, crea el perfil profesional en `profesionales` vinculando al área seleccionada.
7. El sistema envía un correo electrónico (`UserCreatedNotification`) con una **URL firmada temporal** (24 horas de validez) para que el nuevo usuario configure su contraseña. La contraseña **no se muestra en pantalla**.
8. El sistema redirige a CU-04 con la lista de usuarios actualizada.

**Flujo Alternativo:**
- Si la validación falla (campos vacíos, email duplicado, formato inválido), el sistema retorna al formulario con mensajes de error en español bajo cada campo.
- Si se intenta crear un segundo coordinador en la misma área, el sistema rechaza la solicitud con el mensaje: "Ya existe un coordinador asignado a esta área clínica."

**Postcondiciones:** Un nuevo usuario queda registrado en el sistema. Recibe un correo con un enlace temporal para configurar su contraseña. El usuario debe cambiar su contraseña en el primer acceso (forzado por el middleware `RequirePasswordChange`).

---

### CU-07: Editar Usuario

**Actor Principal:** Administrador de Sistema

**Relación:** Extiende a CU-04 (Gestionar Usuarios). Se activa cuando el administrador pulsa "Editar" en una fila.

**Precondiciones:** El administrador se encuentra en CU-04 con al menos un usuario listado.

**Flujo Principal:**

1. El administrador pulsa el botón "Editar" en la fila de un usuario.
2. Navega a `/admin/users/{id}/edit`. El sistema precarga: nombre, email, teléfono, rol actual, área y especialidad.
3. El campo de contraseña aparece **bloqueado** con la advertencia: "Por seguridad criptográfica, la contraseña no puede modificarse desde este panel. Para cambiar la contraseña se requiere un flujo especial (Sprint 3)."
4. El administrador modifica los campos necesarios y pulsa "Guardar Cambios".
5. El backend (`UserController@update`) ejecuta:
   - Actualización de datos básicos (nombre, email, teléfono, `is_active`).
   - Sincronización del rol: si el rol cambió de clínico a sysadmin (o viceversa), se crea o elimina el perfil profesional correspondiente.
   - **Regla de negocio:** si el nuevo rol es `area_coordinator`, verifica que no exista otro coordinador en la misma área (excluyendo al propio usuario de la verificación).
6. El sistema redirige a CU-04 con la lista actualizada.

**Flujo Alternativo:** Si se intenta asignar un coordinador a un área que ya tiene uno, el sistema rechaza la edición con un mensaje descriptivo.

**Postcondiciones:** Los datos del usuario quedan actualizados. La clave criptográfica del usuario no se altera, preservando su capacidad de descifrar expedientes existentes.

---

### CU-08: Auditar Pacientes

**Actor Principal:** Administrador de Sistema

**Precondiciones:** El administrador está autenticado como sysadmin.

**Flujo Principal:**

1. El administrador accede a `/admin/pacientes`.
2. El sistema consulta la tabla `pacientes` pero expone exclusivamente dos campos:
   - `codigo` (UUID anonimizado del paciente).
   - `created_at` (fecha de ingreso al sistema).
3. No se muestra ningún dato personal: ni nombre, ni carnet, ni dirección, ni contactos.
4. Se renderiza una tabla paginada con todos los pacientes del sistema.
5. El administrador puede buscar en tiempo real por UUID (búsqueda parcial con debounce de 300ms) usando `ILIKE` sobre `codigo::text` en PostgreSQL.
6. Esta pantalla existe únicamente con fines de auditoría administrativa. No permite ver detalles clínicos ni navegar a expedientes.

**Flujo Alternativo:** Si no hay pacientes registrados, se muestra un estado vacío.

**Postcondiciones:** El administrador visualiza una lista anonimizada de todos los pacientes del sistema, permitiendo trazabilidad administrativa sin comprometer la privacidad.

---

### CU-10: Ver Organigrama

**Actor Principal:** Administrador de Sistema

**Precondiciones:** El administrador está autenticado como sysadmin.

**Flujo Principal:**

1. El administrador accede a `/admin/organigrama`.
2. El sistema consulta todos los roles, áreas, y usuarios con sus relaciones (rol, área, especialidad).
3. La información se presenta agrupada jerárquicamente:
   - **Por Rol:** cantidad total de usuarios con cada rol.
   - **Por Área Clínica:** para cada área se lista el coordinador asignado, los especialistas y los referentes psicosociales.
4. El administrador obtiene una vista completa de la estructura organizativa del sistema.

**Postcondiciones:** El administrador visualiza la estructura jerárquica completa de roles, áreas y personal clínico.

---

### CU-05: Registrar Paciente

**Actor Principal:** Referente Psicosocial (exclusivo)

**Precondiciones:** El usuario posee el rol `psychosocial_referent`. Si no lo posee, el sistema retorna HTTP 403.

**Flujo Principal:**

1. El Referente Psicosocial, desde el Dashboard Clínico (CU-02), pulsa el botón "Registrar Paciente" o navega directamente a `/pacientes/create`.
2. El controlador verifica el rol; si no es `psychosocial_referent`, retorna `abort(403)`.
3. El sistema carga los catálogos de **Facultades** y **Carreras** para los selectores dinámicos (el selector de Carrera se filtra automáticamente según la Facultad seleccionada).
4. Se renderiza un formulario con las siguientes secciones:
   - **Datos del Paciente:** carnet (único, no cifrado, validado en el FormRequest), sexo, estado civil, nombre completo, dirección, fecha de nacimiento, profesión/ocupación, referido por, llevado por, fecha de primera consulta, motivo de consulta.
   - **Contactos (hasta 3):** Padre, Madre y Responsable Legal. Para cada uno: nombre completo, parentesco, teléfono personal, teléfono de casa, dirección. El referente marca cuál es el responsable principal.
5. El referente completa el formulario y pulsa "Guardar Paciente".
6. El backend (`PacienteController@store`) ejecuta dentro de una **transacción atómica** (`DB::transaction`):
   - Valida los campos mediante `StorePacienteRequest` (carnet único, formato, campos requeridos).
   - Genera un UUID automático como `codigo` del paciente (gestionado por PostgreSQL `uuid-ossp`).
   - Inserta el registro en `pacientes`. Los campos que contienen Información Protegida de Salud —nombre completo, dirección, fecha de nacimiento, profesión, referido por, llevado por, motivo de consulta— son **cifrados automáticamente** por el `EncryptedFieldCast` usando la clave simétrica `_sym_key` de la sesión del servidor (XSalsa20-Poly1305). El carnet se almacena sin cifrar para permitir búsquedas.
   - Inserta hasta 3 registros en `contactos_paciente` con sus datos también cifrados (nombres, teléfonos, direcciones).
7. Confirma la transacción. Si cualquier paso falla, todos los cambios se revierten (rollback).
8. El sistema redirige al Dashboard Clínico (CU-02), donde el nuevo paciente aparece en la tabla de "Pacientes Recientes".

**Flujo Alternativo:**
- Si la validación del formulario falla (campos requeridos vacíos, carnet duplicado, formato de fecha inválido), el sistema retorna a la misma pantalla con mensajes de error en español bajo cada campo.
- Si ocurre un error durante la transacción (ej. fallo de base de datos), se ejecuta rollback automático y se muestra un mensaje de error genérico.

**Postcondiciones:** Un nuevo paciente queda registrado con UUID único. Todos sus datos sensibles están cifrados en reposo (ilegibles incluso con acceso directo a PostgreSQL). Solo el Referente que lo creó puede verlo.

---

### CU-11: Buscar Paciente por Carnet

**Actor Principal:** Referente Psicosocial, Coordinador de Área, Especialista

**Precondiciones:** El usuario está autenticado con un rol clínico.

**Flujo Principal:**

1. El usuario navega a `/pacientes` desde el menú lateral.
2. El sistema muestra una pantalla centrada con un campo de búsqueda que acepta el formato de carnet estudiantil (ej. `CH17001`, `MG23045`).
3. El usuario ingresa el carnet y presiona Enter o pulsa el botón de búsqueda.
4. El backend recibe la petición GET con el parámetro `carnet`. Aplica **Rate Limiting** (30 solicitudes por minuto) para prevenir enumeración.
5. Ejecuta la búsqueda con reglas de visibilidad segmentadas por rol:
   - **Referente Psicosocial:** busca coincidencia exacta de carnet exclusivamente entre los pacientes donde `creado_por_profesional_id` coincide con el profesional autenticado.
   - **Coordinador de Área / Especialista:** busca coincidencia exacta de carnet solo entre pacientes que tienen expedientes en el área del usuario, mediante `whereHas('expedientes')`. El `AreaScope` global garantiza que la subconsulta solo considere expedientes del área autorizada.
6. Si encuentra el paciente, redirige a **Ver Expediente** (CU-12).
7. Si no lo encuentra, retorna un mensaje genérico: "No se encontró ningún paciente con ese carnet". Este mensaje es deliberadamente ambiguo: no revela si el paciente existe pero pertenece a otra área o fue creado por otro referente.

**Flujo Alternativo:**
- El usuario puede consultar su historial de búsquedas recientes, almacenado en `localStorage` del navegador con clave dinámica (`pandora_carnet_history_{user.id}`), garantizando privacidad entre usuarios que comparten equipo.
- Si el usuario ingresa un formato de carnet inválido, el sistema muestra un error de validación.

**Postcondiciones:** Si el paciente es encontrado y el usuario tiene permisos, es redirigido a CU-12. Si no, permanece en la pantalla de búsqueda.

---

### CU-09: Búsqueda Segura por UUID

**Actor Principal:** Coordinador de Área (principal), Referente Psicosocial, Especialista, Administrador de Sistema

**Precondiciones:** El usuario está autenticado. No se requiere un rol específico; cualquier rol autenticado puede acceder a esta pantalla.

**Flujo Principal:**

1. El usuario navega a `/busqueda-segura` desde el menú de navegación.
2. El sistema muestra una pantalla con un campo de búsqueda diseñado para aceptar un UUID completo en formato estándar: `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`.
3. El usuario ingresa un UUID de paciente y pulsa "Buscar".
4. El backend (`SecureSearchController@search`) recibe la petición POST y ejecuta:
   - **Validación de formato:** el valor ingresado debe coincidir con el patrón UUID v4.
   - **Búsqueda por coincidencia exacta** en la columna `codigo` de la tabla `pacientes`.
   - **Filtrado por RBAC:**
     - Referente Psicosocial: solo resultados donde `creado_por_profesional_id` coincide.
     - Coordinador/Especialista: solo resultados donde el paciente tiene expedientes en su área (`whereHas('expedientes')` con `AreaScope`).
     - Administrador de Sistema: sin restricción de área (puede buscar cualquier UUID, pero solo obtiene el código, no datos clínicos).
5. Si se encuentra el paciente, se muestra un resultado **anonimizado**:
   - Únicamente se despliega el código UUID encontrado.
   - Se ofrecen dos botones: "Ver Expediente" (redirige a CU-12) y "Historial de Citas" (funcionalidad futura).
6. Si el usuario pulsa "Ver Expediente", es redirigido a CU-12.
7. Si no se encuentra, se muestra el mensaje genérico: "No se encontró ningún paciente con ese código".

**Flujo Alternativo:**
- Si el formato del UUID no es válido, el sistema muestra un error de validación: "El código ingresado no tiene un formato válido".
- El usuario puede consultar su historial de UUIDs buscados recientemente (`pandora_uuid_history_{user.id}` en `localStorage`).

**Postcondiciones:** El usuario obtiene un resultado anonimizado (solo UUID) o es redirigido al expediente completo si tiene permisos y pulsa "Ver Expediente".

---

### CU-12: Ver Expediente del Paciente

**Actor Principal:** Referente Psicosocial, Coordinador de Área, Especialista

**Relación:** Es extendido por CU-11 (Buscar por Carnet) y CU-09 (Búsqueda Segura por UUID). Solo se accede a esta pantalla tras una búsqueda exitosa de paciente.

**Precondiciones:** El usuario ha encontrado un paciente mediante búsqueda por carnet o por UUID, y posee permisos de acceso sobre ese paciente.

**Flujo Principal:**

1. El usuario es redirigido a `/pacientes/{carnet}` (desde CU-11) o navega tras pulsar "Ver Expediente" en CU-09.
2. El backend (`PacienteController@show`) ejecuta las siguientes verificaciones de seguridad en cadena:
   - **Rate Limiting:** 30 solicitudes por minuto.
   - **Política de acceso** (`PacientePolicy::view()`):
     - Referente Psicosocial: verifica que `creado_por_profesional_id` coincida con el usuario autenticado.
     - Coordinador/Especialista: verifica que el paciente tenga al menos un expediente en el área del usuario. El `AreaScope` garantiza que solo se consideren expedientes del área autorizada.
   - Si la política deniega el acceso, se retorna HTTP 403 (o 404 para no revelar existencia del recurso).
3. Una vez autorizado, el sistema carga el paciente con sus relaciones:
   - `carrera` → `facultad` (datos institucionales).
   - `contactos` (hasta 3 registros de contacto).
4. Los campos cifrados en la base de datos son **descifrados en tiempo real** en el servidor:
   - `EncryptedFieldCast` obtiene la clave simétrica `_sym_key` de la sesión del servidor a través del `EncryptionContextService`.
   - Descifra cada campo PHI: nombre completo, dirección, fecha de nacimiento, profesión, referido por, llevado por, motivo de consulta, y todos los datos de contacto.
   - Si la clave de sesión no está disponible (sesión expirada o manipulada), se lanza `DecryptionException` y el usuario es redirigido al login.
5. Los datos descifrados se empaquetan mediante `PacienteResource`, que filtra explícitamente los campos a exponer (previniendo fugas de datos por hidratación masiva).
6. El sistema renderiza la vista con tres secciones de información:
   - **Datos Generales:** código UUID, carnet, facultad, carrera, sexo, estado civil, nombre completo, dirección, fecha de nacimiento, profesión/ocupación, fecha de primera consulta.
   - **Contactos:** tabla con nombre, parentesco, teléfono personal, teléfono de casa, dirección e indicador de responsable principal.
   - **Información de Admisión:** referido por, llevado por, motivo de consulta.

**Flujo Alternativo:**
- Si el paciente no tiene contactos registrados, la sección de Contactos aparece vacía.
- Si ocurre un error de descifrado (clave de sesión inválida), el sistema redirige al login con un mensaje de sesión expirada.

**Postcondiciones:** El usuario visualiza el expediente completo del paciente con todos los datos descifrados. Los datos nunca abandonan el servidor en texto plano sin pasar por el proceso de descifrado controlado por RBAC.

---

## 3. Relaciones entre Casos de Uso (extend / include)

### Definiciones

- **extend:** El caso de uso base puede ejecutarse sin el caso de uso extendido. El caso extendido agrega comportamiento opcional bajo una condición específica. La flecha en el diagrama va del caso extendido hacia el caso base.

- **include:** El caso de uso base siempre incluye obligatoriamente al caso incluido. La flecha va del caso base hacia el caso incluido.

En PANDORA, **todas las relaciones entre casos de uso son de tipo extend**. Cada pantalla funciona de forma independiente y ninguna requiere obligatoriamente de otra para cumplir su objetivo de negocio. Las operaciones que siempre ocurren (cifrar, descifrar, validar permisos) son funciones internas del sistema —no casos de uso visibles para el usuario— y por tanto no califican como relaciones include.

### Tabla de Relaciones

| # | Caso Base (extendido por) | Relación | Caso que Extiende | Condición de Extensión |
|---|---------------------------|----------|-------------------|------------------------|
| R1 | CU-04 Gestionar Usuarios | ← extend | CU-06 Crear Usuario | El administrador pulsa "Nuevo Usuario" |
| R2 | CU-04 Gestionar Usuarios | ← extend | CU-07 Editar Usuario | El administrador pulsa "Editar" en una fila |
| R3 | CU-12 Ver Expediente | ← extend | CU-11 Buscar por Carnet | La búsqueda encuentra un paciente |
| R4 | CU-12 Ver Expediente | ← extend | CU-09 Búsqueda Segura UUID | La búsqueda encuentra un paciente |

### Diagrama de Relaciones

```
┌─────────────────────────────────┐
│          CU-04                   │
│     Gestionar Usuarios           │
│  (Lista, filtros, métricas,      │
│   eliminación)                   │
└────────────────┬────────────────┘
                 │  extiende (admin pulsa botón)
                 │
      ┌──────────┴──────────┐
      ▼                     ▼
┌─────────────┐     ┌─────────────┐
│   CU-06     │     │   CU-07     │
│   Crear     │     │   Editar    │
│   Usuario   │     │   Usuario   │
└─────────────┘     └─────────────┘


┌─────────────────────────────────┐
│          CU-12                   │
│   Ver Expediente del Paciente    │
│  (Descifrado, RBAC, políticas)  │
└────────────────┬────────────────┘
                 │  extiende (paciente encontrado)
                 │
      ┌──────────┴──────────────┐
      ▼                         ▼
┌─────────────┐           ┌─────────────┐
│   CU-11     │           │   CU-09     │
│   Buscar    │           │   Búsqueda  │
│   por       │           │   Segura    │
│   Carnet    │           │   por UUID  │
└─────────────┘           └─────────────┘


Casos de Uso Independientes (sin relaciones extend/include):

  CU-02  Ver Dashboard Clínico
  CU-03  Ver Dashboard Administrativo
  CU-08  Auditar Pacientes
  CU-10  Ver Organigrama
  CU-05  Registrar Paciente
```

---

## 4. Diagramas de Secuencia

A continuación se presentan los diagramas de secuencia para los flujos más representativos del sistema. Se han seleccionado cuatro flujos que cubren las operaciones principales: registro de paciente (el más complejo, involucra cifrado), búsqueda por carnet con visualización de expediente (flujo clínico más frecuente), creación de usuario administrativo (flujo de gestión), y búsqueda segura anonimizada (flujo de privacidad).

### Participantes en los diagramas

| Participante | Descripción |
|--------------|-------------|
| **Actor** | El rol que inicia la acción (Referente Psicosocial, Coordinador, Especialista, Administrador) |
| **Vue Frontend** | Interfaz de usuario (Vue 3 + Inertia.js). Renderiza páginas y captura interacciones |
| **Controlador Laravel** | Capa HTTP. Recibe peticiones, coordina validaciones, invoca servicios y retorna respuestas |
| **Middleware / Políticas** | Capa de seguridad: verifica roles (`EnsureIsSysadmin`), aplica Rate Limiting, evalúa `PacientePolicy` y `ExpedientePolicy` |
| **Modelo / DB (PostgreSQL)** | Capa de persistencia. Los modelos Eloquent aplican `EncryptedFieldCast` transparentemente al guardar/leer |

> **Nota sobre cifrado/descifrado:** Las operaciones criptográficas ocurren de forma transparente a nivel del modelo Eloquent. Cuando un controlador ejecuta `Paciente::create($datos)` o `$paciente->nombre_completo`, el `EncryptedFieldCast` —a través del `EncryptionContextService`— obtiene la clave simétrica `_sym_key` de la sesión del servidor y aplica XSalsa20-Poly1305. Esta clave se deriva de la contraseña del usuario al momento del login mediante Argon2id y nunca se almacena en base de datos ni se envía al navegador.

---

### Diagrama de Secuencia 1: Registro de Paciente (CU-05)

**Descripción:** El Referente Psicosocial registra un nuevo paciente con datos demográficos, contactos y motivo de consulta. El sistema cifra automáticamente todos los datos sensibles antes de persistirlos en PostgreSQL. Es el flujo que combina el formulario más complejo del sistema con la capa criptográfica.

```
Referente          Vue Frontend           PacienteController         Middleware/        Modelo/DB
Psicosocial        (Create.vue)                                     Políticas          (PostgreSQL)
    │                   │                       │                      │                   │
    │ 1. Click          │                       │                      │                   │
    │   "Nuevo          │                       │                      │                   │
    │    Paciente"      │                       │                      │                   │
    │──────────────────>│                       │                      │                   │
    │                   │                       │                      │                   │
    │                   │ 2. GET /pacientes/    │                      │                   │
    │                   │    create             │                      │                   │
    │                   │──────────────────────>│                      │                   │
    │                   │                       │                      │                   │
    │                   │                       │ 3. Verificar rol     │                   │
    │                   │                       │   (abort 403 si      │                   │
    │                   │                       │    no es referente)  │                   │
    │                   │                       │─────────────────────>│                   │
    │                   │                       │                      │                   │
    │                   │                       │ 4. Cargar catálogos  │                   │
    │                   │                       │   Facultades y       │                   │
    │                   │                       │   Carreras           │                   │
    │                   │                       │──────────────────────────────────────────>│
    │                   │                       │<──────────────────────────────────────────│
    │                   │                       │                      │                   │
    │                   │ 5. Renderizar         │                      │                   │
    │                   │    formulario         │                      │                   │
    │                   │<──────────────────────│                      │                   │
    │                   │                       │                      │                   │
    │ 6. Completar      │                       │                      │                   │
    │    formulario     │                       │                      │                   │
    │   (datos personales│                      │                      │                   │
    │    + contactos +   │                       │                      │                   │
    │    motivo consulta)│                       │                      │                   │
    │──────────────────>│                       │                      │                   │
    │                   │                       │                      │                   │
    │ 7. Click          │                       │                      │                   │
    │   "Guardar"       │                       │                      │                   │
    │──────────────────>│                       │                      │                   │
    │                   │                       │                      │                   │
    │                   │ 8. POST /pacientes    │                      │                   │
    │                   │    (StorePaciente-    │                      │                   │
    │                   │     Request)          │                      │                   │
    │                   │──────────────────────>│                      │                   │
    │                   │                       │                      │                   │
    │                   │                       │ 9. Validar campos    │                   │
    │                   │                       │   (carnet único,     │                   │
    │                   │                       │    campos requeridos)│                   │
    │                   │                       │─────────────────────>│                   │
    │                   │                       │<─────────────────────│                   │
    │                   │                       │                      │                   │
    │                   │                       │ 10. DB::transaction()│                   │
    │                   │                       │──────────────────────────────────────────>│
    │                   │                       │                      │                   │
    │                   │                       │ 11. INSERT paciente  │                   │
    │                   │                       │    ┌─────────────────┤                   │
    │                   │                       │    │ EncryptedField- │                   │
    │                   │                       │    │ Cast cifra cada │                   │
    │                   │                       │    │ campo PHI con   │                   │
    │                   │                       │    │ _sym_key de     │                   │
    │                   │                       │    │ sesión usando   │                   │
    │                   │                       │    │ XSalsa20-       │                   │
    │                   │                       │    │ Poly1305.       │                   │
    │                   │                       │    │ Los datos se    │                   │
    │                   │                       │    │ almacenan como  │                   │
    │                   │                       │    │ Base64 ilegible │                   │
    │                   │                       │    └─────────────────┤                   │
    │                   │                       │    INSERT INTO pacientes                 │
    │                   │                       │    (codigo UUID autogenerado,            │
    │                   │                       │     carnet en texto plano,               │
    │                   │                       │     campos PHI ya cifrados)              │
    │                   │                       │                      │                   │
    │                   │                       │ 12. INSERT contactos │                   │
    │                   │                       │    (hasta 3)         │                   │
    │                   │                       │    ┌─────────────────┤                   │
    │                   │                       │    │ Mismo proceso   │                   │
    │                   │                       │    │ de cifrado para │                   │
    │                   │                       │    │ cada contacto   │                   │
    │                   │                       │    └─────────────────┤                   │
    │                   │                       │    INSERT INTO contactos_paciente        │
    │                   │                       │                      │                   │
    │                   │                       │ 13. COMMIT           │                   │
    │                   │                       │<──────────────────────────────────────────│
    │                   │                       │                      │                   │
    │                   │ 14. Redirect 302      │                      │                   │
    │                   │     /dashboard        │                      │                   │
    │                   │<──────────────────────│                      │                   │
    │                   │                       │                      │                   │
    │ 15. Ver Dashboard │                       │                      │                   │
    │     con paciente  │                       │                      │                   │
    │     en lista      │                       │                      │                   │
    │<──────────────────│                       │                      │                   │
```

**Puntos clave:**
- El paso 3 es una barrera dura: `abort(403)` sin posibilidad de bypass desde la UI.
- Los pasos 10-13 son una transacción atómica: si falla la inserción de contactos, el paciente tampoco se crea (y viceversa). Esto evita datos huérfanos.
- En los pasos 11-12, el controlador simplemente llama a `Paciente::create($datos)`. El cifrado es completamente transparente: el `EncryptedFieldCast` del modelo Eloquent intercepta cada campo marcado como `encrypted` en `$casts` y aplica `sodium_crypto_secretbox` antes de escribir en la base de datos. Los datos nunca viajan al navegador en texto plano durante este flujo.

---

### Diagrama de Secuencia 2: Buscar Paciente por Carnet → Ver Expediente (CU-11 → CU-12)

**Descripción:** Un Especialista o Coordinador busca un paciente por su carnet estudiantil. Si el paciente existe y está en su área, el sistema redirige al expediente donde los datos se descifran en tiempo real. Este es el flujo clínico más frecuente del sistema.

```
Coordinador/       Vue Frontend           PacienteController         Middleware/        Modelo/DB
Especialista       (Index.vue             (index / show)             Políticas          (PostgreSQL)
                   → Show.vue)
    │                   │                       │                      │                   │
    │ 1. Navegar a      │                       │                      │                   │
    │    /pacientes     │                       │                      │                   │
    │──────────────────>│                       │                      │                   │
    │                   │                       │                      │                   │
    │                   │ 2. GET /pacientes     │                      │                   │
    │                   │──────────────────────>│                      │                   │
    │                   │                       │                      │                   │
    │                   │ 3. Renderizar         │                      │                   │
    │                   │    campo búsqueda     │                      │                   │
    │                   │<──────────────────────│                      │                   │
    │                   │                       │                      │                   │
    │ 4. Ingresar       │                       │                      │                   │
    │    carnet         │                       │                      │                   │
    │    "CH17001"      │                       │                      │                   │
    │──────────────────>│                       │                      │                   │
    │                   │                       │                      │                   │
    │ 5. Presionar      │                       │                      │                   │
    │    Enter          │                       │                      │                   │
    │──────────────────>│                       │                      │                   │
    │                   │                       │                      │                   │
    │                   │ 6. GET /pacientes?    │                      │                   │
    │                   │    carnet=CH17001     │                      │                   │
    │                   │──────────────────────>│                      │                   │
    │                   │                       │                      │                   │
    │                   │                       │ 7. Rate Limiting     │                   │
    │                   │                       │   (30 req/min)       │                   │
    │                   │                       │─────────────────────>│                   │
    │                   │                       │                      │                   │
    │                   │                       │ 8. Buscar paciente   │                   │
    │                   │                       │   ┌──────────────────┤                   │
    │                   │                       │   │ Según rol:       │                   │
    │                   │                       │   │                  │                   │
    │                   │                       │   │ Referente:       │                   │
    │                   │                       │   │ WHERE carnet     │                   │
    │                   │                       │   │ = 'CH17001'      │                   │
    │                   │                       │   │ AND creado_por_  │                   │
    │                   │                       │   │ profesional_id   │                   │
    │                   │                       │   │ = current_user   │                   │
    │                   │                       │   │                  │                   │
    │                   │                       │   │ Coord/Espec:     │                   │
    │                   │                       │   │ WHERE carnet     │                   │
    │                   │                       │   │ = 'CH17001'      │                   │
    │                   │                       │   │ AND EXISTS       │                   │
    │                   │                       │   │ (SELECT FROM     │                   │
    │                   │                       │   │  expedientes     │                   │
    │                   │                       │   │  WHERE paciente_ │                   │
    │                   │                       │   │  id = pacientes. │                   │
    │                   │                       │   │  id AND area_id  │                   │
    │                   │                       │   │  IN [áreas del   │                   │
    │                   │                       │   │  usuario])       │                   │
    │                   │                       │   └──────────────────┤                   │
    │                   │                       │──────────────────────────────────────────>│
    │                   │                       │<──────────────────────────────────────────│
    │                   │                       │                      │                   │
    │                   │                       │ 9. ¿Encontrado?      │                   │
    │                   │                       │                      │                   │
    │                   │                       │  ┌── SÍ ──────────┐  │                   │
    │                   │                       │  │ Redirect 302 a │  │                   │
    │                   │                       │  │ /pacientes/    │  │                   │
    │                   │                       │  │ {carnet}       │  │                   │
    │                   │                       │  └────────────────┘  │                   │
    │                   │                       │                      │                   │
    │                   │                       │  ┌── NO ──────────┐  │                   │
    │                   │                       │  │ Error genérico │  │                   │
    │                   │                       │  │ "No se encontró│  │                   │
    │                   │                       │  │ ningún paciente│  │                   │
    │                   │                       │  │ con ese carnet"│  │                   │
    │                   │                       │  └────────────────┘  │                   │
    │                   │                       │                      │                   │
    │                   │ 10a. Redirect a       │                      │                   │
    │                   │      expediente       │                      │                   │
    │                   │<──────────────────────│                      │                   │
    │                   │                       │                      │                   │
    │ ═══════════════════════════════════════════════════════════════════ │
    │        INICIO CU-12: Ver Expediente (si paciente encontrado)       │
    │ ═══════════════════════════════════════════════════════════════════ │
    │                   │                       │                      │                   │
    │                   │ 11. GET /pacientes/   │                      │                   │
    │                   │     {carnet}          │                      │                   │
    │                   │──────────────────────>│                      │                   │
    │                   │                       │                      │                   │
    │                   │                       │ 12. PacientePolicy   │                   │
    │                   │                       │    ::view()           │                   │
    │                   │                       │─────────────────────>│                   │
    │                   │                       │                      │                   │
    │                   │                       │  ┌── Verificación ─┐ │                   │
    │                   │                       │  │ Referente:      │ │                   │
    │                   │                       │  │ creado_por ==   │ │                   │
    │                   │                       │  │ current_user    │ │                   │
    │                   │                       │  │                 │ │                   │
    │                   │                       │  │ Coord/Espec:    │ │                   │
    │                   │                       │  │ $paciente->     │ │                   │
    │                   │                       │  │ expedientes()   │ │                   │
    │                   │                       │  │ ->exists()      │ │                   │
    │                   │                       │  │ (con AreaScope) │ │                   │
    │                   │                       │  └─────────────────┘ │                   │
    │                   │                       │                      │                   │
    │                   │                       │  ¿Autorizado?        │                   │
    │                   │                       │  ├── SÍ: continuar   │                   │
    │                   │                       │  └── NO: 403/404     │                   │
    │                   │                       │                      │                   │
    │                   │                       │ 13. Cargar paciente  │                   │
    │                   │                       │    con relaciones    │                   │
    │                   │                       │──────────────────────────────────────────>│
    │                   │                       │                      │                   │
    │                   │                       │   SELECT * FROM pacientes               │
    │                   │                       │   JOIN carreras ON ...                  │
    │                   │                       │   JOIN facultades ON ...                │
    │                   │                       │   LEFT JOIN contactos_paciente           │
    │                   │                       │   WHERE carnet = 'CH17001'              │
    │                   │                       │<──────────────────────────────────────────│
    │                   │                       │                      │                   │
    │                   │                       │ 14. Descifrar campos │                   │
    │                   │                       │    ┌─────────────────┤                   │
    │                   │                       │    │ EncryptedField- │                   │
    │                   │                       │    │ Cast obtiene    │                   │
    │                   │                       │    │ _sym_key de la  │                   │
    │                   │                       │    │ sesión y aplica │                   │
    │                   │                       │    │ sodium_crypto_  │                   │
    │                   │                       │    │ secretbox_open  │                   │
    │                   │                       │    │ a cada campo    │                   │
    │                   │                       │    └─────────────────┤                   │
    │                   │                       │                      │                   │
    │                   │                       │ 15. PacienteResource │                   │
    │                   │                       │    (filtrar campos)  │                   │
    │                   │                       │                      │                   │
    │                   │ 16. Renderizar        │                      │                   │
    │                   │     expediente        │                      │                   │
    │                   │     (datos ya         │                      │                   │
    │                   │     descifrados)      │                      │                   │
    │                   │<──────────────────────│                      │                   │
    │                   │                       │                      │                   │
    │ 17. Visualizar    │                       │                      │                   │
    │     datos         │                       │                      │                   │
    │     completos     │                       │                      │                   │
    │<──────────────────│                       │                      │                   │
```

**Puntos clave:**
- Paso 8: la misma ruta devuelve resultados diferentes según el rol. Un Especialista de Psicología jamás encuentra un paciente que solo tiene expediente en Fisioterapia.
- Paso 9: el mensaje de error es deliberadamente genérico para no revelar si el paciente existe en otra área (anti-enumeración).
- Paso 12: `PacientePolicy` es la última línea de defensa. Incluso si alguien intenta acceder directamente a `/pacientes/CH17001` sin pasar por la búsqueda, la política le niega el acceso.
- Paso 14: los datos se descifran exclusivamente en el servidor. El navegador recibe los datos ya en texto plano, pero solo después de que el servidor verificó permisos y descifró cada campo.

---

### Diagrama de Secuencia 3: Gestionar Usuarios → Crear Usuario (CU-04 → CU-06)

**Descripción:** El Administrador de Sistema crea un nuevo usuario clínico. El sistema genera credenciales seguras y envía un enlace temporal por correo para la configuración de contraseña, sacando al administrador de la cadena de confianza.

```
Administrador      Vue Frontend           UserController             Modelo/DB          Servicio de
(sysadmin)         (Index.vue                                    (PostgreSQL)          Correo
                   → Form.vue)
    │                   │                       │                      │                   │
    │ 1. Acceder a      │                       │                      │                   │
    │    /admin/users   │                       │                      │                   │
    │──────────────────>│                       │                      │                   │
    │                   │                       │                      │                   │
    │                   │ 2. GET /admin/users   │                      │                   │
    │                   │──────────────────────>│                      │                   │
    │                   │                       │                      │                   │
    │                   │                       │ 3. Consultar         │                   │
    │                   │                       │    usuarios + roles   │                   │
    │                   │                       │    + áreas            │                   │
    │                   │                       │─────────────────────>│                   │
    │                   │                       │<─────────────────────│                   │
    │                   │                       │                      │                   │
    │                   │                       │ 4. Calcular métricas │                   │
    │                   │                       │   (total, activos,   │                   │
    │                   │                       │    ratio coord.)     │                   │
    │                   │                       │                      │                   │
    │                   │ 5. Renderizar tabla   │                      │                   │
    │                   │    + métricas         │                      │                   │
    │                   │<──────────────────────│                      │                   │
    │                   │                       │                      │                   │
    │ 6. Click "Nuevo   │                       │                      │                   │
    │    Usuario"       │                       │                      │                   │
    │──────────────────>│                       │                      │                   │
    │                   │                       │                      │                   │
    │                   │ 7. GET /admin/users/  │                      │                   │
    │                   │    create             │                      │                   │
    │                   │──────────────────────>│                      │                   │
    │                   │                       │                      │                   │
    │                   │                       │ 8. Cargar roles y    │                   │
    │                   │                       │    áreas             │                   │
    │                   │                       │─────────────────────>│                   │
    │                   │                       │<─────────────────────│                   │
    │                   │                       │                      │                   │
    │                   │ 9. Renderizar         │                      │                   │
    │                   │    formulario         │                      │                   │
    │                   │    (Form.vue)         │                      │                   │
    │                   │<──────────────────────│                      │                   │
    │                   │                       │                      │                   │
    │ 10. Completar     │                       │                      │                   │
    │     datos         │                       │                      │                   │
    │    (nombre, email, │                       │                      │                   │
    │     rol, área)    │                       │                      │                   │
    │──────────────────>│                       │                      │                   │
    │                   │                       │                      │                   │
    │ 11. Click         │                       │                      │                   │
    │     "Guardar"     │                       │                      │                   │
    │──────────────────>│                       │                      │                   │
    │                   │                       │                      │                   │
    │                   │ 12. POST /admin/users │                      │                   │
    │                   │──────────────────────>│                      │                   │
    │                   │                       │                      │                   │
    │                   │                       │ 13. Validar regla    │                   │
    │                   │                       │    de negocio:       │                   │
    │                   │                       │    ¿Ya existe coord. │                   │
    │                   │                       │    en esta área?     │                   │
    │                   │                       │─────────────────────>│                   │
    │                   │                       │<─────────────────────│                   │
    │                   │                       │                      │                   │
    │                   │                       │ 14. DB::transaction()│                   │
    │                   │                       │─────────────────────>│                   │
    │                   │                       │                      │                   │
    │                   │                       │  a. Generar password │                   │
    │                   │                       │     aleatorio (16ch) │                   │
    │                   │                       │  b. Hash Argon2id    │                   │
    │                   │                       │  c. Generar kdf_salt │                   │
    │                   │                       │  d. INSERT users     │                   │
    │                   │                       │  e. INSERT role_user │                   │
    │                   │                       │  f. INSERT           │                   │
    │                   │                       │     profesionales    │                   │
    │                   │                       │     (si rol clínico) │                   │
    │                   │                       │                      │                   │
    │                   │                       │ 15. COMMIT           │                   │
    │                   │                       │<─────────────────────│                   │
    │                   │                       │                      │                   │
    │                   │                       │ 16. Enviar correo    │                   │
    │                   │                       │    con URL firmada   │                   │
    │                   │                       │──────────────────────────────────────────>│
    │                   │                       │                      │                   │
    │                   │                       │   UserCreated-       │  ┌─────────────────│
    │                   │                       │   Notification       │  │ Email con:      │
    │                   │                       │   (temporarySigned-  │  │ - URL firmada   │
    │                   │                       │    Route, 24h)       │  │ - Expira 24h    │
    │                   │                       │                      │  │ - Link para     │
    │                   │                       │                      │  │   configurar    │
    │                   │                       │                      │  │   contraseña    │
    │                   │                       │                      │  └─────────────────│
    │                   │                       │                      │                   │
    │                   │ 17. Redirect 302      │                      │                   │
    │                   │     /admin/users      │                      │                   │
    │                   │<──────────────────────│                      │                   │
    │                   │                       │                      │                   │
    │ 18. Ver tabla     │                       │                      │                   │
    │     actualizada   │                       │                      │                   │
    │     con nuevo     │                       │                      │                   │
    │     usuario       │                       │                      │                   │
    │<──────────────────│                       │                      │                   │
```

**Puntos clave:**
- Paso 13: regla de negocio — solo puede existir un Coordinador por Área. El sistema lo valida antes de insertar.
- Paso 16: la contraseña temporal nunca se muestra en pantalla. Se envía por correo con una URL firmada (`URL::temporarySignedRoute`) que expira en 24 horas. Esto implementa el principio de "el sysadmin fuera de la cadena de confianza".
- Pasos 14-15: todas las inserciones (a-f) ocurren en una transacción atómica. Si cualquiera falla, todo se revierte.

---

### Diagrama de Secuencia 4: Búsqueda Segura por UUID → Ver Expediente (CU-09 → CU-12)

**Descripción:** El Coordinador de Área busca un paciente por su código UUID anonimizado. El sistema retorna un resultado sin exponer datos personales; solo muestra el UUID encontrado. Si el coordinador decide ver el expediente, se aplica el mismo flujo de autorización y descifrado de CU-12.

```
Coordinador        Vue Frontend           SecureSearchController       Middleware/        Modelo/DB
de Área            (SecureSearch/                                   Políticas          (PostgreSQL)
                   Index.vue)
    │                   │                       │                      │                   │
    │ 1. Navegar a      │                       │                      │                   │
    │    /busqueda-     │                       │                      │                   │
    │    segura         │                       │                      │                   │
    │──────────────────>│                       │                      │                   │
    │                   │                       │                      │                   │
    │                   │ 2. GET /busqueda-     │                      │                   │
    │                   │    segura             │                      │                   │
    │                   │──────────────────────>│                      │                   │
    │                   │                       │                      │                   │
    │                   │ 3. Renderizar campo   │                      │                   │
    │                   │    búsqueda UUID      │                      │                   │
    │                   │<──────────────────────│                      │                   │
    │                   │                       │                      │                   │
    │ 4. Ingresar UUID  │                       │                      │                   │
    │    a1b2c3d4-e5f6- │                       │                      │                   │
    │    7890-abcd-     │                       │                      │                   │
    │    ef1234567890   │                       │                      │                   │
    │──────────────────>│                       │                      │                   │
    │                   │                       │                      │                   │
    │ 5. Click "Buscar" │                       │                      │                   │
    │──────────────────>│                       │                      │                   │
    │                   │                       │                      │                   │
    │                   │ 6. POST /busqueda-    │                      │                   │
    │                   │    segura             │                      │                   │
    │                   │    {uuid: "a1b2..."}  │                      │                   │
    │                   │──────────────────────>│                      │                   │
    │                   │                       │                      │                   │
    │                   │                       │ 7. Validar formato   │                   │
    │                   │                       │    UUID (regex)      │                   │
    │                   │                       │                      │                   │
    │                   │                       │ 8. Buscar por UUID   │                   │
    │                   │                       │    exacto + RBAC     │                   │
    │                   │                       │   ┌──────────────────┤                   │
    │                   │                       │   │ Coord/Espec:     │                   │
    │                   │                       │   │ WHERE codigo =   │                   │
    │                   │                       │   │ 'a1b2...'        │                   │
    │                   │                       │   │ AND EXISTS       │                   │
    │                   │                       │   │ (SELECT FROM     │                   │
    │                   │                       │   │  expedientes     │                   │
    │                   │                       │   │  WHERE ...       │                   │
    │                   │                       │   │  [AreaScope])    │                   │
    │                   │                       │   │                  │                   │
    │                   │                       │   │ Referente:       │                   │
    │                   │                       │   │ WHERE codigo =   │                   │
    │                   │                       │   │ 'a1b2...'        │                   │
    │                   │                       │   │ AND creado_por_  │                   │
    │                   │                       │   │ profesional_id   │                   │
    │                   │                       │   │ = current_user   │                   │
    │                   │                       │   └──────────────────┤                   │
    │                   │                       │──────────────────────────────────────────>│
    │                   │                       │<──────────────────────────────────────────│
    │                   │                       │                      │                   │
    │                   │                       │ 9. Evaluar resultado │                   │
    │                   │                       │                      │                   │
    │                   │                       │  ┌── SÍ ──────────┐  │                   │
    │                   │                       │  │ Mostrar:       │  │                   │
    │                   │                       │  │ "Código        │  │                   │
    │                   │                       │  │ encontrado:    │  │                   │
    │                   │                       │  │ a1b2c3d4-..."  │  │                   │
    │                   │                       │  │                │  │                   │
    │                   │                       │  │ [Ver Expediente]│  │                   │
    │                   │                       │  │ [Historial]    │  │                   │
    │                   │                       │  └────────────────┘  │                   │
    │                   │                       │                      │                   │
    │                   │                       │  ┌── NO ──────────┐  │                   │
    │                   │                       │  │ "No se encontró│  │                   │
    │                   │                       │  │ ningún paciente│  │                   │
    │                   │                       │  │ con ese código"│  │                   │
    │                   │                       │  └────────────────┘  │                   │
    │                   │                       │                      │                   │
    │                   │ 10. Renderizar        │                      │                   │
    │                   │     resultado         │                      │                   │
    │                   │     anonimizado       │                      │                   │
    │                   │     (solo UUID)       │                      │                   │
    │                   │<──────────────────────│                      │                   │
    │                   │                       │                      │                   │
    │ 11. Ver resultado │                       │                      │                   │
    │     (UUID sin     │                       │                      │                   │
    │     datos)        │                       │                      │                   │
    │<──────────────────│                       │                      │                   │
    │                   │                       │                      │                   │
    │ ═══════════════════════════════════════════════════════════════════ │
    │   Si pulsa "Ver Expediente" → mismo flujo CU-12 (pasos 11-17)    │
    │   del Diagrama 2: autorización, descifrado, renderizado           │
    │ ═══════════════════════════════════════════════════════════════════ │
```

**Puntos clave:**
- Diferencia fundamental con CU-11: el resultado de la búsqueda **nunca expone el carnet ni el nombre**. Solo muestra el UUID. Esto preserva la anonimización incluso durante la búsqueda.
- Paso 8: aplica las mismas reglas RBAC que CU-11. Un Coordinador de Medicina General jamás encuentra un paciente de Psicología.
- El paso intermedio (resultado anonimizado) es la característica distintiva: el usuario decide si accede al expediente completo solo después de confirmar que el UUID existe en su ámbito autorizado.

---

## 5. Matriz Actor vs Caso de Uso

| Caso de Uso | sysadmin | area_coordinator | psychosocial_referent | specialist |
|-------------|:--------:|:----------------:|:---------------------:|:----------:|
| CU-02 Ver Dashboard Clínico | — | ✅ | ✅ | ✅ |
| CU-03 Ver Dashboard Administrativo | ✅ | — | — | — |
| CU-04 Gestionar Usuarios | ✅ | — | — | — |
| CU-06 Crear Usuario | ✅ | — | — | — |
| CU-07 Editar Usuario | ✅ | — | — | — |
| CU-08 Auditar Pacientes | ✅ | — | — | — |
| CU-10 Ver Organigrama | ✅ | — | — | — |
| CU-05 Registrar Paciente | — | — | ✅ | — |
| CU-11 Buscar Paciente por Carnet | — | ✅ | ✅ | ✅ |
| CU-09 Búsqueda Segura por UUID | ✅ | ✅ | ✅ | ✅ |
| CU-12 Ver Expediente del Paciente | — | ✅ | ✅ | ✅ |

> ✅ = Autorizado | — = Sin acceso (middleware/Policies bloquean con 403 o 404)

---

## Observaciones Finales

1. **Separación estricta administrador/datos clínicos:** El Administrador de Sistema tiene el máximo nivel de privilegios administrativos (nivel 100) pero cero acceso a datos clínicos. Esta decisión de diseño cumple con el principio de mínimo privilegio (OWASP ASVS V4.1.1) y con normativas de privacidad de datos de salud.

2. **Cifrado transparente para el usuario:** En CU-05 (Registrar Paciente) y CU-12 (Ver Expediente), el usuario nunca interactúa con datos cifrados ni con claves. El `EncryptedFieldCast` opera a nivel del modelo Eloquent, cifrando al guardar y descifrando al leer. La clave simétrica `_sym_key` se deriva de la contraseña mediante Argon2id durante el login y reside exclusivamente en la sesión del servidor.

3. **Defensa en profundidad (RBAC en 3 capas):** Cada caso de uso está protegido por: (a) middleware de ruta (`role:`, `sysadmin`), (b) Global Scope de Eloquent (`AreaScope`), y (c) Policies de Laravel (`PacientePolicy`, `ExpedientePolicy`). Ningún CU puede ejecutarse si las tres capas no autorizan.

4. **Implementación completa:** Los 10 casos de uso descritos corresponden a pantallas funcionales con controladores, vistas Vue, políticas de acceso y pruebas automatizadas que verifican su correcto funcionamiento (16 pruebas de compliance con 100% de éxito).
