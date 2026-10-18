
# =========================
# Stage 1: Build frontend assets
# =========================
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package*.json ./

RUN npm ci

COPY . .

RUN npm run build


# =========================
# Stage 2: Get Composer binary
# =========================
FROM composer:2 AS composer-bin


# =========================
# Stage 3: Install PHP dependencies
# =========================
FROM php:8.4-cli-alpine AS composer

WORKDIR /app

# Install system dependencies and PHP extensions required by Composer
RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    postgresql-dev \
    && docker-php-ext-install \
        bcmath \
        intl \
        pdo_mysql \
        pdo_pgsql \
        sockets \
        zip

# Copy Composer binary
COPY --from=composer-bin /usr/bin/composer /usr/bin/composer

# Install production dependencies
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts


# =========================
# Stage 4: Production image
# =========================
FROM php:8.4-fpm-alpine

WORKDIR /var/www/html

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    bash \
    icu-dev \
    libzip-dev \
    postgresql-dev \
    && docker-php-ext-install \
        bcmath \
        intl \
        pdo_mysql \
        pdo_pgsql \
        sockets \
        zip

# Copy Composer dependencies
COPY --from=composer /app/vendor ./vendor

# Copy application source
COPY . .

# Copy compiled frontend assets
COPY --from=frontend /app/public/build ./public/build

# Create Laravel writable directories and set permissions
RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache

# Production PHP configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

EXPOSE 9000

CMD ["php-fpm", "-F"]
