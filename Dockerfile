# ─────────────────────────────────────────────────────────────────────────────
# Stage 1: Install PHP dependencies (no dev) and generate Wayfinder routes
# ─────────────────────────────────────────────────────────────────────────────
FROM composer:2.8 AS composer-deps

WORKDIR /app

COPY composer.json composer.lock ./

# Sin scripts: el post-autoload-dump corre `artisan`, que todavia no esta.
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --no-autoloader \
    --ignore-platform-reqs

COPY . .

# El frontend importa las rutas tipadas que genera Wayfinder desde el codigo
# PHP. Se generan aca, donde hay PHP y la app completa; la etapa de Node solo
# las copia (ver SKIP_WAYFINDER en vite.config.ts).
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs \
    && composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan wayfinder:generate --with-form


# ─────────────────────────────────────────────────────────────────────────────
# Stage 2: Build frontend assets (Vite + Vue 3 + TypeScript)
# ─────────────────────────────────────────────────────────────────────────────
FROM node:22-alpine AS frontend

WORKDIR /app

# Copy dependency manifests first (layer cache)
COPY package.json package-lock.json ./

RUN npm ci --prefer-offline

# Copy source and build
COPY resources/    resources/
COPY public/       public/
COPY vite.config.ts tsconfig.json ./
COPY --from=composer-deps /app/resources/js/actions   resources/js/actions
COPY --from=composer-deps /app/resources/js/routes    resources/js/routes
COPY --from=composer-deps /app/resources/js/wayfinder resources/js/wayfinder

# Necesita internet: el plugin de fuentes baja Instrument Sans de fonts.bunny.net.
RUN SKIP_WAYFINDER=1 npm run build


# ─────────────────────────────────────────────────────────────────────────────
# Stage 3: Production image
# ─────────────────────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS production

LABEL maintainer="Grover Ramirez <grover.ramirez.z@gmail.com>"
LABEL description="Catálogo de productos — Laravel 13 + Inertia + Vue 3"

# ── System dependencies ──────────────────────────────────────────────────────
RUN apk add --no-cache \
        nginx \
        supervisor \
        curl \
        # GD image library
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev \
        # ZIP support
        libzip-dev \
        # Intl
        icu-dev \
        # Multibye strings
        oniguruma-dev \
        # mysqldump para los backups
        mariadb-client \
        # Misc
        shadow \
        bash

# ── PHP extensions ───────────────────────────────────────────────────────────
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        zip \
        bcmath \
        gd \
        pcntl \
        opcache \
        intl \
    && pecl install redis \
    && docker-php-ext-enable redis

# ── App directory ────────────────────────────────────────────────────────────
WORKDIR /var/www/html

# Copy application source (excluding items in .dockerignore)
COPY --chown=www-data:www-data . .

# Overwrite vendor with production-only install from stage 1
COPY --from=composer-deps --chown=www-data:www-data /app/vendor ./vendor

# Overwrite public/build with compiled frontend from stage 2
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build

# ── Runtime configuration ────────────────────────────────────────────────────
COPY docker/php.ini         "$PHP_INI_DIR/conf.d/99-app.ini"
COPY docker/nginx.conf      /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh   /entrypoint.sh
COPY docker/backup.sh       /usr/local/bin/backup.sh

RUN chmod +x /entrypoint.sh /usr/local/bin/backup.sh

# ── Directories + permissions ─────────────────────────────────────────────────
RUN mkdir -p \
        storage/app/public \
        storage/logs \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
        /var/log/supervisor \
        /var/run/php \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache \
        /var/log/supervisor \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -sf http://localhost/up || exit 1

ENTRYPOINT ["/entrypoint.sh"]
