# syntax=docker/dockerfile:1

# ---- Front-end assets (the customer portal) ---------------------------------
FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund
COPY vite.config.js tsconfig.json ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---- Application -------------------------------------------------------------
# nginx + PHP-FPM, built for Laravel. The same image runs the web app, the
# queue worker and the scheduler; docker-compose.yml picks the role.
FROM serversideup/php:8.4-fpm-nginx AS app

USER root
# intl: Filament's number/date formatting. Everything else Laravel needs ships in the base image.
RUN install-php-extensions intl
USER www-data

WORKDIR /var/www/html

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY --chown=www-data:www-data . .
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi \
    && php artisan filament:assets

# Coolify's proxy terminates TLS; the container speaks plain HTTP on 8080.
ENV SSL_MODE=off \
    PHP_OPCACHE_ENABLE=1 \
    LOG_CHANNEL=stderr
