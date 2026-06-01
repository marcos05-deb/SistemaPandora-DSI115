# PANDORA — Sistema de Gestión Clínica (DSI115)

Sistema integral de gestión clínica desarrollado con **Laravel 11, Inertia.js, Vue 3, Tailwind CSS y PostgreSQL**. Implementa estrictos controles de seguridad, incluyendo cifrado simétrico asimétrico, y Control de Acceso Basado en Roles (RBAC).

---

## 🚀 Requisitos Previos

El entorno está 100% dockerizado para garantizar que funcione idénticamente sin importar tu sistema operativo.

- **Linux:** Docker Engine y Docker Compose.
- **Windows / Mac:** Docker Desktop.

---

## 🛠️ Instalación y Ejecución (Linux y Windows)

No necesitas instalar PHP ni Composer localmente.

```bash
# 1. Clonar el repositorio y entrar a la carpeta
git clone <url-del-repo>
cd SistemaPandora-DSI115

# 2. Levantar los contenedores (esto instalará dependencias y migrará la DB automáticamente)
docker compose up --build
```

El script interno de Docker (`entrypoint.sh`) se encargará automáticamente de:
- Crear tu archivo `.env` a partir del `.env.example`.
- Instalar dependencias de PHP (`composer install`).
- Generar la `APP_KEY` si no existe.
- Esperar a que PostgreSQL esté listo y ejecutar `php artisan migrate`.

---

## ⚠️ Notas Importantes para Desarrolladores en Windows

Si estás utilizando **Docker Desktop en Windows**, la virtualización puede causar algunos cuellos de botella específicos que en Linux nativo no ocurren. Sigue estas instrucciones:

### 1. Lentitud extrema al Iniciar Sesión (Login)
PANDORA utiliza el algoritmo criptográfico **Argon2id** (altamente intensivo en CPU/Memoria) tanto para verificar contraseñas como para derivar claves de cifrado de pacientes (KDF). En Linux esto toma `~0.8s`, pero la virtualización de Docker en Windows puede hacerlo demorar entre 5 y 10 segundos.

**Solución para desarrollo local:** Baja los requerimientos de memoria y tiempo de Argon2. Añade estas variables al final de tu archivo `.env` local:
```env
# Reducir costo de Argon2 para desarrollo local (Windows/Docker)
HASH_ARGON_MEMORY=16384
HASH_ARGON_TIME=1
KDF_OPSLIMIT=1
KDF_MEMLIMIT=16777216
```
*(⚠️ Importante: Tras hacer este cambio, **debes** regenerar la base de datos para que los hashes de los Seeders se creen con estos nuevos límites. Ejecuta: `docker compose exec app php artisan migrate:fresh --seed`)*.

### 2. Error "Connection: sqlite" o "Database file does not exist"
PANDORA migró recientemente de SQLite a PostgreSQL. Si tienes este error, es porque tu máquina local tiene un archivo de caché viejo (`bootstrap/cache/config.php`) que Docker está leyendo.
- **Solución Automática:** El `entrypoint.sh` ahora limpia la caché automáticamente (`php artisan optimize:clear`) al hacer `docker compose up`.
- **Solución Manual:** Si el error persiste, elimina manualmente todos los archivos `.php` dentro de la carpeta `bootstrap/cache/` de tu máquina (deja únicamente el archivo `.gitignore`).

---

## 🌐 Accesos Rápidos

Una vez que `docker compose up` esté corriendo, puedes acceder a:

| Servicio | URL |
|----------|-----|
| **Aplicación Laravel** | http://localhost:8000 |
| **Vite** (Hot Reload CSS/JS) | http://localhost:5173 |
| **PostgreSQL 16** | `localhost:5434` (Usuario/Pass/DB: `pandora`) |

### Credenciales de Prueba (Seeders)

Para limpiar completamente la base de datos (borrar todos los registros) y volver a poblarla con datos iniciales y usuarios de prueba, ejecuta el siguiente comando:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Este comando generará automáticamente los siguientes usuarios para que puedas probar el sistema:

- **Administrador del Sistema:** `admin@pandora.com` / `password_segura`
- **Coordinador Clínico:** `coordinador@pandora.com` / `password_segura`
- **Referente Psicosocial:** `psicosocial@pandora.com` / `password_segura`

*(Todos los usuarios forzarán el cambio de contraseña al primer login en un entorno real, pero en local este flag viene desactivado para mayor comodidad).*

---

## 💻 Comandos Útiles de Docker

Ejecuta estos comandos en una terminal paralela mientras los contenedores están arriba:

```bash
# Apagar el entorno
docker compose down

# Limpiar volúmenes (si hay errores raros de base de datos o vendor)
docker compose down -v

# Entrar a la consola PHP dentro del contenedor
docker compose exec app sh

# Correr migraciones y seeders manualmente
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed

# Entrar a la consola SQL de PostgreSQL
docker compose exec postgres psql -U pandora -d pandora
```

*(Modo Producción: Si deseas levantar la aplicación sin Vite corriendo en tiempo real, puedes usar `docker compose -f docker-compose.prod.yml up --build`)*.
