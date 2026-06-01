#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --no-progress
fi

if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
    php artisan key:generate --force --no-interaction
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs database
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Limpiar caché obsoleta (especialmente config cache) que pueda causar conflictos con SQLite antiguo
php artisan optimize:clear 2>/dev/null || true

if [ "${DB_CONNECTION:-pgsql}" = "pgsql" ]; then
    echo "Esperando PostgreSQL..."
    i=0
    while [ "$i" -lt 30 ]; do
        if php -r "
            \$h = getenv('DB_HOST') ?: 'postgres';
            \$p = getenv('DB_PORT') ?: '5432';
            \$d = getenv('DB_DATABASE') ?: 'pandora';
            \$u = getenv('DB_USERNAME') ?: 'pandora';
            \$w = getenv('DB_PASSWORD') ?: 'pandora';
            new PDO(\"pgsql:host=\$h;port=\$p;dbname=\$d\", \$u, \$w);
        " 2>/dev/null; then
            break
        fi
        i=$((i + 1))
        sleep 2
    done
    php artisan migrate --force --no-interaction 2>/dev/null || true
fi

exec "$@"
