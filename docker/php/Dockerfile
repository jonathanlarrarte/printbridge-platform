# syntax=docker/dockerfile:1
#
# Una sola imagen para los 3 servicios de la app en docker-compose.yml
# (app/queue/scheduler) -- mismo filesystem, distinto comando cada uno.
# Deliberadamente calca la arquitectura que ya corre en produccion (nginx
# del host -> proxy_pass a `php artisan serve`, ver /etc/nginx en el VPS
# actual) en vez de migrar a PHP-FPM + nginx-en-contenedor -- menos piezas
# nuevas que puedan fallar en una migracion a un servidor de cliente donde
# la prioridad es no romper nada, no "arquitectura ideal".

# --- Etapa 1: build de los assets del dashboard (Vite) ---------------------
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources resources
COPY vite.config.js ./
RUN npm run build

# --- Etapa 2: dependencias de Composer (sin dev, autoload optimizado) ------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# --- Etapa 3: runtime PHP ----------------------------------------------------
FROM php:8.3-cli AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libicu-dev libonig-dev unzip git \
    && docker-php-ext-install pdo_pgsql pgsql bcmath intl zip opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove libpq-dev libzip-dev libicu-dev libonig-dev unzip git \
    && rm -rf /var/lib/apt/lists/*

# OPcache en modo produccion -- sin esto, cada request recompila todo el
# codigo PHP desde cero, igual que en desarrollo. validate_timestamps=0
# significa que un cambio de codigo requiere reconstruir la imagen y
# recrear el contenedor, nunca alcanza con pisar archivos en vivo.
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache-prod.ini

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/scripts/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

ENTRYPOINT ["entrypoint.sh"]
# Default: el servicio `app`. queue/scheduler pisan esto en docker-compose.yml.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
