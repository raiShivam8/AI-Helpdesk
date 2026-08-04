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
RUN composer dump-autoload --optimize --no-dev --no-scripts --ignore-platform-reqs

# =========================================================
# Stage 3: Production Runtime (PHP 8.3 FPM + Nginx + Supervisor)
# =========================================================
FROM php:8.3-fpm-alpine AS production

# Install System Dependencies & PHP Extensions required for Laravel, PostgreSQL, IMAP, etc.
RUN apk add --no-cache \
    nginx \
    supervisor \
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

# Expose HTTP Ports
EXPOSE 80 8080 3000

# Define Container Startup Command
CMD ["sh", "-c", "mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache && chmod -R 775 storage bootstrap/cache 2>/dev/null || true && php artisan storage:link --force 2>/dev/null || true && php artisan config:clear || true && php artisan route:clear || true && php artisan view:clear || true && php artisan package:discover --ansi || true && (php -d max_execution_time=0 artisan queue:work --sleep=3 --tries=3 --timeout=120 --max-time=3600 &) && (php artisan schedule:work &) && (sleep 2 && php artisan migrate --force &) && echo '🌟 Server listening on 0.0.0.0:'${PORT:-8080} && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]
