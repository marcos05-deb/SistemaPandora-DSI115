# Stage 1: Build Frontend Assets
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install
COPY vite.config.js tailwind.config.js postcss.config.js jsconfig.json* ./
COPY resources/ resources/
COPY public/ public/
RUN npm run build

# Stage 2: Application
FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libpq-dev \
    libicu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install zip pdo_pgsql pgsql intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

# Create system user to run Composer and Artisan Commands
RUN useradd -G www-data,root -u 1000 -d /home/pandora pandora
RUN mkdir -p /home/pandora/.composer && \
    chown -R pandora:pandora /home/pandora

# Copy existing application directory contents and set permissions
COPY --chown=pandora:pandora . /var/www/html

# Copy compiled frontend assets from Stage 1
COPY --chown=pandora:pandora --from=frontend /app/public/build /var/www/html/public/build

# Switch to the restricted user
USER pandora

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
