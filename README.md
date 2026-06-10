# PANDORA - Sistema de Gestión Clínica (DSI115)

## Descripción del Sistema

PANDORA es un sistema integral de gestión clínica desarrollado sobre el framework Laravel 11, utilizando Inertia.js y Vue 3 para la capa de presentación. La arquitectura implementa controles estrictos de seguridad, incluyendo cifrado simétrico y asimétrico para el resguardo de información clínica, y un modelo de Control de Acceso Basado en Roles (RBAC) con aislamiento de datos por áreas organizacionales.

## Requisitos del Entorno

La infraestructura de desarrollo está diseñada para operar exclusivamente a través de contenedores, garantizando la paridad entre los entornos de desarrollo local y los entornos de integración/producción.

Dependencias requeridas en la máquina host:
- Docker Engine (Linux) o Docker Desktop (Windows / macOS).
- Docker Compose v2 o superior.
- Git.

No es necesario disponer de binarios locales de PHP, Composer o Node.js.

## Instalación y Despliegue Local

1. Clonar el repositorio del proyecto e ingresar al directorio de trabajo:
   ```bash
   git clone <repository_url>
   cd SistemaPandora-DSI115
   ```

2. Construir las imágenes e inicializar los contenedores:
   ```bash
   docker compose up --build
   ```

El script de aprovisionamiento interno (`docker/entrypoint.sh`) automatiza las siguientes tareas críticas durante el arranque:
- Creación del archivo de configuración `.env` a partir de `.env.example`.
- Resolución e instalación de dependencias backend mediante Composer.
- Generación de la clave de encriptación de la aplicación (`APP_KEY`).
- Espera activa del servicio PostgreSQL y ejecución de las migraciones pendientes.

## Configuración Criptográfica y Rendimiento (Específico para Windows)

PANDORA implementa el algoritmo Argon2id tanto para el hashing de contraseñas de usuarios como para la Función de Derivación de Claves (KDF) utilizada en el cifrado de expedientes. Este algoritmo es intencionalmente intensivo a nivel computacional y de memoria.

En entornos virtualizados como Docker Desktop para Windows, el sobrecosto de I/O y virtualización de CPU incrementa sustancialmente el tiempo de ejecución de Argon2id, resultando en latencias de inicio de sesión de hasta 10 segundos.

Para mitigar este impacto de rendimiento de manera local, se deben reducir los parámetros de costo computacional en su entorno de desarrollo. Incorpore las siguientes directivas al final de su archivo `.env`:

```env
# Mitigación de carga criptográfica para entornos de desarrollo virtualizados
HASH_ARGON_MEMORY=16384
HASH_ARGON_TIME=1
KDF_OPSLIMIT=1
KDF_MEMLIMIT=16777216
```

**Nota Operativa:** La modificación de los parámetros del KDF y de hashing invalida las contraseñas e información cifrada generada previamente. Es estrictamente necesario reconstruir la base de datos tras aplicar este cambio (véase la sección siguiente).

## Inicialización de Base de Datos y Credenciales

Para efectuar un borrado completo del esquema relacional y repoblar la base de datos con las estructuras y los usuarios de prueba, ejecute el siguiente comando contra el contenedor de aplicación:

```bash
docker compose exec app php artisan migrate:fresh --database=pgsql_admin --seed
```

El proceso de sembrado (`DatabaseSeeder.php`) aprovisionará las siguientes credenciales con propósitos de integración y prueba:

- Administrador del Sistema: `admin@pandora.com` / `password_segura`
- Coordinador Clínico: `coordinador@pandora.com` / `password_segura`
- Referente Psicosocial: `psicosocial@pandora.com` / `password_segura`

La política de cambio de contraseña forzado al primer inicio de sesión se encuentra deshabilitada de manera predeterminada para estas cuentas en el entorno local.

## Resolución de Problemas Comunes

### Conflicto de Caché Obsoleta (Error: "Connection: sqlite")
El sistema ha sido migrado recientemente de SQLite a PostgreSQL. Si al iniciar el entorno se presenta una excepción indicando la ausencia de un archivo `database.sqlite`, esto confirma la existencia de archivos de caché obsoletos en su volumen de trabajo local.

El archivo `entrypoint.sh` contiene rutinas de invalidación de caché, pero en caso de que el error persista, realice una purga manual:
1. Elimine recursivamente todos los archivos con extensión `.php` alojados dentro del directorio `bootstrap/cache/` en su máquina host. Debe mantener intacto el archivo `.gitignore` ubicado en ese mismo directorio.
2. Reinicie el conjunto de contenedores.

### Error "Firebase\JWT\JWT not found" al clonar el repositorio
El directorio `vendor/` está excluido del control de versiones (`.gitignore`) porque las dependencias se gestionan con Composer. Este error indica que las dependencias PHP no están instaladas.

**Solución con Docker** (recomendada):
```bash
docker compose down -v
docker compose up --build
```
El `entrypoint.sh` ejecutará `composer install` automáticamente durante el arranque.

**Solución sin Docker** (instalación local):
```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
```

### Generación del BLIND_INDEX_SECRET
La clave de índice ciego (`BLIND_INDEX_SECRET`) es requerida para el cifrado determinista de datos clínicos. El `entrypoint.sh` no la genera automáticamente. Tras el primer despliegue, se debe ejecutar:

```bash
docker compose exec app php -r "file_put_contents('.env', PHP_EOL . 'BLIND_INDEX_SECRET=' . base64_encode(random_bytes(32)), FILE_APPEND);"
docker compose exec app php artisan config:clear
```

Para instalaciones sin Docker, generar el valor y agregarlo manualmente al archivo `.env`:
```bash
php -r "echo base64_encode(random_bytes(32));"
# Copiar la salida y agregar al .env: BLIND_INDEX_SECRET=<valor_generado>
```

## Accesos a Servicios Locales

Finalizado el ciclo de arranque de Docker, la arquitectura de red expone los siguientes puertos vinculados al host:

- Aplicación Backend (Laravel): `http://localhost:8000`
- Servidor Frontend (Vite HMR): `http://localhost:5173`
- Motor Relacional (PostgreSQL 16): `localhost:5434` (Credenciales por defecto: `pandora` / `pandora` / `pandora`)

## Referencia de Comandos CLI

Las operaciones de administración de infraestructura deben ejecutarse interactuando con los contenedores. A continuación se listan los comandos operativos estándar:

```bash
# Interrupción segura de contenedores y red
docker compose down

# Destrucción de entorno y purga de volúmenes persistentes (Elimina DB local y dependencias)
docker compose down -v

# Iniciar sesión interactiva bash/sh en el contenedor PHP
docker compose exec app sh

# Interfaz de consola nativa de PostgreSQL
docker compose exec postgres psql -U pandora -d pandora

# Emulación local del entorno de Producción (servidor Vite deshabilitado, usa public/build)
docker compose -f docker-compose.prod.yml up --build
```

## Protocolo de Reporte de Errores y Contribución

Cualquier hallazgo de defectos de software, bugs o degradación de servicio debe ser rigurosamente documentado en el sistema de seguimiento de Issues del proyecto. Un reporte técnico válido debe cumplir estrictamente con la siguiente estructura:

### 1. Contexto de Ejecución
- Sistema Operativo del Host y versión de arquitectura.
- Versiones exactas del binario Docker Engine y Docker Compose instalados.
- Identificador del commit (`hash`) y nombre de la rama (`branch`) donde se detectó la incidencia.

### 2. Descripción de la Incidencia
- Comportamiento esperado versus comportamiento observado.
- Listado determinístico de pasos precisos para reproducir el fallo.

### 3. Trazas y Telemetría
- Stack trace íntegro arrojado por el framework (exportación en texto plano).
- Extractos temporales relevantes del archivo `storage/logs/laravel.log`.
- Salida estándar y de error del contenedor afectado, obtenida mediante:
  `docker compose logs app` o `docker compose logs postgres`.
