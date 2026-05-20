# PANDORA — Sistema de Gestión Clínica (DSI115)

Proyecto Laravel 11 + Inertia.js (Vue 3) con las **6 pantallas mockup** implementadas.

## Recomendado: Docker (todo en contenedores)

No necesitas instalar PHP ni Composer en Windows. Solo [Docker Desktop](https://www.docker.com/products/docker-desktop/).

```powershell
cd C:\Users\marga\OneDrive\Documentos\GitHub\SistemaPandora-DSI115
docker compose up --build
```

| Servicio | URL |
|----------|-----|
| Aplicación Laravel | http://localhost:8000 |
| Vite (hot reload CSS/JS) | http://localhost:5173 |
| PostgreSQL 16 | `localhost:5432` (usuario/contraseña/BD: `pandora`) |

### Rutas mockup

| Pantalla | URL |
|----------|-----|
| Login | http://localhost:8000/login |
| Usuarios y roles | http://localhost:8000/usuarios |
| Códigos de privacidad | http://localhost:8000/codigos-privacidad |
| Registro paciente | http://localhost:8000/registro-paciente |
| Búsqueda segura | http://localhost:8000/busqueda-segura (código demo: `PND-00124`) |
| Cierre de sesión | http://localhost:8000/cierre-sesion |

### Comandos útiles

```powershell
docker compose down          # Detener
docker compose logs -f app     # Ver logs de Laravel
docker compose exec app sh   # Entrar al contenedor PHP
docker compose exec app php artisan migrate   # Migraciones
docker compose exec postgres psql -U pandora -d pandora   # Consola SQL
```

Si ves error de nombre de contenedor o `vendor` vacío:

```powershell
docker compose down -v
docker compose up --build
```

Asegúrate de que **Docker Desktop esté abierto** antes de `docker compose up` (icono de ballena en la bandeja del sistema).

Modo solo app (sin Vite en vivo, usa `public/build` ya compilado):

```powershell
docker compose -f docker-compose.prod.yml up --build
```

## Instalación local (sin Docker)

> No abras `composer.phar` con doble clic en Windows.

Necesitas **PostgreSQL** en el host (puerto 5432) con base `pandora` y usuario `pandora` (o ajusta `.env`).

```powershell
.\composer.bat install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

## Estructura Docker

```
docker-compose.yml      # postgres + app (PHP 8.2) + vite (Node 22)
docker-compose.prod.yml # postgres + app
Dockerfile              # PHP 8.2 con pdo_pgsql
docker/entrypoint.sh    # composer, .env, espera a Postgres, migrate
```

Base de datos: **PostgreSQL** (`DB_CONNECTION=pgsql`). El archivo `database/database.sqlite` ya no se usa.

## Próximos pasos

- Roles y permisos por pantalla
- Autenticación real y base de datos
- CRUD completo (más allá de mockups)
