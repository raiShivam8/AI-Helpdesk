# =========================================================
# Stage 1: Build Frontend Assets
# =========================================================
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package*.json ./

RUN npm ci

COPY . .

RUN npm run build


# =========================================================
# Stage 2: Install Composer Dependencies
# =========================================================
FROM composer:latest AS composer

WORKDIR /app

COPY composer*.json ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --ignore-platform-reqs


COPY . .

RUN composer dump-autoload \
    --optimize \
    --no-dev \
    --no-scripts \
    --ignore-platform-reqs


# =========================================================
# Stage 3: Laravel Production Runtime
# =========================================================
FROM php:8.4-fpm-alpine AS production


# Install PHP dependencies
RUN apk add --no-cache \
    nginx \
    curl \
    git \
    libpng \
    libpng-dev \
    libjpeg-turbo \
    libjpeg-turbo-dev \
    freetype \
    freetype-dev \
    libzip \
    libzip-dev \
    postgresql-libs \
    postgresql-dev \
    icu-libs \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS


# Install PHP extensions
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    pgsql \
    pdo_mysql \
    gd \
    zip \
    bcmath \
    opcache \
    intl \
    mbstring


# PHP production settings
RUN { \
    echo 'memory_limit=512M'; \
    echo 'upload_max_filesize=64M'; \
    echo 'post_max_size=64M'; \
    echo 'max_execution_time=300'; \
    } > /usr/local/etc/php/conf.d/custom.ini


# OPcache
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    } > /usr/local/etc/php/conf.d/opcache.ini


# Redirect PHP-FPM logs to stdout to prevent Railway from highlighting NOTICE messages as errors
RUN { \
    echo '[global]'; \
    echo 'error_log = /proc/self/fd/1'; \
    } > /usr/local/etc/php-fpm.d/zz-log.conf



WORKDIR /var/www/html


# Copy Laravel project
COPY . .
RUN cp .env.example .env || touch .env


# Copy built frontend
COPY --from=frontend /app/public/build ./public/build


# Copy vendor
COPY --from=composer /app/vendor ./vendor



# Copy entrypoint
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh

# Copy nginx config
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

RUN chmod +x /usr/local/bin/docker-entrypoint.sh


# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache



# Render & Default Web Ports
EXPOSE 10000
EXPOSE 8080
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]