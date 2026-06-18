# Roadmap Sprint 1: Cimientos, Seguridad y Privacidad
**Versión:** 2.1  
**Estado:** Revisado — Listo para equipo  
**Última revisión:** Mayo 2026  
**Cambios respecto a v2.0:** Ver [Registro de Cambios v2.1](#registro-de-cambios-v21)

---

## Índice
1. [Registro de Cambios v2.1](#registro-de-cambios-v21)
2. [Glosario de Dominio](#1-glosario-de-dominio)
3. [Stack Técnico y Versiones](#2-stack-técnico-y-versiones)
4. [Estructura de Carpetas](#3-estructura-de-carpetas)
5. [Requisitos Técnicos Transversales](#4-requisitos-técnicos-transversales)
6. [Mapa de Dependencias entre HUs](#5-mapa-de-dependencias-entre-hus)
7. [Historias de Usuario](#6-historias-de-usuario)
   - HU-01: Autenticación y Criptografía Base
   - HU-02: Cierre de Sesión
   - HU-03: Roles y Segmentación Estricta (RBAC)
   - HU-04: Código de Privacidad y Anonimización
   - HU-05: Registro de Pacientes (Cifrado de Datos)
   - HU-06: Búsqueda Segura por Código
8. [Bases para Sprints Futuros](#7-bases-para-sprints-futuros)
9. [Fuera de Alcance (Non-Goals)](#8-fuera-de-alcance-non-goals)
10. [Estrategia de Testing](#9-estrategia-de-testing)
11. [Gestión de Secretos y Variables de Entorno](#10-gestión-de-secretos-y-variables-de-entorno)

---

## Registro de Cambios v2.1

> Esta sección documenta todas las correcciones respecto a v2.0. Cada cambio tiene un ID `C-XX` referenciable.

| ID | Sección | Tipo | Descripción |
|---|---|---|---|
| C-01 | HU-05 | 🔴 Bug | Colisión de variable `$key` en `EncryptedFieldCast` corregida → renombrada a `$encKey` |
| C-02 | HU-01, HU-05 | 🔴 Bug | Nomenclatura algorítmica corregida: `sodium_crypto_secretbox` implementa XSalsa20-Poly1305, no AES-256-GCM |
| C-03 | HU-01 | ✅ Hecho | Bloqueo de cuenta: 3 intentos fallidos → bloqueo 15 min |
| C-04 | HU-03 | 🔴 Bug | `AreaScope` corregido para soportar múltiples áreas por especialista (`whereIn` en lugar de `where`) |
| C-05 | HU-05 | 🔴 Bug | `DecryptionException` definida explícitamente con namespace y ubicación |
| C-06 | HU-04 | 🔴 Bug | `encodeBase32Crockford()` implementado completamente |
| C-07 | HU-05 | 🟡 Diseño | `EncryptedFieldCast` desacoplado de `session()` mediante `EncryptionContextService` inyectable |
| C-08 | §5 | 🟡 Diseño | Dependencia circular HU-04/HU-05 documentada y corregida en el mapa |
| C-09 | §7 | 🟡 Diseño | Flaw arquitectónica crítica de cifrado multi-especialista documentada como deuda prioritaria para Sprint 2 |
| C-10 | §7 | 🟡 Diseño | `AuditableController` trait propuesto para enforcement del audit `read` |
| C-11 | §3 | 🔵 Mejora | `Http/Requests/` y `Exceptions/` añadidas a la estructura de carpetas |
| C-12 | §4 | 🔵 Mejora | Hash de contraseñas especificado como Argon2id para consistencia con el KDF |
| C-13 | §7 | 🔵 Mejora | Health checks añadidos al docker-compose |
| C-14 | §10 | 🔵 Mejora | `PGPASSWORD` en contenedor de backup reemplazado por `.pgpass` (permisos 600) |
| C-15 | §9 | 🔵 Mejora | Aclaración sobre incompatibilidad SQLite/PostgreSQL para tipos específicos |
| C-16 | Migraciones | 🔵 Mejora | Nombres de migraciones corregidos al estándar de timestamp de Laravel |

---

## 1. Glosario de Dominio

> **Propósito para agentes IA:** Este glosario es la fuente de verdad para nombres de modelos, tablas y variables. No inventar sinónimos.

| Término | Definición | Modelo Eloquent | Tabla en DB |
|---|---|---|---|
| **Especialista** | Usuario autenticado del sistema (psicólogo, médico, fisioterapeuta, etc.). Nunca llamar "usuario" ni "doctor" en el código. | `Especialista` | `especialistas` |
| **Paciente** | Persona que recibe atención. Sus datos personales están cifrados en reposo. | `Paciente` | `pacientes` |
| **Expediente** | Registro clínico que vincula un Paciente con un Área. Es la unidad de acceso controlada por RBAC. | `Expediente` | `expedientes` |
| **Área** | Especialidad clínica (Psicología, Medicina General, Fisioterapia, Nutrición). Define el scope de acceso. | `Area` | `areas` |
| **Código de Privacidad** | Identificador legible y anónimo del paciente (`PND-XXXXX`). Es la única referencia pública. | — | columna `codigo_privacidad` en `pacientes` |
| **Clave Simétrica de Sesión** | Clave XSalsa20 de 32 bytes derivada de la contraseña del Especialista al momento del login. Vive solo en sesión de servidor, nunca en BD. | — | `$_SESSION['_sym_key']` |
| **Blind Index** | Hash determinista de un campo cifrado que permite búsquedas sin descifrar. | — | columna `codigo_privacidad_hash` en `pacientes` |
| **Audit Log** | Registro inmutable de cada acción sobre datos sensibles. | `AuditLog` | `audit_logs` |
| **EncryptionContextService** | *[C-07]* Servicio inyectable que abstrae el acceso a `_sym_key` desde sesión. Permite testear `EncryptedFieldCast` en aislamiento. | `EncryptionContextService` | — |

---

## 2. Stack Técnico y Versiones

> **Obligatorio:** Usar exactamente estas versiones. No actualizar dependencias sin aprobación del Tech Lead.

| Componente | Versión | Notas |
|---|---|---|
| PHP | `^8.3` | Requerido para `sodium` nativo y `Fibers` |
| Laravel | `^11.x` | No usar Laravel 12 hasta Sprint 3 |
| Inertia.js (server) | `^2.x` | `inertiajs/inertia-laravel` |
| Inertia.js (client) | `^2.x` | Con Vue 3 o React 18 (definir antes del sprint) |
| PostgreSQL | `^16.x` | Extensión `pgcrypto` habilitada |
| Laravel Sanctum | `^4.x` | Modo SPA únicamente |
| libsodium | Nativa PHP | `ext-sodium` — verificar con `php -m \| grep sodium` |
| Docker Engine | `^26.x` | |
| Docker Compose | `^2.x` (plugin) | No usar `docker-compose` legacy |

### Dependencias Composer obligatorias

```json
{
    "require": {
        "laravel/sanctum": "^4.0",
        "owen-it/laravel-auditing": "^13.0"
    },
    "require-dev": {
        "pestphp/pest": "^3.0",
        "pestphp/pest-plugin-laravel": "^3.0"
    }
}
```

---

## 3. Estructura de Carpetas

> **Propósito para agentes IA:** Toda pieza de código nueva debe respetar esta estructura. No crear carpetas fuera de este esquema sin justificación.

```
app/
├── Console/
│   └── Commands/
│       └── BackupDatabase.php              # HU: Respaldos
├── Exceptions/
│   └── DecryptionException.php             # C-05: Definida en HU-05
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   ├── LoginController.php         # HU-01
│   │   │   └── LogoutController.php        # HU-02
│   │   ├── ExpedienteController.php        # HU-03, HU-05
│   │   └── PacienteController.php          # HU-04, HU-05, HU-06
│   ├── Middleware/
│   │   ├── EnforceAreaScope.php            # HU-03
│   │   └── ValidateSymmetricKey.php        # HU-01, HU-05
│   └── Requests/                           # C-11: Form Requests obligatorios
│       ├── Auth/
│       │   └── LoginRequest.php            # HU-01
│       ├── PacienteStoreRequest.php        # HU-05
│       └── PacienteBuscarRequest.php       # HU-06
├── Models/
│   ├── Casts/
│   │   └── EncryptedFieldCast.php          # HU-05
│   ├── Scopes/
│   │   └── AreaScope.php                   # HU-03
│   ├── Observers/
│   │   ├── ExpedienteObserver.php          # Audit Trail
│   │   └── PacienteObserver.php            # Audit Trail
│   ├── Area.php
│   ├── AuditLog.php
│   ├── Especialista.php
│   ├── Expediente.php
│   └── Paciente.php
├── Services/
│   ├── Crypto/
│   │   ├── KeyDerivationService.php        # HU-01 — KDF Argon2id
│   │   ├── EncryptionContextService.php    # C-07 — Abstrae acceso a _sym_key
│   │   ├── FieldEncryptionService.php      # HU-05 — XSalsa20-Poly1305
│   │   └── BlindIndexService.php           # HU-06 — HMAC-SHA256
│   └── Privacy/
│       └── CodigoPrivacidadService.php     # HU-04 — Generación PND
├── Traits/
│   └── AuditableController.php             # C-10 — Enforcement de audit 'read'
database/
├── migrations/
│   ├── 2026_01_01_000001_create_areas_table.php            # C-16
│   ├── 2026_01_01_000002_create_especialistas_table.php
│   ├── 2026_01_01_000003_create_roles_table.php
│   ├── 2026_01_01_000004_create_pacientes_table.php
│   ├── 2026_01_01_000005_create_expedientes_table.php
│   └── 2026_01_01_000006_create_audit_logs_table.php
└── seeders/
    ├── AreasSeeder.php
    └── RolesSeeder.php
```

> **C-16 — Migraciones con timestamp:** Laravel espera el formato `YYYY_MM_DD_HHMMSS_nombre.php`. Las migraciones numeradas (`0001_...`) son reconocidas pero no siguen la convención estándar, lo que rompe `php artisan migrate:status` e impide insertar migraciones entre existentes sin renumerar. Usar el formato de fecha con segundos incrementales garantiza compatibilidad a largo plazo.

---

## 4. Requisitos Técnicos Transversales

### Arquitectura Base
- **Framework:** Laravel `^11.x` + Inertia.js `^2.x`
- **Renderizado:** SPA con Inertia. Sin API REST pura. Sin endpoints JSON independientes de Inertia salvo los de autenticación.

### Autenticación
- Basada en **token JWT** (`firebase/php-jwt`) con expiración de **8 horas** (480 minutos).
- El token JWT se almacena en cookie `pandora_token` con `HttpOnly`, `SameSite=Strict`, `Secure=true` en producción.
- El middleware `AuthenticateJwt` valida el token JWT en cada petición a rutas protegidas.
- Para el acto de login se usa `Auth::attempt()` con el guard `session`; tras autenticación exitosa se emite el JWT.

### Base de Datos
- **PostgreSQL `^16.x`** con extensión `pgcrypto` habilitada.
- **Soft-Deletes obligatorio** en todos los modelos que representen datos clínicos (`Paciente`, `Expediente`).
- Todos los IDs públicos son **UUID v4** (`uuid_generate_v4()`). IDs internos de autoincremento solo para relaciones internas no expuestas.
- La conexión de la aplicación a PostgreSQL debe usar **SSL/TLS** (`sslmode=require` en la cadena de conexión DSN). Configurar en `config/database.php`:
  ```php
  'pgsql' => [
      // ...
      'sslmode' => env('DB_SSLMODE', 'require'),
  ],
  ```

### Hash de Contraseñas — C-12

> **Cambio respecto a v2.0:** Laravel usa bcrypt por defecto. Se especifica explícitamente Argon2id para consistencia con el KDF.

Configurar en `config/hashing.php`:
```php
'driver' => 'argon2id',

'argon' => [
    'memory'  => 65536, // 64 MB
    'threads' => 1,
    'time'    => 4,
],
```

Esto aplica al hash almacenado en `especialistas.password`. Es independiente del KDF de la clave simétrica (ambos usan Argon2id pero con propósitos distintos).

### Seguridad HTTP
Los siguientes headers deben configurarse en el middleware global de Laravel o en la configuración de Nginx/Caddy:

```
Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

### Política de Contraseñas
- Mínimo **14 caracteres**.
- Debe contener al menos: 1 mayúscula, 1 número, 1 símbolo.
- Verificar contra la API de **HaveIBeenPwned** (k-anonymity) al momento del registro y cambio de contraseña.
- Implementar con el Rule `Password::defaults()` de Laravel extendido.

### Infraestructura
- Contenedores Docker con **volumen cifrado independiente** para respaldos.
- Respaldos gestionados vía `pg_dump` y Cron dentro del contenedor `backup`.
- Ver sección [Gestión de Secretos](#10-gestión-de-secretos-y-variables-de-entorno).

---

## 5. Mapa de Dependencias entre HUs

> **Para agentes IA:** Una HU no puede iniciarse hasta que sus dependencias estén en estado `DONE`.

```
HU-01 (Auth + KDF)
  └─── HU-02 (Logout)                      [depende de: HU-01]
  └─── HU-03 (RBAC + Scopes)               [depende de: HU-01]
        └─── HU-05 (Cifrado de Registros)  [depende de: HU-01, HU-03]
              ├─── HU-04 (Código PND)      [implementado dentro de HU-05 — ver nota C-08]
              └─── HU-06 (Búsqueda)        [depende de: HU-05]

Audit Trail                                [depende de: HU-03, HU-05]
Respaldos                                  [sin dependencias — paralelo]
```

> **C-08 — Dependencia HU-04/HU-05 corregida:** La v2.0 definía "HU-04 depende de HU-05", pero HU-05 (registro de pacientes) necesita `CodigoPrivacidadService` para generar el PND en el momento de creación del paciente. Son mutuamente dependientes en el orden original. La corrección: `CodigoPrivacidadService` **se implementa técnicamente dentro del alcance de HU-05**, y HU-04 es la historia que define la *política*, el *formato* y los *criterios* del código PND. HU-04 no es un prerrequisito técnico de compilación; es un prerrequisito de especificación.

**Orden de implementación sugerido:**
`HU-01 → HU-02 → HU-03 → HU-05 (incluye CodigoPrivacidadService) → HU-04 (valida criterios PND) → HU-06 → Audit Trail`

---

## 6. Historias de Usuario

---

### HU-01: Autenticación y Criptografía Base

**Objetivo:** Ingreso seguro de Especialistas sin exponer vectores XSS ni filtrar material criptográfico.

**Dependencias:** Ninguna (es la base del sprint).

#### Qué se necesita

**a) Autenticación por sesión nativa de Laravel**
- Form Request: `LoginRequest` con validación de `email` y `password`.
- Endpoint `POST /login` con validación de credenciales contra la tabla `especialistas`.
- Redirigir via Inertia a `/dashboard` en éxito.
- Configurar en `config/session.php`:
  ```php
  'secure'          => true,
  'http_only'       => true,
  'same_site'       => 'strict',
  'expire_on_close' => false,
  'lifetime'        => 120, // minutos — ver política de sesión
  ```

**b) Derivación de Clave Simétrica — Argon2id (OBLIGATORIO)**

La clave de 32 bytes se deriva de la contraseña en texto plano capturada **solo durante el login**, usando `sodium_crypto_pwhash`:

```php
// app/Services/Crypto/KeyDerivationService.php
public function derive(string $password, string $salt): string
{
    return sodium_crypto_pwhash(
        length:    SODIUM_CRYPTO_SECRETBOX_KEYBYTES, // 32 bytes
        password:  $password,
        salt:      $salt,                            // 16 bytes — almacenado en especialistas.kdf_salt
        opslimit:  SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
        memlimit:  SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
        alg:       SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
    );
}
```

- La sal `kdf_salt` es única por Especialista, generada en el registro con `random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES)` y almacenada en `especialistas` en Base64.
- La clave derivada resultante se almacena **únicamente en sesión de servidor** bajo `_sym_key` (codificada en Base64).
- La contraseña en texto plano **nunca se persiste ni se loguea**. Limpiar con `sodium_memzero()` después de la derivación.

**c) Token JWT**
- Autenticación mediante **token JWT** (`firebase/php-jwt`) con algoritmo HS256.
- Expiración: **8 horas** (480 minutos) desde la emisión.
- El token se almacena en cookie `pandora_token` con `HttpOnly`, `SameSite=Strict`, `Secure=true` en producción.
- La clave de firma del JWT es `APP_KEY` (sin prefijo `base64:`).

**d) Bloqueo de Cuenta — Protección contra fuerza bruta — C-03**

- Máximo **3 intentos fallidos** consecutivos por cuenta.
- Al superar el límite: bloqueo de **15 minutos** (columna `locked_until` en `users`).
- El contador de intentos fallidos se resetea tras un login exitoso.
- Rate limiting adicional en la ruta `POST /login`: 3 intentos por IP+email en ventana de 10 minutos.

#### Criterios de Aceptación

- [x] `POST /login` con credenciales válidas emite cookie JWT `pandora_token` (exp. 8h) y redirige al dashboard.
- [x] `POST /login` con credenciales inválidas retorna error genérico (no revelar si el email existe o no).
- [x] Después del login, `session()->get('_sym_key')` contiene la clave derivada en Base64.
- [x] La clave derivada tiene exactamente 32 bytes al decodificar.
- [x] Tras 3 intentos fallidos, la cuenta se bloquea por 15 minutos.
- [x] El bloqueo se levanta automáticamente a los 15 minutos.
- [x] Login exitoso resetea el contador de intentos fallidos.
- [x] Test feature cubre: login exitoso, cookie JWT, credenciales inválidas, bloqueo de cuenta.

---

### HU-02: Cierre de Sesión

**Objetivo:** Destrucción completa e irrecuperable del estado de autenticación y material criptográfico.

**Dependencias:** HU-01.

#### Qué se necesita

- Endpoint `POST /logout` (método POST para requerir CSRF token, nunca GET).
- Secuencia de destrucción obligatoria:
  ```php
  // 1. Borrar la clave simétrica antes de invalidar la sesión
  session()->forget('_sym_key');

  // 2. Invalidar la sesión completa
  Session::invalidate();

  // 3. Regenerar el token CSRF
  Session::regenerateToken();

  // 4. Responder
  return redirect('/login');
  ```
- El orden es estricto: primero borrar `_sym_key`, luego invalidar. Así se garantiza que incluso si `invalidate()` falla, la clave no queda en sesión.

#### Criterios de Aceptación

- [x] `POST /logout` retorna redirección a `/login`.
- [x] Tras el logout, cualquier request autenticado retorna `HTTP 401` o redirección a `/login`.
- [x] `session()->has('_sym_key')` retorna `false` tras el logout.
- [x] La cookie de sesión anterior no sirve para re-autenticar (el ID de sesión fue regenerado).
- [x] Test feature cubre: logout exitoso, request post-logout rechazado.

---

### HU-03: Roles y Segmentación Estricta (RBAC)

**Objetivo:** Aislar los expedientes entre áreas. Un Especialista de Psicología nunca puede acceder a expedientes de Medicina, aunque conozca el UUID.

**Dependencias:** HU-01.

#### Jerarquía de Roles (Definición Canónica)

| Rol | Slug | Nivel | Descripción |
|---|---|---|---|
| Administrador de Sistema | `sysadmin` | 100 | Acceso a configuración del sistema. Sin acceso a expedientes clínicos. |
| Coordinador de Área | `area_coordinator` | 50 | Acceso de lectura a todos los expedientes de su área. Sin escritura. |
| Especialista | `specialist` | 10 | Acceso de lectura y escritura solo a los expedientes de su área. |

> **Regla crítica:** El rol `sysadmin` tiene acceso cero a datos clínicos. El acceso administrativo y el acceso clínico son mutuamente excluyentes por diseño.

#### Estructura de Tablas (Esquema Real)

El sistema utiliza las tablas existentes y la tabla pivote de profesionales para la gestión de acceso, en lugar de una tabla combinada genérica:

- **`users`**: Tabla principal de especialistas.
- **`roles`**: Contiene `id` (BIGINT), `nombre`, `slug` y `nivel`.
- **`role_user`**: Tabla pivote global que asigna un rol a un usuario (`user_id`, `role_id`). Los roles son globales para el usuario.
- **`profesionales`**: Relaciona a un `user_id` con un `area_id`. Permite que un usuario pertenezca a múltiples áreas de atención.

#### Relación `areas()` y `roles()` en el Modelo — C-04

Para que el scope pueda consultar todas las áreas autorizadas de un especialista, el modelo debe exponer esta relación a través de la tabla `profesionales` y sus roles a través de `role_user`:

```php
// app/Models/Especialista.php
public function areas(): BelongsToMany
{
    return $this->belongsToMany(Area::class, 'profesionales', 'user_id', 'area_id');
}

public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
}

public function hasRole(string $slug): bool
{
    return $this->roles()->where('slug', $slug)->exists();
}
```

#### Global Scope en Eloquent — C-04

> **Cambio respecto a v2.0:** La v2.0 usaba `$especialista->area_id` (escalar), pero la tabla `especialista_rol_area` permite múltiples áreas por especialista. Un Coordinador en Psicología + Especialista en Fisioterapia no veía expedientes con el scope anterior.

```php
// app/Models/Scopes/AreaScope.php
class AreaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $especialista = Auth::user();
        if ($especialista && !$especialista->hasRole('sysadmin')) {
            // whereIn para cubrir todos los áreas autorizados del especialista
            $areaIds = $especialista->areas()->pluck('areas.id');
            $builder->whereIn('area_id', $areaIds);
        }
    }
}
```

Aplicar el scope en `Expediente::booted()`:
```php
protected static function booted(): void
{
    static::addGlobalScope(new AreaScope());
}
```

#### Middleware de Ruta

```php
// app/Http/Middleware/EnforceAreaScope.php
// Bloquea el acceso a UUIDs de expedientes fuera del área del especialista.
// Se aplica DESPUÉS del Global Scope como segunda línea de defensa.
```

Registrar en `bootstrap/app.php` y aplicar al grupo de rutas `expedientes.*`.

#### Criterios de Aceptación

- [x] Un Especialista de Área A que solicita el UUID de un expediente de Área B recibe `HTTP 403` (o 404 por el scope).
- [x] El Global Scope está activo en todas las consultas de `Expediente` y no puede desactivarse desde una request HTTP.
- [x] El rol `sysadmin` no puede acceder a ninguna ruta de expedientes clínicos.
- [x] Un especialista con roles en múltiples áreas puede ver expedientes de **todas** sus áreas autorizadas.
- [x] Tests cubren: acceso a propio scope (éxito), acceso fuera de scope (403/404), acceso de sysadmin a ruta clínica (403), especialista multi-área (éxito en ambas).

---

### HU-04: Código de Privacidad y Anonimización

**Objetivo:** Generación del código `PND-XXXXX` único, legible y sin colisiones bajo concurrencia.

**Dependencias:** Implementado dentro del alcance técnico de HU-05. Ver nota C-08.

#### Formato Canónico del Código

```
PND-XXXXX

Donde:
  PND  = Prefijo fijo, literal, no parametrizable
  -    = Guion separador
  XXXXX = 5 caracteres alfanuméricos en Base32 Crockford (0-9, A-Z sin I, L, O, U)
          Ejemplo válido:   PND-A3K7M
          Ejemplo inválido: PND-A3K7I  (contiene I, excluida en Crockford)

Espacio total de valores: 32^5 = 33.554.432 códigos únicos posibles.
```

#### Implementación Completa — C-06

```php
// app/Services/Privacy/CodigoPrivacidadService.php

/**
 * Genera un código PND derivado del UUID del paciente.
 * La derivación es determinista: el mismo UUID siempre produce el mismo código.
 * Esto permite regenerar el código si se pierde, pero no predecirlo sin conocer el UUID.
 */
public function generar(string $uuid): string
{
    $hash    = hash('sha256', $uuid, raw: true);
    $numero  = unpack('N', substr($hash, 0, 4))[1]; // uint32 big-endian
    $codigo  = $this->encodeBase32Crockford($numero % (32 ** 5));
    return 'PND-' . str_pad($codigo, 5, '0', STR_PAD_LEFT);
}

/**
 * Codifica un entero en Base32 Crockford.
 * Alfabeto: 0-9, A-Z excluyendo I, L, O, U (evita confusión visual).
 */
private function encodeBase32Crockford(int $number): string
{
    $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; // 32 caracteres
    $result   = '';
    do {
        $result = $alphabet[$number % 32] . $result;
        $number = intdiv($number, 32);
    } while ($number > 0);
    return $result;
}
```

> **Nota sobre sesgo de módulo:** `unpack('N', ...)` produce un entero en `[0, 2^32)`. El espacio objetivo es `32^5 = 33.554.432`. Como `2^32` no es divisible exactamente por `32^5`, existe un sesgo estadístico de ~0,78% en los primeros valores del espacio. Para un código de privacidad (no una clave criptográfica), este sesgo es aceptable. Si se requiere distribución perfectamente uniforme en iteraciones futuras, aplicar rejection sampling usando los bytes sucesivos del hash SHA-256.

#### Manejo de Colisiones

```php
// En PacienteController o PacienteService:
DB::transaction(function () use ($datos) {
    $maxReintentos = 5;
    for ($intento = 0; $intento < $maxReintentos; $intento++) {
        try {
            $uuid    = (string) Str::uuid();
            $codigo  = $this->codigoService->generar($uuid);
            $paciente = Paciente::create([
                'id'                => $uuid,
                'codigo_privacidad' => $codigo,
                // ... otros campos cifrados
            ]);
            return $paciente;
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') { // Unique Violation PostgreSQL
                Log::warning("Colisión en código PND, reintento $intento");
                continue;
            }
            throw $e;
        }
    }
    throw new RuntimeException('No se pudo generar código PND único tras 5 intentos.');
});
```

#### Criterios de Aceptación

- [ ] Todos los códigos generados siguen el formato `PND-[0-9A-HJ-KM-NP-TV-Z]{5}`.
- [ ] La columna `codigo_privacidad` en `pacientes` tiene constraint `UNIQUE`.
- [ ] En un test de concurrencia con 100 inserciones simultáneas, no se producen duplicados ni errores no capturados.
- [ ] Si se superan 5 reintentos, se lanza excepción controlada y se retorna `HTTP 500` con log.

---

### HU-05: Registro de Pacientes (Cifrado de Datos)

**Objetivo:** Los datos sociodemográficos y clínicos del Paciente son ilegibles sin la clave del Especialista autenticado. Un DBA con acceso directo a PostgreSQL no puede leer los datos.

**Dependencias:** HU-01 (clave simétrica en sesión), HU-03 (area_id del scope).

#### Campos Cifrados (lista canónica)

| Campo | Tabla | Tipo en DB |
|---|---|---|
| `nombre_completo` | `pacientes` | `TEXT` (cifrado) |
| `fecha_nacimiento` | `pacientes` | `TEXT` (cifrado, formato ISO 8601) |
| `motivo_consulta` | `expedientes` | `TEXT` (cifrado) |
| `notas_clinicas` | `expedientes` | `TEXT` (cifrado) |
| `diagnostico` | `expedientes` | `TEXT` (cifrado) |

> Campos **no cifrados** (usados en queries): `id` (UUID), `codigo_privacidad`, `codigo_privacidad_hash`, `area_id`, `created_at`, `deleted_at`.

#### Definición de `DecryptionException` — C-05

```php
// app/Exceptions/DecryptionException.php
namespace App\Exceptions;

use RuntimeException;

/**
 * Lanzada cuando un campo cifrado no puede descifrarse.
 * Causas comunes: sesión expirada, clave incorrecta, datos corruptos.
 */
final class DecryptionException extends RuntimeException {}
```

#### `EncryptionContextService` — C-07

```php
// app/Services/Crypto/EncryptionContextService.php
namespace App\Services\Crypto;

use App\Exceptions\DecryptionException;

/**
 * Abstrae el acceso a la clave simétrica de sesión.
 * Permite que EncryptedFieldCast sea testeable en aislamiento
 * mockeando este servicio en lugar de acceder a session() directamente.
 */
class EncryptionContextService
{
    /**
     * Retorna la clave simétrica de 32 bytes desde sesión.
     *
     * @throws DecryptionException Si la clave no está disponible (sesión expirada o no autenticado).
     */
    public function getKey(): string
    {
        if (!session()->has('_sym_key')) {
            throw new DecryptionException(
                'Clave de cifrado no disponible. La sesión expiró o el usuario no está autenticado.'
            );
        }
        $decoded = base64_decode(session('_sym_key'), strict: true);
        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new DecryptionException('La clave de sesión está malformada o tiene longitud incorrecta.');
        }
        return $decoded;
    }
}
```

#### Implementación del Cast — C-01, C-02, C-07

> **Cambios respecto a v2.0:**
> - **C-01:** Variable `$key` renombrada a `$encKey` para no colisionar con el parámetro `$key` del contrato `CastsAttributes` (que representa el nombre de la columna).
> - **C-02:** El algoritmo es **XSalsa20-Poly1305** (vía `sodium_crypto_secretbox`), no AES-256-GCM. XSalsa20-Poly1305 es la opción recomendada por libsodium porque es tiempo-constante sin requerir instrucciones hardware AES, lo que lo hace más seguro en entornos virtualizados.
> - **C-07:** El cast ya no llama a `session()` directamente; delega en `EncryptionContextService` inyectable.

```php
// app/Models/Casts/EncryptedFieldCast.php
namespace App\Models\Casts;

use App\Exceptions\DecryptionException;
use App\Services\Crypto\EncryptionContextService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class EncryptedFieldCast implements CastsAttributes
{
    public function get($model, string $key, $value, $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }
        $encKey = app(EncryptionContextService::class)->getKey();
        return $this->decryptField($value, $encKey);
    }

    public function set($model, string $key, $value, $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }
        $encKey = app(EncryptionContextService::class)->getKey();
        return $this->encryptField($value, $encKey);
    }

    private function encryptField(string $plaintext, string $encKey): string
    {
        $nonce      = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $encKey);
        return base64_encode($nonce . $ciphertext);
    }

    private function decryptField(string $encoded, string $encKey): string
    {
        $decoded = base64_decode($encoded, strict: true);
        if ($decoded === false) {
            throw new DecryptionException("El valor cifrado del campo está malformado (base64 inválido).");
        }
        $nonce      = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext  = sodium_crypto_secretbox_open($ciphertext, $nonce, $encKey);
        if ($plaintext === false) {
            throw new DecryptionException("No se pudo descifrar el campo. Posible corrupción o clave incorrecta.");
        }
        return $plaintext;
    }
}
```

Aplicar en el modelo:
```php
// app/Models/Paciente.php
protected $casts = [
    'nombre_completo'  => EncryptedFieldCast::class,
    'fecha_nacimiento' => EncryptedFieldCast::class,
];
```

#### Manejo de `DecryptionException` en el Handler

Registrar en `bootstrap/app.php` para retornar `HTTP 401` cuando la sesión expire durante una operación:

```php
$exceptions->render(function (DecryptionException $e, Request $request) {
    if ($request->wantsJson()) {
        return response()->json(['message' => 'Sesión expirada. Por favor, inicie sesión nuevamente.'], 401);
    }
    return redirect()->route('login')->withErrors(['session' => 'Su sesión expiró durante la operación.']);
});
```

#### Consideración de Performance

El cifrado XSalsa20-Poly1305 (vía `sodium_crypto_secretbox`) tiene un costo despreciable para registros individuales (< 1ms por campo). Sin embargo, las consultas de tipo listado (ej. paginación de expedientes) **no deben mostrar campos cifrados en la vista de lista**: usar solo `codigo_privacidad` e `id`. Los campos cifrados solo se descifran en la vista de detalle de un expediente individual.

#### Rotación de Clave ante Cambio de Contraseña

> ⚠️ **Deuda técnica documentada para Sprint 3.**
>
> Si un Especialista cambia su contraseña, la clave derivada cambia, haciendo ilegibles todos sus registros. La solución (re-cifrado de todos los registros con la nueva clave) debe ser implementada en Sprint 3 como un Job asíncrono. Hasta entonces, el cambio de contraseña está **deshabilitado** y la UI debe reflejar esta limitación.

#### Criterios de Aceptación

- [ ] Un SELECT directo en PostgreSQL sobre la columna `nombre_completo` muestra solo datos cifrados en Base64.
- [ ] El modelo `Paciente` descifra correctamente `nombre_completo` cuando `_sym_key` está en sesión.
- [ ] Si `_sym_key` no está en sesión (sesión expirada), el acceso a un campo cifrado lanza `DecryptionException` y retorna `HTTP 401`.
- [ ] La creación de un Paciente persiste todos los campos cifrados correctamente.
- [ ] `EncryptedFieldCast` puede testearse unitariamente mockeando `EncryptionContextService` sin necesidad de sesión activa.
- [ ] Tests cubren: creación con cifrado, lectura con descifrado, intento de lectura sin clave en sesión.

---

### HU-06: Búsqueda Segura por Código

**Objetivo:** Encontrar un Paciente por su `PND-XXXXX` sin descifrar registros en masa y sin exponer el código como texto plano en la DB.

**Dependencias:** HU-04 (código PND), HU-05 (modelo Paciente).

#### Diseño del Blind Index

El `codigo_privacidad` en sí no está cifrado (es un identificador de búsqueda), pero su **hash determinista** permite verificar igualdad sin revelar el valor. Se usa HMAC-SHA256 con una clave secreta de aplicación (`BLIND_INDEX_SECRET` en `.env`):

```php
// app/Services/Crypto/BlindIndexService.php
public function hash(string $value): string
{
    return hash_hmac('sha256', strtoupper(trim($value)), config('app.blind_index_secret'));
}
```

#### Flujo de Búsqueda

```php
// En PacienteController@buscar:
$termino      = $request->validated('codigo');   // Ej: "pnd-a3k7m"
$hashBusqueda = $this->blindIndexService->hash($termino);

$paciente = Paciente::where('codigo_privacidad_hash', $hashBusqueda)->first();
// El Global Scope de AreaScope se aplica automáticamente a esta consulta.
```

#### Índice en Base de Datos

```sql
CREATE INDEX idx_pacientes_codigo_hash ON pacientes(codigo_privacidad_hash);
```

#### Criterios de Aceptación

- [ ] La búsqueda por `PND-A3K7M` retorna el paciente correcto usando el hash, no el texto plano.
- [ ] La búsqueda es case-insensitive (el hash siempre se calcula sobre mayúsculas).
- [ ] Una búsqueda con código de otra área retorna vacío (el Global Scope lo filtra).
- [ ] `EXPLAIN ANALYZE` de la query de búsqueda muestra uso del índice `idx_pacientes_codigo_hash`.
- [ ] Tests cubren: búsqueda exitosa, búsqueda de código inexistente (404), búsqueda de código de otra área (vacío/403).

---

## 7. Bases para Sprints Futuros

---

### ⚠️ Deuda Arquitectónica Crítica: Cifrado Multi-Especialista — C-09

> **Esta limitación debe resolverse en Sprint 2 antes de cualquier despliegue en producción con múltiples especialistas por área.**

**El problema:** La clave simétrica se deriva de la contraseña individual de cada Especialista. Esto significa que los datos cifrados por el Especialista A son **completamente ilegibles para el Especialista B**, incluso si ambos pertenecen al mismo Área y el RBAC les otorga el mismo nivel de acceso.

El RBAC define que Coordinadores y Especialistas deben poder leer *todos* los expedientes de su área, lo que es técnicamente imposible con el esquema actual.

**Opciones de solución para Sprint 2:**

| Opción | Descripción | Complejidad | Seguridad |
|---|---|---|---|
| **A — Clave compartida por Área** | Cada Área tiene una clave simétrica derivada de un secreto de aplicación (`AREA_KEY_SECRET`). La clave individual del Especialista solo se usa para autenticarlo, no para cifrar datos clínicos. | Baja | Media — la clave de área es un secreto de servidor |
| **B — Cifrado de sobre (DEK)** | Cada expediente tiene una Data Encryption Key (DEK) aleatoria. La DEK se cifra con la clave de cada Especialista autorizado. Cuando se da acceso a un nuevo Especialista, se re-cifra la DEK con su clave. | Alta | Alta — cada Especialista solo puede descifrar con su propia clave |
| **C — KMS externo** | Delegar la gestión de claves a un servicio externo (AWS KMS, HashiCorp Vault). La aplicación solicita permisos de descifrado al KMS con credenciales del Especialista. | Media | Muy alta |

**Recomendación:** Implementar la Opción A para Sprint 2 (más simple, resuelve el bloqueo funcional) y evaluar la Opción B para Sprint 4 si los requisitos de auditoría lo exigen.

**Impacto en Sprint 1:** El sprint 1 puede completarse con el esquema actual bajo el entendido explícito de que **cada Especialista solo puede leer datos que él mismo registró**. Esta limitación debe estar documentada en la UI.

---

### Auditoría (Audit Trail)

**Qué se necesita:**

Observers en modelos críticos (`Paciente`, `Expediente`) que escriben a la tabla `audit_logs`:

```sql
CREATE TABLE audit_logs (
    id              BIGSERIAL,
    especialista_id UUID NOT NULL,
    accion          VARCHAR(20) NOT NULL CHECK (accion IN ('create', 'read', 'update', 'soft_delete')),
    modelo          VARCHAR(60) NOT NULL,
    registro_uuid   UUID NOT NULL,
    ip_address      INET NOT NULL,
    user_agent      TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
    -- Sin columnas updated_at ni deleted_at: esta tabla es append-only
);
```

**Permisos de DB (obligatorio):**

```sql
-- El usuario de aplicación NO tiene UPDATE ni DELETE en audit_logs
REVOKE UPDATE, DELETE ON TABLE audit_logs FROM app_user;
GRANT INSERT, SELECT ON TABLE audit_logs TO app_user;
```

#### `AuditableController` Trait — C-10

> Los Observers no detectan lecturas automáticamente. Para evitar que el registro de `read` quede en manos de cada desarrollador (y se olvide), se provee un trait obligatorio para todos los controladores que operen con datos clínicos.

```php
// app/Traits/AuditableController.php
namespace App\Traits;

use App\Models\AuditLog;

trait AuditableController
{
    /**
     * Registrar un evento de lectura en el audit log.
     * Llamar en cada acción show() o index() que acceda a datos clínicos.
     */
    protected function auditRead(string $modelo, string $registroUuid): void
    {
        AuditLog::create([
            'especialista_id' => auth()->id(),
            'accion'          => 'read',
            'modelo'          => $modelo,
            'registro_uuid'   => $registroUuid,
            'ip_address'      => request()->ip(),
            'user_agent'      => request()->userAgent(),
        ]);
    }
}
```

Uso en controladores:
```php
class ExpedienteController extends Controller
{
    use AuditableController;

    public function show(Expediente $expediente): Response
    {
        $this->auditRead(Expediente::class, $expediente->id); // obligatorio
        return Inertia::render('Expediente/Show', ['expediente' => $expediente]);
    }
}
```

---

### Recuperación ante Desastres (Respaldos)

**Servicio en `docker-compose.yml` — C-13, C-14, C-15:**

> **Cambios respecto a v2.0:**
> - **C-14:** Se eliminó `PGPASSWORD` como variable de entorno de larga vida (visible en `/proc/<pid>/environ`). Se reemplaza por un archivo `.pgpass` creado al inicio del contenedor con permisos 600.
> - **C-15:** Se añadió `healthcheck` al servicio `db` y se configura `depends_on` con `condition: service_healthy` para evitar que el contenedor de backup arranque antes de que PostgreSQL esté listo.

```yaml
services:
  db:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB:       ${DB_DATABASE}
      POSTGRES_USER:     ${DB_USERNAME}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME} -d ${DB_DATABASE}"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s
    volumes:
      - pgdata:/var/lib/postgresql/data

  app:
    depends_on:
      db:
        condition: service_healthy

  backup:
    image: postgres:16-alpine
    environment:
      # Estas variables solo se usan durante el entrypoint para crear .pgpass
      # y son eliminadas del entorno inmediatamente después.
      _DB_HOST:     db
      _DB_PORT:     5432
      _DB_NAME:     ${DB_DATABASE}
      _DB_USER:     ${DB_USERNAME}
      _DB_PASSWORD: ${DB_PASSWORD}
    entrypoint: >
      /bin/sh -c "
        printf '%s:%s:%s:%s:%s\n'
          $$_DB_HOST $$_DB_PORT $$_DB_NAME $$_DB_USER $$_DB_PASSWORD
          > /root/.pgpass &&
        chmod 600 /root/.pgpass &&
        unset _DB_PASSWORD &&
        crond -f -l 2
      "
    volumes:
      - backup_data:/backups
      - ./scripts/backup.sh:/backup.sh
    depends_on:
      db:
        condition: service_healthy

volumes:
  pgdata:
  backup_data:
    driver: local
    driver_opts:
      type:   none
      o:      bind
      device: /encrypted/backups  # Punto de montaje cifrado en el host
```

> **Alternativa para producción:** En entornos con Docker Swarm o Portainer, usar **Docker Secrets** en lugar de variables de entorno para `DB_PASSWORD`. Ver documentación en `https://docs.docker.com/engine/swarm/secrets/`.

**Política de retención:**
- Respaldos diarios: retener los últimos **7 días**.
- Respaldos semanales: retener las últimas **4 semanas**.
- Logs de backup: rotación semanal con `logrotate`.

---

## 8. Fuera de Alcance (Non-Goals)

> **Para agentes IA:** Las siguientes funcionalidades están explícitamente **prohibidas** en Sprint 1. No implementar, no preparar, no agregar como "mejora mientras tanto".

| Fuera de alcance | Motivo |
|---|---|
| Cambio de contraseña de Especialista | Depende de re-cifrado de registros (Sprint 3) |
| Recuperación de contraseña por email | Requiere flujo de re-derivación de clave (Sprint 3) |
| Registro público de Especialistas | El alta de usuarios es manual por sysadmin (Sprint 2) |
| API REST independiente de Inertia | No es parte de la arquitectura decidida |
| Autenticación multifactor (MFA/2FA) | Sprint 2 |
| Exportación de expedientes (PDF, Excel) | Sprint 4 |
| Integración con servicios externos | No definido aún |
| Multi-tenancy (múltiples clínicas) | Fuera del alcance del producto actual |
| Solución al cifrado multi-especialista | Sprint 2 — documentado en §7 |

---

## 9. Estrategia de Testing

### Stack de Testing
- **Framework:** PestPHP `^3.x` con plugin de Laravel.
- **DB de testing:** Ver nota sobre compatibilidad abajo.
- **Factories:** Una Factory por modelo con `Faker` en español (`es_SV`).

#### Compatibilidad SQLite vs PostgreSQL — C-15

> **Cambio respecto a v2.0:** El documento original indicaba "SQLite en memoria para tests unitarios" sin advertir sobre incompatibilidades.

| Tipo de test | Engine de DB | Razón |
|---|---|---|
| **Unitarios** (servicios, casts, lógica pura) | Sin DB / en memoria | No requieren DB |
| **Feature con modelos simples** | SQLite en memoria | Más rápido; solo para modelos sin tipos PG-específicos |
| **Feature con RBAC, UUID nativo, INET** | **PostgreSQL en Docker** | `INET`, `uuid_generate_v4()` y `pgcrypto` no existen en SQLite |
| **Tests de concurrencia (HU-04)** | **PostgreSQL en Docker** | SQLite no soporta concurrencia real |

Configurar en `phpunit.xml`:
```xml
<!-- Para tests unitarios y feature básicos -->
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>

<!-- Override para suites que requieren PostgreSQL: -->
<!-- php artisan test --testsuite=Postgres -->
```

### Cobertura mínima por HU

| HU | Tipo de test requerido | Cobertura mínima |
|---|---|---|
| HU-01 | Feature + Unit (KeyDerivationService) | Login exitoso, login fallido, rate limit, derivación de clave |
| HU-02 | Feature | Logout limpia sesión, token inválido post-logout |
| HU-03 | Feature + Unit (AreaScope) | Acceso propio scope, acceso fuera de scope, sysadmin sin acceso clínico, multi-área |
| HU-04 | Unit (CodigoPrivacidadService) | Formato válido, colisión manejada, 5 reintentos agotados |
| HU-05 | Unit (EncryptedFieldCast mockando EncryptionContextService) + Feature | Cifrado en escritura, descifrado en lectura, fallo sin clave |
| HU-06 | Feature (PostgreSQL) | Búsqueda exitosa, búsqueda inexistente, búsqueda cross-área |

### Comandos

```bash
# Correr todos los tests
php artisan test --parallel

# Solo una HU específica
php artisan test --filter="HU01"

# Suite que requiere PostgreSQL
php artisan test --testsuite=Postgres
```

### Convención de nombres de test

```php
it('permite_login_con_credenciales_validas')           // HU-01
it('bloquea_sexto_intento_login_con_http_429')         // HU-01 — C-03
it('destruye_sym_key_al_hacer_logout')                 // HU-02
it('bloquea_expediente_fuera_de_area')                 // HU-03
it('especialista_multi_area_accede_a_sus_expedientes') // HU-03 — C-04
it('genera_codigo_pnd_con_formato_correcto')           // HU-04
it('cifra_nombre_completo_en_base_de_datos')           // HU-05
it('lanza_excepcion_al_descifrar_sin_clave_en_sesion') // HU-05 — C-05
it('busca_paciente_por_hash_sin_texto_plano')          // HU-06
```

---

## 10. Gestión de Secretos y Variables de Entorno

### Variables requeridas en `.env`

```dotenv
# Aplicación
APP_KEY=             # php artisan key:generate
APP_ENV=production
APP_DEBUG=false

# Base de datos
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=clinica_db
DB_USERNAME=app_user
DB_PASSWORD=         # Generado con: openssl rand -base64 32
DB_SSLMODE=require   # C-12: Conexión cifrada obligatoria

# Sesiones
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

# Criptografía
BLIND_INDEX_SECRET=  # Generado con: php -r "echo base64_encode(random_bytes(32));"
# Nota: BLIND_INDEX_SECRET es permanente. Si cambia, todos los hashes de búsqueda se invalidan.

# Backups
BACKUP_ENCRYPTION_KEY= # Generado con: openssl rand -base64 32
```

### Reglas de gestión de secretos

1. **Nunca commitear `.env`** al repositorio. El archivo `.env.example` no debe contener valores reales.
2. En producción, usar **Docker Secrets** o variables de entorno inyectadas por el orquestador (Portainer, Swarm, etc.).
3. El `APP_KEY` de Laravel se rota solo si hay evidencia de compromiso. La rotación invalida todas las sesiones activas.
4. El `BLIND_INDEX_SECRET` **nunca se rota** en producción sin un proceso de migración previo que recalcule todos los hashes.
5. **C-14:** En el contenedor de backup, `DB_PASSWORD` se usa solo al inicio del contenedor para generar el archivo `.pgpass` y se elimina del entorno. No persiste como variable de proceso.

### `.env.example` (para el repositorio)

```dotenv
APP_KEY=
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=clinica_db
DB_USERNAME=app_user
DB_PASSWORD=
DB_SSLMODE=require
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
BLIND_INDEX_SECRET=
BACKUP_ENCRYPTION_KEY=
```

---

*Fin del documento — Roadmap Sprint 1 v2.1*  
*Revisado desde v2.0 con 16 correcciones (C-01 a C-16).*  
*Próxima revisión al inicio del Sprint 2 — prioridad: resolver deuda arquitectónica C-09 (cifrado multi-especialista).*