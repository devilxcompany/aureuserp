# ============================================================
# Stage 1: PHP dependencies (Composer)
# ============================================================
FROM composer:2.7 AS composer

WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    2>/dev/null || composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist

# ============================================================
# Stage 2: Node.js assets (if package.json exists)
# ============================================================
FROM node:20-alpine AS node_builder

WORKDIR /app
COPY package*.json ./
RUN if [ -f package.json ]; then npm ci --production=false; fi
COPY . .
RUN if [ -f package.json ] && grep -q '"build"' package.json; then npm run build; fi

# ============================================================
# Stage 3: Final PHP-FPM image
# ============================================================
FROM php:8.2-fpm-alpine AS production

LABEL maintainer="DevilX Company <admin@devilxcompany.com>"
LABEL org.opencontainers.image.title="AureusERP"
LABEL org.opencontainers.image.description="ERP Management System"

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    git \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    mysql-client \
    oniguruma-dev \
    postgresql-client \
    postgresql-dev \
    redis \
    shadow \
    sqlite \
    sqlite-dev \
    unzip \
    zip \
    && rm -rf /var/cache/apk/*

# Install PHP extensions
RUN docker-php-ext-install \
    bcmath \
    exif \
    gd \
    mbstring \
    opcache \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    pcntl \
    xml \
    zip

# Install Redis PHP extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Copy custom PHP configuration
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/custom.conf

# Create non-root user
RUN addgroup -g 1000 www \
    && adduser -u 1000 -G www -s /bin/bash -D www

WORKDIR /var/www/html

# Copy application code
COPY --chown=www:www . .

# Copy vendor from composer stage
COPY --from=composer --chown=www:www /app/vendor ./vendor

# Copy built assets from node stage (if they exist)
COPY --from=node_builder --chown=www:www /app/public/build ./public/build 2>/dev/null || true

# Create necessary directories
RUN mkdir -p \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www:www storage bootstrap/cache

# Create SQLite database file for dev/testing
RUN touch database/database.sqlite \
    && chown www:www database/database.sqlite \
    && chmod 664 database/database.sqlite

USER www

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD php-fpm -t 2>/dev/null && echo "OK" || exit 1

CMD ["php-fpm"]
