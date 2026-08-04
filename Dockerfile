# =========================================================
# Stage 1: Build Frontend Assets (Vite, Tailwind, JS)
# =========================================================
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# =========================================================
# Stage 2: Install PHP Composer Dependencies
# =========================================================
FROM composer:2.7 AS composer
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
RUN composer dump-autoload --optimize --no-dev --ignore-platform-reqs

# =========================================================
# Stage 3: Production Runtime (PHP 8.4 FPM + Nginx + Supervisor)
# =========================================================
FROM php:8.4-fpm-alpine AS production

# Install System Dependencies & PHP Extensions required for Laravel, PostgreSQL, IMAP, etc.
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    postgresql-dev \
    imap-dev \
    krb5-dev \
    openssl-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers

# Configure & Install PHP Extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
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

# Configure Production OPcache settings
RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Configure PHP Limits
RUN { \
    echo 'upload_max_filesize=64M'; \
    echo 'post_max_size=64M'; \
    echo 'memory_limit=512M'; \
    echo 'max_execution_time=300'; \
} > /usr/local/etc/php/conf.d/custom-limits.ini

WORKDIR /var/www/html

# Copy application source code
COPY . .

# Copy built frontend assets from Stage 1
COPY --from=frontend /app/public/build ./public/build

# Copy vendor packages from Stage 2
COPY --from=composer /app/vendor ./vendor

# Ensure config directories exist
RUN mkdir -p /etc/nginx/http.d /etc/nginx/conf.d /etc/supervisor/conf.d

# Copy Docker configurations (Nginx, Supervisor, Entrypoint)
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh

# Make entrypoint executable
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Fix directory permissions for Nginx and PHP-FPM
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose HTTP Port
EXPOSE 80

# Define Container Entrypoint
ENTRYPOINT ["docker-entrypoint.sh"]
