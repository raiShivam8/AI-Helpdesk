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
FROM php:8.3-fpm-alpine AS production


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
    imap-dev \
    c-client \
    krb5-dev \
    openssl-dev \
    icu-libs \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS


# Install PHP extensions
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    && docker-php-ext-configure imap \
    --with-kerberos \
    --with-imap-ssl \
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
    mbstring \
    imap


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



WORKDIR /var/www/html


# Copy Laravel project
COPY . .


# Copy built frontend
COPY --from=frontend /app/public/build ./public/build


# Copy vendor
COPY --from=composer /app/vendor ./vendor



# Copy entrypoint
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh


RUN chmod +x /usr/local/bin/docker-entrypoint.sh


# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache



# Railway port
EXPOSE 8080



ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]