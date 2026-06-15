# Sistema PANDORA — Casos de Uso y Diagramas de Secuencia

> **Materia:** DSI115 — Ingeniería de Software  
> **Proyecto:** PANDORA — Sistema Integral de Gestión Clínica Universitaria  
> **Fecha:** Junio 2026  

---

## Índice

1. [Actores del Sistema](#1-actores-del-sistema)
2. [Convenciones en los Diagramas](#2-convenciones-en-los-diagramas)
3. [Casos de Uso](#3-casos-de-uso)
   - [CU-01: Ver Dashboard Clínico](#cu-01-ver-dashboard-clínico)
   - [CU-02: Ver Dashboard Administrativo](#cu-02-ver-dashboard-administrativo)
   - [CU-03: Gestionar Usuarios](#cu-03-gestionar-usuarios)
   - [CU-04: Crear Usuario](#cu-04-crear-usuario)
   - [CU-05: Editar Usuario](#cu-05-editar-usuario)
   - [CU-06: Auditar Pacientes](#cu-06-auditar-pacientes)
   - [CU-07: Ver Organigrama](#cu-07-ver-organigrama)
   - [CU-08: Registrar Paciente](#cu-08-registrar-paciente)
   - [CU-09: Buscar Paciente por Carnet](#cu-09-buscar-paciente-por-carnet)
   - [CU-10: Ver Expediente del Paciente](#cu-10-ver-expediente-del-paciente)
   - [CU-11: Búsqueda Segura por UUID](#cu-11-búsqueda-segura-por-uuid)
4. [Matriz Actor vs Caso de Uso](#4-matriz-actor-vs-caso-de-uso)

---

## 1. Actores del Sistema

PANDORA define cuatro actores con permisos estrictamente delimitados por RBAC.

### Actor 1: Administrador de Sistema (`sysadmin`)
- **Nivel:** 100 (máximo)
- **Propósito:** Gestión organizativa: usuarios, áreas clínicas, roles.
- **Restricción:** No accede a datos clínicos (expedientes, diagnósticos).
- **Pantallas:** Dashboard Administrativo, Gestión de Usuarios, Auditoría de Pacientes (solo UUIDs), Organigrama.

### Actor 2: Coordinador de Área (`area_coordinator`)
- **Nivel:** 50
- **Propósito:** Supervisar expedientes clínicos de su área (solo lectura).
- **Restricción:** Solo ve pacientes con expedientes en su área. Un coordinador por área.
- **Pantallas:** Dashboard Clínico, Búsqueda por Carnet, Ver Expediente, Búsqueda Segura.

### Actor 3: Referente Psicosocial (`psychosocial_referent`)
- **Nivel:** 20
- **Propósito:** Punto de entrada: registra pacientes y crea el primer expediente.
- **Restricción:** Solo ve los pacientes que él/ella registró.
- **Pantallas:** Dashboard Clínico (con métricas), Registro de Paciente, Búsqueda por Carnet, Ver Expediente, Búsqueda Segura.

### Actor 4: Especialista (`specialist`)
- **Nivel:** 10 (mínimo)
- **Propósito:** Atender pacientes en su especialidad (consultas, notas de evolución).
- **Restricción:** Solo ve pacientes con expedientes en su área clínica. No registra pacientes.
- **Pantallas:** Dashboard Clínico, Búsqueda por Carnet, Ver Expediente, Búsqueda Segura.

---

## 2. Convenciones en los Diagramas

| Participante | Descripción |
|---|---|
| **Actor** | Rol que inicia la acción |
| **Vue Frontend** | Interfaz Inertia.js (Vue 3). Renderiza páginas |
| **Controlador** | Capa HTTP. Coordina validaciones, invoca servicios |
| **Middleware / Policies** | Seguridad: roles, Rate Limiting, políticas de acceso |
| **Modelo / DB** | Persistencia PostgreSQL con `EncryptedFieldCast` |

---

## 3. Casos de Uso

---

### CU-01: Ver Dashboard Clínico

**Ruta:** `/dashboard`
**Actor Principal:** Referente Psicosocial, Coordinador de Área, Especialista

**Precondiciones:** Usuario autenticado con rol clínico. Si el usuario es `sysadmin`, se redirige al Dashboard Administrativo.

**Flujo Principal:**

1. El usuario accede a `/dashboard`.
2. El sistema verifica el rol del usuario.
3. Si es **Referente Psicosocial**:
   - Consulta pacientes donde `creado_por_profesional_id` coincide con el profesional autenticado.
   - Calcula dos métricas: total de pacientes registrados y expedientes activos.
   - Muestra tabla con los 10 pacientes más recientes (nombre descifrado + carnet).
4. Si es **Coordinador** o **Especialista**:
   - Muestra dashboard con métricas en cero y lista vacía.
   - Acceden a pacientes solo mediante búsqueda explícita (CU-09 o CU-11).
5. El usuario visualiza según su rol.

**Flujo Alternativo:** Usuario sin rol clínico asignado → dashboard vacío sin errores.

**Postcondiciones:** Dashboard personalizado con datos segmentados por rol.

```
Referente/         Vue Frontend           Controlador              Modelo/DB
Coordinador        (Dashboard.vue)        (inline en ruta)         (PostgreSQL)
    │                    │                      │                     │
    │ 1. GET /dashboard  │                      │                     │
    │───────────────────>│                      │                     │
    │                    │ 2. GET /dashboard     │                     │
    │                    │─────────────────────>│                     │
    │                    │                      │                     │
    │                    │                      │ 3. Verificar rol    │
    │                    │                      │                     │
    │                    │                      │ ┌── Referente ────┐ │
    │                    │                      │ │ Consultar       │ │
    │                    │                      │ │ pacientes por   │ │
    │                    │                      │ │ creado_por_     │ │
    │                    │                      │ │ profesional_id  │ │
    │                    │                      │ └─────────────────┘ │
    │                    │                      │────────────────────>│
    │                    │                      │<────────────────────│
    │                    │                      │                     │
    │                    │                      │ 4. Calcular        │
    │                    │                      │    métricas        │
    │                    │                      │                     │
    │                    │ 5. Renderizar        │                     │
    │                    │    Dashboard         │                     │
    │                    │<─────────────────────│                     │
    │ 6. Ver dashboard   │                      │                     │
    │    según rol       │                      │                     │
    │<───────────────────│                      │                     │
```

---

### CU-02: Ver Dashboard Administrativo

**Ruta:** `/admin/dashboard`
**Actor Principal:** Administrador de Sistema

**Precondiciones:** Usuario autenticado como `sysadmin`.

**Flujo Principal:**

1. El administrador accede a `/admin/dashboard`.
2. El middleware `EnsureIsSysadmin` verifica rol sysadmin (nivel 100). Si no, HTTP 403.
3. El sistema renderiza panel de bienvenida con accesos directos: Usuarios, Pacientes, Organigrama.

**Flujo Alternativo:** Usuario clínico intenta acceder a `/admin/*` → middleware bloquea con HTTP 403.

**Postcondiciones:** Panel de control general visible para el administrador.

```
Administrador      Vue Frontend           AdminDashboard            Middleware
(sysadmin)         (Dashboard.vue)        Controller                EnsureIsSysadmin
    │                    │                      │                     │
    │ 1. GET /admin/     │                      │                     │
    │    dashboard       │                      │                     │
    │───────────────────>│                      │                     │
    │                    │ 2. GET /admin/       │                     │
    │                    │    dashboard         │                     │
    │                    │─────────────────────>│                     │
    │                    │                      │ 3. Verificar       │
    │                    │                      │    sysadmin        │
    │                    │                      │────────────────────>│
    │                    │                      │<────────────────────│
    │                    │                      │                     │
    │                    │ 4. Renderizar        │                     │
    │                    │    panel admin       │                     │
    │                    │<─────────────────────│                     │
    │ 5. Ver menú        │                      │                     │
    │    de gestión      │                      │                     │
    │<───────────────────│                      │                     │
```

---

### CU-03: Gestionar Usuarios

**Ruta:** `/admin/users`
**Actor Principal:** Administrador de Sistema

**Precondiciones:** Administrador autenticado como sysadmin.

**Flujo Principal:**

1. El administrador accede a `/admin/users`.
2. El sistema consulta todos los usuarios con sus relaciones (rol, área, especialidad).
3. Se renderiza tabla paginada (15 registros/página) con columnas: Nombre, Email, Rol, Área/Especialidad, Estado, Acciones.
4. En la parte superior se muestran tres tarjetas métricas: Total Usuarios, Usuarios Activos, Ratio Coordinadores/Área.
5. El administrador puede:
   - **Filtrar** por nombre o email (barra de búsqueda en tiempo real).
   - **Filtrar** por rol (selector desplegable).
   - **Eliminar** usuario con soft-delete (`is_active = false`). No puede eliminarse a sí mismo.
6. Desde aquí navega a Crear Usuario (CU-04) o Editar Usuario (CU-05).

**Flujo Alternativo:** Sin resultados → estado vacío con mensaje "No se encontraron usuarios".

**Postcondiciones:** Lista completa de usuarios con métricas visibles.

```
Administrador      Vue Frontend           UserController            Modelo/DB
(sysadmin)         (Index.vue)                                    (PostgreSQL)
    │                    │                      │                     │
    │ 1. GET             │                      │                     │
    │    /admin/users    │                      │                     │
    │───────────────────>│                      │                     │
    │                    │ 2. GET /admin/users  │                     │
    │                    │─────────────────────>│                     │
    │                    │                      │ 3. Consultar        │
    │                    │                      │    usuarios + roles │
    │                    │                      │    + áreas          │
    │                    │                      │────────────────────>│
    │                    │                      │<────────────────────│
    │                    │                      │                     │
    │                    │                      │ 4. Calcular         │
    │                    │                      │    métricas         │
    │                    │                      │                     │
    │                    │ 5. Renderizar tabla  │                     │
    │                    │    + métricas        │                     │
    │                    │<─────────────────────│                     │
    │ 6. Visualizar     │                       │                     │
    │    listado +      │                       │                     │
    │    métricas       │                       │                     │
    │<───────────────────│                       │                     │
    │                    │                       │                     │
    │ 7. (Opcional)      │                       │                     │
    │    Filtrar/Eliminar│                       │                     │
    │───────────────────>│                       │                     │
    │                    │ 8. DELETE /admin/     │                     │
    │                    │    users/{id}         │                     │
    │                    │─────────────────────>│                     │
    │                    │                      │ 9. Soft-delete      │
    │                    │                      │    (is_active=false) │
    │                    │                      │────────────────────>│
    │                    │                      │<────────────────────│
    │                    │ 10. Tabla actualizada│                     │
    │                    │<─────────────────────│                     │
```

---

### CU-04: Crear Usuario

**Ruta:** `/admin/users/create`
**Actor Principal:** Administrador de Sistema
**Relación:** Se accede desde CU-03 (Gestionar Usuarios).

**Precondiciones:** Administrador en CU-03.

**Flujo Principal:**

1. El administrador pulsa "Nuevo Usuario" en CU-03.
2. Navega a `/admin/users/create`. El sistema carga catálogos de roles y áreas.
3. Formulario de dos columnas:
   - **Izquierda:** nombre, email, teléfono.
   - **Derecha:** selector de rol (si es clínico, se despliegan área y especialidad).
4. Completa campos y pulsa "Guardar Usuario".
5. El backend (`UserController@store`) valida:
   - **Regla de negocio:** si el rol es `area_coordinator`, verifica que no exista otro coordinador activo en la misma área.
6. Transacción atómica:
   - Genera contraseña aleatoria (16 caracteres).
   - Hash Argon2id + `kdf_salt` único.
   - Crea registro en `users` con `must_change_password = true`.
   - Asigna rol en `role_user`.
   - Si es clínico, crea perfil en `profesionales`.
7. Envía correo (`UserCreatedNotification`) con URL firmada temporal (24h) para configurar contraseña.
8. Redirige a CU-03 con lista actualizada.

**Flujo Alternativo:** Validación falla → retorna al formulario con errores. Segundo coordinador en misma área → rechazo con mensaje.

**Postcondiciones:** Nuevo usuario registrado. Recibe correo con enlace temporal. Debe cambiar contraseña en primer acceso.

```
Administrador      Vue Frontend           UserController            Modelo/DB          Servicio
(sysadmin)         (Form.vue)                                    (PostgreSQL)        de Correo
    │                    │                      │                     │                   │
    │ 1. Click           │                      │                     │                   │
    │    "Nuevo Usuario" │                      │                     │                   │
    │───────────────────>│                      │                     │                   │
    │                    │ 2. GET /admin/       │                     │                   │
    │                    │    users/create      │                     │                   │
    │                    │─────────────────────>│                     │                   │
    │                    │                      │ 3. Cargar roles    │                   │
    │                    │                      │    y áreas         │                   │
    │                    │                      │────────────────────>│                   │
    │                    │                      │<────────────────────│                   │
    │                    │ 4. Renderizar        │                     │                   │
    │                    │    formulario        │                     │                   │
    │                    │<─────────────────────│                     │                   │
    │ 5. Completar       │                      │                     │                   │
    │    datos           │                      │                     │                   │
    │───────────────────>│                      │                     │                   │
    │ 6. Click "Guardar" │                      │                     │                   │
    │───────────────────>│                      │                     │                   │
    │                    │ 7. POST /admin/      │                     │                   │
    │                    │    users             │                     │                   │
    │                    │─────────────────────>│                     │                   │
    │                    │                      │ 8. Validar regla    │                   │
    │                    │                      │    (único coord.    │                   │
    │                    │                      │    por área)        │                   │
    │                    │                      │────────────────────>│                   │
    │                    │                      │<────────────────────│                   │
    │                    │                      │                     │                   │
    │                    │                      │ 9. DB::transaction  │                   │
    │                    │                      │────────────────────>│                   │
    │                    │                      │  a. Generar pass    │                   │
    │                    │                      │  b. Hash Argon2id   │                   │
    │                    │                      │  c. INSERT users    │                   │
    │                    │                      │  d. INSERT role_user│                   │
    │                    │                      │  e. INSERT prof.    │                   │
    │                    │                      │ 10. COMMIT          │                   │
    │                    │                      │<────────────────────│                   │
    │                    │                      │                     │                   │
    │                    │                      │ 11. Enviar correo   │                   │
    │                    │                      │    (URL firmada)    │──────────────────>│
    │                    │                      │                     │                   │
    │                    │ 12. Redirect 302     │                     │                   │
    │                    │     /admin/users     │                     │                   │
    │                    │<─────────────────────│                     │                   │
    │ 13. Ver tabla     │                       │                     │                   │
    │     actualizada   │                       │                     │                   │
    │<───────────────────│                       │                     │                   │
```

---

### CU-05: Editar Usuario

**Ruta:** `/admin/users/{id}/edit`
**Actor Principal:** Administrador de Sistema
**Relación:** Se accede desde CU-03 (Gestionar Usuarios).

**Precondiciones:** Administrador en CU-03 con al menos un usuario listado.

**Flujo Principal:**

1. El administrador pulsa "Editar" en la fila de un usuario.
2. Navega a `/admin/users/{id}/edit`. El sistema precarga datos actuales.
3. El campo de contraseña aparece bloqueado (no modificable desde este panel por seguridad criptográfica).
4. El administrador modifica los campos necesarios y pulsa "Guardar Cambios".
5. El backend (`UserController@update`) ejecuta:
   - Actualización de datos básicos.
   - Sincronización de rol (crea/elimina perfil profesional si cambia de clínico a sysadmin o viceversa).
   - **Regla de negocio:** si nuevo rol es `area_coordinator`, verifica unicidad por área (excluyendo al propio usuario).
6. Redirige a CU-03 con lista actualizada.

**Flujo Alternativo:** Segundo coordinador en misma área → rechazo con mensaje.

**Postcondiciones:** Usuario actualizado. Clave criptográfica preservada.

```
Administrador      Vue Frontend           UserController            Modelo/DB
(sysadmin)         (Form.vue)                                    (PostgreSQL)
    │                    │                      │                     │
    │ 1. Click           │                      │                     │
    │    "Editar"        │                      │                     │
    │───────────────────>│                      │                     │
    │                    │ 2. GET /admin/       │                     │
    │                    │    users/{id}/edit   │                     │
    │                    │─────────────────────>│                     │
    │                    │                      │ 3. Cargar datos    │
    │                    │                      │    del usuario     │
    │                    │                      │────────────────────>│
    │                    │                      │<────────────────────│
    │                    │ 4. Renderizar        │                     │
    │                    │    formulario        │                     │
    │                    │    precargado        │                     │
    │                    │<─────────────────────│                     │
    │ 5. Modificar       │                      │                     │
    │    campos          │                      │                     │
    │───────────────────>│                      │                     │
    │ 6. Click           │                      │                     │
    │    "Guardar"       │                      │                     │
    │───────────────────>│                      │                     │
    │                    │ 7. PUT /admin/       │                     │
    │                    │    users/{id}        │                     │
    │                    │─────────────────────>│                     │
    │                    │                      │ 8. Validar unicidad│
    │                    │                      │    coord. por área │
    │                    │                      │────────────────────>│
    │                    │                      │<────────────────────│
    │                    │                      │ 9. UPDATE users    │
    │                    │                      │    + sinc. rol     │
    │                    │                      │────────────────────>│
    │                    │                      │<────────────────────│
    │                    │ 10. Redirect 302     │                     │
    │                    │     /admin/users     │                     │
    │                    │<─────────────────────│                     │
    │ 11. Ver tabla     │                       │                     │
    │     actualizada   │                       │                     │
    │<───────────────────│                       │                     │
```

---

### CU-06: Auditar Pacientes

**Ruta:** `/admin/pacientes`
**Actor Principal:** Administrador de Sistema

**Precondiciones:** Administrador autenticado como sysadmin.

**Flujo Principal:**

1. El administrador accede a `/admin/pacientes`.
2. El sistema consulta la tabla `pacientes` pero expone exclusivamente: `codigo` (UUID) y `created_at`.
3. No se muestra ningún dato personal (ni nombre, carnet, dirección, contactos).
4. Tabla paginada con todos los pacientes del sistema.
5. El administrador puede buscar en tiempo real por UUID (búsqueda parcial, debounce 300ms, `ILIKE` sobre `codigo::text`).
6. Solo fines de auditoría administrativa. No permite ver detalles clínicos.

**Flujo Alternativo:** Sin pacientes registrados → estado vacío.

**Postcondiciones:** Lista anonimizada de pacientes para trazabilidad administrativa.

```
Administrador      Vue Frontend           AdminPaciente             Modelo/DB
(sysadmin)         (Index.vue)            Controller                (PostgreSQL)
    │                    │                      │                     │
    │ 1. GET             │                      │                     │
    │    /admin/         │                      │                     │
    │    pacientes       │                      │                     │
    │───────────────────>│                      │                     │
    │                    │ 2. GET /admin/       │                     │
    │                    │    pacientes         │                     │
    │                    │─────────────────────>│                     │
    │                    │                      │ 3. SELECT codigo,  │
    │                    │                      │    created_at      │
    │                    │                      │    FROM pacientes  │
    │                    │                      │────────────────────>│
    │                    │                      │<────────────────────│
    │                    │ 4. Renderizar tabla  │                     │
    │                    │    (solo UUID +      │                     │
    │                    │     fecha)           │                     │
    │                    │<─────────────────────│                     │
    │ 5. Visualizar     │                       │                     │
    │    lista          │                       │                     │
    │    anonimizada    │                       │                     │
    │<───────────────────│                       │                     │
```

---

### CU-07: Ver Organigrama

**Ruta:** `/admin/organigrama`
**Actor Principal:** Administrador de Sistema

**Precondiciones:** Administrador autenticado como sysadmin.

**Flujo Principal:**

1. El administrador accede a `/admin/organigrama`.
2. El sistema consulta roles, áreas y usuarios con sus relaciones.
3. La información se agrupa jerárquicamente:
   - **Por Rol:** cantidad de usuarios por rol.
   - **Por Área Clínica:** coordinador, especialistas y referentes de cada área.
4. Vista completa de la estructura organizativa del sistema.

**Postcondiciones:** Estructura jerárquica de roles, áreas y personal visible.

```
Administrador      Vue Frontend           OrganigramaController     Modelo/DB
(sysadmin)         (Index.vue)                                      (PostgreSQL)
    │                    │                      │                     │
    │ 1. GET             │                      │                     │
    │    /admin/         │                      │                     │
    │    organigrama     │                      │                     │
    │───────────────────>│                      │                     │
    │                    │ 2. GET /admin/       │                     │
    │                    │    organigrama       │                     │
    │                    │─────────────────────>│                     │
    │                    │                      │ 3. Consultar roles,│
    │                    │                      │    áreas, usuarios │
    │                    │                      │────────────────────>│
    │                    │                      │<────────────────────│
    │                    │ 4. Renderizar        │                     │
    │                    │    organigrama       │                     │
    │                    │    agrupado          │                     │
    │                    │<─────────────────────│                     │
    │ 5. Visualizar     │                       │                     │
    │    estructura     │                       │                     │
    │    organizativa   │                       │                     │
    │<───────────────────│                       │                     │
```

---

### CU-08: Registrar Paciente

**Ruta:** `/pacientes/create`
**Actor Principal:** Referente Psicosocial (exclusivo)

**Precondiciones:** Usuario con rol `psychosocial_referent`. Si no, HTTP 403.

**Flujo Principal:**

1. El Referente, desde el Dashboard Clínico, pulsa "Registrar Paciente" o navega a `/pacientes/create`.
2. El controlador verifica el rol; si no es referente, `abort(403)`.
3. El sistema carga catálogos de Facultades y Carreras (selector dinámico: Carrera se filtra por Facultad).
4. Formulario con secciones:
   - **Datos del Paciente:** carnet (único, no cifrado), sexo, estado civil, nombre completo, dirección, fecha de nacimiento, profesión, referido por, llevado por, fecha primera consulta, motivo de consulta.
   - **Contactos (hasta 3):** Padre, Madre, Responsable Legal. Cada uno: nombre, parentesco, teléfonos, dirección.
5. Completa y pulsa "Guardar Paciente".
6. El backend (`PacienteController@store`) en transacción atómica:
   - Valida campos mediante `StorePacienteRequest`.
   - Genera UUID automático (`uuid-ossp`).
   - Inserta en `pacientes`: los campos PHI se cifran automáticamente con `EncryptedFieldCast` (XSalsa20-Poly1305). El carnet se almacena sin cifrar.
   - Inserta hasta 3 contactos (datos también cifrados).
7. Confirma transacción. Si algo falla, rollback total.
8. Redirige al Dashboard Clínico (CU-01), donde el paciente aparece en "Pacientes Recientes".

**Flujo Alternativo:** Validación falla → retorna al formulario con errores en español. Error en transacción → rollback y mensaje genérico.

**Postcondiciones:** Paciente registrado con UUID. Datos sensibles cifrados en reposo. Solo visible para el Referente que lo creó.

```
Referente          Vue Frontend           PacienteController        Middleware/        Modelo/DB
Psicosocial        (Create.vue)                                    Políticas          (PostgreSQL)
    │                    │                      │                     │                   │
    │ 1. Click           │                      │                     │                   │
    │    "Nuevo          │                      │                     │                   │
    │    Paciente"       │                      │                     │                   │
    │───────────────────>│                      │                     │                   │
    │                    │ 2. GET /pacientes/   │                     │                   │
    │                    │    create            │                     │                   │
    │                    │─────────────────────>│                     │                   │
    │                    │                      │ 3. Verificar rol   │                   │
    │                    │                      │    (abort 403 si   │                   │
    │                    │                      │    no es referente)│                   │
    │                    │                      │────────────────────>│                   │
    │                    │                      │                     │                   │
    │                    │                      │ 4. Cargar          │                   │
    │                    │                      │    facultades y    │                   │
    │                    │                      │    carreras        │                   │
    │                    │                      │───────────────────────────────────────>│
    │                    │                      │<───────────────────────────────────────│
    │                    │ 5. Renderizar        │                     │                   │
    │                    │    formulario        │                     │                   │
    │                    │<─────────────────────│                     │                   │
    │ 6. Completar       │                      │                     │                   │
    │    datos +         │                      │                     │                   │
    │    contactos       │                      │                     │                   │
    │───────────────────>│                      │                     │                   │
    │ 7. Click           │                      │                     │                   │
    │    "Guardar"       │                      │                     │                   │
    │───────────────────>│                      │                     │                   │
    │                    │ 8. POST /pacientes   │                     │                   │
    │                    │─────────────────────>│                     │                   │
    │                    │                      │ 9. Validar campos   │                   │
    │                    │                      │────────────────────>│                   │
    │                    │                      │<────────────────────│                   │
    │                    │                      │                     │                   │
    │                    │                      │ 10. DB::transaction │                   │
    │                    │                      │───────────────────────────────────────>│
    │                    │                      │ 11. INSERT paciente │                   │
    │                    │                      │    ┌────────────────┤                   │
    │                    │                      │    │ EncryptedField │                   │
    │                    │                      │    │ Cast cifra     │                   │
    │                    │                      │    │ cada campo PHI │                   │
    │                    │                      │    │ con _sym_key   │                   │
    │                    │                      │    │ (XSalsa20-     │                   │
    │                    │                      │    │  Poly1305)     │                   │
    │                    │                      │    └────────────────┤                   │
    │                    │                      │ 12. INSERT         │                   │
    │                    │                      │     contactos (×3) │                   │
    │                    │                      │    (cifrados)      │                   │
    │                    │                      │ 13. COMMIT         │                   │
    │                    │                      │<───────────────────────────────────────│
    │                    │                      │                     │                   │
    │                    │ 14. Redirect 302     │                     │                   │
    │                    │     /dashboard       │                     │                   │
    │                    │<─────────────────────│                     │                   │
    │ 15. Ver Dashboard  │                      │                     │                   │
    │     con paciente   │                      │                     │                   │
    │     en lista       │                      │                     │                   │
    │<───────────────────│                      │                     │                   │
```

---

### CU-09: Buscar Paciente por Carnet

**Ruta:** `/pacientes`
**Actor Principal:** Referente Psicosocial, Coordinador de Área, Especialista

**Precondiciones:** Usuario autenticado con rol clínico.

**Flujo Principal:**

1. El usuario navega a `/pacientes` desde el menú lateral.
2. El sistema muestra un campo de búsqueda para carnet estudiantil (ej. `CH17001`).
3. El usuario ingresa el carnet y presiona Enter.
4. El backend aplica **Rate Limiting** (30 req/min) y ejecuta la búsqueda según el rol:
   - **Referente:** coincidencia exacta + `creado_por_profesional_id = usuario actual`.
   - **Coordinador/Especialista:** coincidencia exacta + el paciente tiene expedientes en su área (`whereHas('expedientes')` con `AreaScope`).
5. Si encuentra el paciente, redirige a **Ver Expediente** (CU-10).
6. Si no, mensaje genérico: "No se encontró ningún paciente con ese carnet" (ambiguo para no revelar existencia en otras áreas).

**Flujo Alternativo:** Historial de búsquedas en `localStorage` (`pandora_carnet_history_{user.id}`). Formato inválido → error de validación.

**Postcondiciones:** Encontrado → redirige a CU-10. No encontrado → permanece en búsqueda.

```
Usuario            Vue Frontend           PacienteController        Middleware/        Modelo/DB
(Clínico)          (Index.vue)            (index)                   Políticas          (PostgreSQL)
    │                    │                      │                     │                   │
    │ 1. Navegar a       │                      │                     │                   │
    │    /pacientes      │                      │                     │                   │
    │───────────────────>│                      │                     │                   │
    │                    │ 2. GET /pacientes    │                     │                   │
    │                    │─────────────────────>│                     │                   │
    │                    │ 3. Renderizar campo  │                     │                   │
    │                    │    de búsqueda       │                     │                   │
    │                    │<─────────────────────│                     │                   │
    │ 4. Ingresar        │                      │                     │                   │
    │    carnet          │                      │                     │                   │
    │───────────────────>│                      │                     │                   │
    │ 5. Presionar Enter │                      │                     │                   │
    │───────────────────>│                      │                     │                   │
    │                    │ 6. GET /pacientes?   │                     │                   │
    │                    │    carnet=CH17001    │                     │                   │
    │                    │─────────────────────>│                     │                   │
    │                    │                      │ 7. Rate Limiting   │                   │
    │                    │                      │    (30 req/min)    │                   │
    │                    │                      │────────────────────>│                   │
    │                    │                      │                     │                   │
    │                    │                      │ 8. Buscar paciente │                   │
    │                    │                      │    ┌───────────────┤                   │
    │                    │                      │    │ Según rol:    │                   │
    │                    │                      │    │ Referente:    │                   │
    │                    │                      │    │ WHERE carnet  │                   │
    │                    │                      │    │ = 'CH17001'   │                   │
    │                    │                      │    │ AND creado_por│                   │
    │                    │                      │    │ = current_user│                   │
    │                    │                      │    │               │                   │
    │                    │                      │    │ Coord/Espec:  │                   │
    │                    │                      │    │ WHERE carnet  │                   │
    │                    │                      │    │ = 'CH17001'   │                   │
    │                    │                      │    │ AND EXISTS    │                   │
    │                    │                      │    │ (expedientes  │                   │
    │                    │                      │    │  WHERE area_id│                   │
    │                    │                      │    │  IN [áreas    │                   │
    │                    │                      │    │  del usuario])│                   │
    │                    │                      │    └───────────────┤                   │
    │                    │                      │───────────────────────────────────────>│
    │                    │                      │<───────────────────────────────────────│
    │                    │                      │                     │                   │
    │                    │                      │ 9. ¿Encontrado?    │                   │
    │                    │                      │                     │                   │
    │                    │ 10a. Redirect 302    │  ┌── SÍ ─────────┐  │                   │
    │                    │      /pacientes/     │  │ Redirect a   │  │                   │
    │                    │      {carnet}        │  │ expediente   │  │                   │
    │                    │<─────────────────────│  └──────────────┘  │                   │
    │                    │                      │                     │                   │
    │                    │ 10b. Error           │  ┌── NO ─────────┐  │                   │
    │                    │      genérico        │  │ "No se        │  │                   │
    │                    │<─────────────────────│  │ encontró..."  │  │                   │
    │                    │                      │  └──────────────┘  │                   │
```

---

### CU-10: Ver Expediente del Paciente

**Ruta:** `/pacientes/{carnet}`
**Actor Principal:** Referente Psicosocial, Coordinador de Área, Especialista
**Relación:** Extendido por CU-09 (Buscar por Carnet). Solo se accede tras búsqueda exitosa.

**Precondiciones:** Usuario ha encontrado un paciente mediante CU-09 y posee permisos.

**Flujo Principal:**

1. El usuario es redirigido a `/pacientes/{carnet}` (desde CU-09).
2. El backend (`PacienteController@show`) ejecuta:
   - **Rate Limiting:** 30 req/min.
   - **Política de acceso** (`PacientePolicy::view()`):
     - Referente: `creado_por_profesional_id` coincide.
     - Coordinador/Especialista: paciente tiene expedientes en su área.
   - Si deniega → HTTP 403/404.
3. Autorizado: carga el paciente con relaciones (`carrera`, `facultad`, `contactos`).
4. Los campos cifrados se **descifran en tiempo real**:
   - `EncryptedFieldCast` obtiene `_sym_key` de la sesión.
   - Descifra: nombre, dirección, fecha nacimiento, profesión, referido por, llevado por, motivo de consulta, contactos.
   - Si la clave de sesión no está disponible → `DecryptionException` → redirige al login.
5. Los datos se empaquetan con `PacienteResource` (filtra campos).
6. Renderiza vista con tres secciones:
   - **Datos Generales:** UUID, carnet, facultad, carrera, sexo, estado civil, nombre, dirección, fecha nacimiento, profesión, fecha primera consulta.
   - **Contactos:** tabla con nombre, parentesco, teléfonos, dirección, responsable principal.
   - **Información de Admisión:** referido por, llevado por, motivo de consulta.

**Flujo Alternativo:** Sin contactos → sección vacía. Error de descifrado → redirige al login.

**Postcondiciones:** Expediente completo visible con datos descifrados. Los datos nunca abandonan el servidor sin pasar por descifrado controlado por RBAC.

```
Usuario            Vue Frontend           PacienteController        Middleware/        Modelo/DB
(Clínico)          (Show.vue)             (show)                    Políticas          (PostgreSQL)
    │                    │                      │                     │                   │
    │                    │ 1. GET /pacientes/   │                     │                   │
    │                    │    {carnet}          │                     │                   │
    │                    │─────────────────────>│                     │                   │
    │                    │                      │ 2. Rate Limiting   │                   │
    │                    │                      │────────────────────>│                   │
    │                    │                      │                     │                   │
    │                    │                      │ 3. PacientePolicy  │                   │
    │                    │                      │    ::view()         │                   │
    │                    │                      │────────────────────>│                   │
    │                    │                      │  ┌── Referente ───┐│                   │
    │                    │                      │  │ creado_por == ││                   │
    │                    │                      │  │ current_user  ││                   │
    │                    │                      │  ├── Coord/Espec ─┤│                   │
    │                    │                      │  │ $paciente->   ││                   │
    │                    │                      │  │ expedientes() ││                   │
    │                    │                      │  │ ->exists()    ││                   │
    │                    │                      │  │ (AreaScope)   ││                   │
    │                    │                      │  └────────────────┘│                   │
    │                    │                      │<────────────────────│                   │
    │                    │                      │                     │                   │
    │                    │                      │ 4. Cargar paciente │                   │
    │                    │                      │    + relaciones    │                   │
    │                    │                      │───────────────────────────────────────>│
    │                    │                      │<───────────────────────────────────────│
    │                    │                      │                     │                   │
    │                    │                      │ 5. Descifrar       │                   │
    │                    │                      │    campos PHI      │                   │
    │                    │                      │    (EncryptedField │                   │
    │                    │                      │     Cast +         │                   │
    │                    │                      │     _sym_key)      │                   │
    │                    │                      │                     │                   │
    │                    │                      │ 6. PacienteResource│                   │
    │                    │                      │    (filtrar campos)│                   │
    │                    │                      │                     │                   │
    │                    │ 7. Renderizar        │                     │                   │
    │                    │    expediente        │                     │                   │
    │                    │    (datos            │                     │                   │
    │                    │     descifrados)     │                     │                   │
    │                    │<─────────────────────│                     │                   │
    │ 8. Visualizar     │                       │                     │                   │
    │    datos          │                       │                     │                   │
    │    completos      │                       │                     │                   │
    │<───────────────────│                       │                     │                   │
```

---

### CU-11: Búsqueda Segura por UUID

**Ruta:** `/busqueda-segura`
**Actor Principal:** Coordinador de Área, Referente Psicosocial, Especialista, Administrador de Sistema

**Precondiciones:** Usuario autenticado. Accesible por cualquier rol.

**Flujo Principal:**

1. El usuario navega a `/busqueda-segura` desde el menú de navegación.
2. Campo de búsqueda para UUID completo (`xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`).
3. Ingresa el UUID y pulsa "Buscar".
4. El backend (`SecureSearchController@search`) ejecuta:
   - **Validación de formato:** regex UUID v4.
   - **Búsqueda por coincidencia exacta** en `codigo` de `pacientes`.
   - **Filtrado RBAC:**
     - Referente: solo `creado_por_profesional_id` coincide.
     - Coordinador/Especialista: `whereHas('expedientes')` con `AreaScope`.
     - Administrador: sin restricción (solo obtiene UUID, no datos clínicos).
5. Si encuentra, muestra resultado **anonimizado**: solo el código UUID + botones "Ver Expediente" (CU-10) e "Historial de Citas".
6. Si pulsa "Ver Expediente", redirige a CU-10.
7. Si no encuentra, mensaje: "No se encontró ningún paciente con ese código".

**Flujo Alternativo:** UUID inválido → error de validación. Historial en `localStorage`.

**Postcondiciones:** Resultado anonimizado (solo UUID) o redirige al expediente si tiene permisos.

```
Usuario            Vue Frontend           SecureSearchController    Middleware/        Modelo/DB
                   (Index.vue)                                      Políticas          (PostgreSQL)
    │                    │                      │                     │                   │
    │ 1. Navegar a       │                      │                     │                   │
    │    /busqueda-      │                      │                     │                   │
    │    segura          │                      │                     │                   │
    │───────────────────>│                      │                     │                   │
    │                    │ 2. GET /busqueda-    │                     │                   │
    │                    │    segura            │                     │                   │
    │                    │─────────────────────>│                     │                   │
    │                    │ 3. Renderizar campo  │                     │                   │
    │                    │    búsqueda UUID     │                     │                   │
    │                    │<─────────────────────│                     │                   │
    │ 4. Ingresar UUID   │                      │                     │                   │
    │───────────────────>│                      │                     │                   │
    │ 5. Click "Buscar"  │                      │                     │                   │
    │───────────────────>│                      │                     │                   │
    │                    │ 6. POST /busqueda-   │                     │                   │
    │                    │    segura {uuid}     │                     │                   │
    │                    │─────────────────────>│                     │                   │
    │                    │                      │ 7. Validar formato │                   │
    │                    │                      │    UUID (regex)    │                   │
    │                    │                      │                     │                   │
    │                    │                      │ 8. Buscar UUID +   │                   │
    │                    │                      │    RBAC            │                   │
    │                    │                      │   ┌───────────────┐│                   │
    │                    │                      │   │ Según rol:    ││                   │
    │                    │                      │   │ WHERE codigo  ││                   │
    │                    │                      │   │ = UUID        ││                   │
    │                    │                      │   │ + filtro      ││                   │
    │                    │                      │   │ según RBAC    ││                   │
    │                    │                      │   └───────────────┘│                   │
    │                    │                      │───────────────────────────────────────>│
    │                    │                      │<───────────────────────────────────────│
    │                    │                      │                     │                   │
    │                    │                      │ 9. Evaluar         │                   │
    │                    │                      │                     │                   │
    │                    │ 10a. UUID encontrado │  ┌── SÍ ─────────┐  │                   │
    │                    │      + botones       │  │ Mostrar UUID  │  │                   │
    │                    │<─────────────────────│  │ [Ver          │  │                   │
    │                    │                      │  │  Expediente]  │  │                   │
    │                    │                      │  │ [Historial]   │  │                   │
    │                    │                      │  └──────────────┘  │                   │
    │                    │                      │                     │                   │
    │                    │ 10b. No encontrado   │  ┌── NO ─────────┐  │                   │
    │                    │<─────────────────────│  │ "No se        │  │                   │
    │                    │                      │  │ encontró..."  │  │                   │
    │                    │                      │  └──────────────┘  │                   │
    │ 11. Ver resultado  │                      │                     │                   │
    │     (solo UUID)    │                      │                     │                   │
    │<───────────────────│                      │                     │                   │
    │                    │                      │                     │                   │
    │ (Si pulsa "Ver     │                      │                     │                   │
    │  Expediente" →     │                      │                     │                   │
    │  mismo flujo CU-10)│                      │                     │                   │
```

---

## 4. Matriz Actor vs Caso de Uso

| Caso de Uso | sysadmin | area_coordinator | psychosocial_referent | specialist |
|---|---|---|---|---|
| CU-01 Ver Dashboard Clínico | — | ✅ | ✅ | ✅ |
| CU-02 Ver Dashboard Administrativo | ✅ | — | — | — |
| CU-03 Gestionar Usuarios | ✅ | — | — | — |
| CU-04 Crear Usuario | ✅ | — | — | — |
| CU-05 Editar Usuario | ✅ | — | — | — |
| CU-06 Auditar Pacientes | ✅ | — | — | — |
| CU-07 Ver Organigrama | ✅ | — | — | — |
| CU-08 Registrar Paciente | — | — | ✅ | — |
| CU-09 Buscar por Carnet | — | ✅ | ✅ | ✅ |
| CU-10 Ver Expediente | — | ✅ | ✅ | ✅ |
| CU-11 Búsqueda Segura UUID | ✅ | ✅ | ✅ | ✅ |

> ✅ = Autorizado | — = Sin acceso (middleware/policies bloquean con 403 o 404)

---

## 5. Relaciones entre Casos de Uso

Todas las relaciones son de tipo **extend**: el caso base puede ejecutarse sin el extendido.

| # | Caso Base | Relación | Caso que Extiende | Condición |
|---|---|---|---|---|
| R1 | CU-03 Gestionar Usuarios | ← extend | CU-04 Crear Usuario | Admin pulsa "Nuevo Usuario" |
| R2 | CU-03 Gestionar Usuarios | ← extend | CU-05 Editar Usuario | Admin pulsa "Editar" |
| R3 | CU-10 Ver Expediente | ← extend | CU-09 Buscar por Carnet | Búsqueda encuentra paciente |
| R4 | CU-10 Ver Expediente | ← extend | CU-11 Búsqueda Segura UUID | Usuario pulsa "Ver Expediente" |

**Casos de Uso Independientes:** CU-01, CU-02, CU-06, CU-07, CU-08.

---

## 6. Observaciones Finales

1. **Separación administrador/datos clínicos:** El sysadmin tiene máximo privilegio administrativo pero cero acceso a datos clínicos (principio de mínimo privilegio OWASP ASVS V4.1.1).
2. **Cifrado transparente:** En CU-08 (Registrar) y CU-10 (Ver Expediente), el `EncryptedFieldCast` cifra/descifra automáticamente. La `_sym_key` se deriva de la contraseña vía Argon2id y reside solo en sesión del servidor.
3. **Defensa en profundidad:** Cada CU protegido por: (a) middleware de ruta, (b) Global Scope (`AreaScope`), (c) Policies de Laravel.
4. **Once casos de uso** correspondientes a cada pantalla funcional implementada en el Sprint 1.
