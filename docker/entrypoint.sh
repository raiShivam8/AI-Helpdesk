#!/bin/sh

set -e

PORT="${PORT:-10000}"

# Handle dedicated Background Worker or Cron Job execution modes on Render
if [ "$1" = "worker" ]; then
    echo "⚙️ Starting Render Background Queue Worker..."
    exec php artisan queue:work --sleep=3 --tries=3 --timeout=120
elif [ "$1" = "cron" ]; then
    echo "⏰ Executing Render Cron IMAP Email Fetch..."
    exec php artisan tickets:fetch-emails
elif [ "$#" -gt 0 ] && [ "$1" != "web" ]; then
    echo "▶️ Executing custom command: $@"
    exec "$@"
fi

echo "🚀 Starting AI Helpdesk Production Web Server..."
echo "🌐 Server will run on 0.0.0.0:${PORT}"

# Create required Laravel directories
mkdir -p \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache

# Ensure .env file exists so artisan key:generate and config:cache operate properly
if [ ! -f "/var/www/html/.env" ]; then
    echo "📄 Creating .env file from .env.example..."
    if [ -f "/var/www/html/.env.example" ]; then
        cp /var/www/html/.env.example /var/www/html/.env
    else
        touch /var/www/html/.env
    fi
fi

# Generate APP_KEY if missing in environment
if [ -z "$APP_KEY" ]; then
    echo "🔑 APP_KEY is empty. Generating key..."
    php artisan key:generate --force || true
    ENV_KEY=$(grep '^APP_KEY=' /var/www/html/.env 2>/dev/null | cut -d '=' -f2-)
    if [ -n "$ENV_KEY" ]; then
        export APP_KEY="$ENV_KEY"
    fi
fi

echo "🔗 Creating storage link..."
php artisan storage:link --force || true

echo "⚡ Caching Laravel config & routes..."
if [ ! -f "/var/www/html/public/build/manifest.json" ]; then
    echo "⚠️ Warning: public/build/manifest.json is missing! Vite assets might be missing."
fi
php artisan package:discover --ansi || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:clear || true

echo "📦 Running database migrations and seeders..."
php artisan migrate --force
php artisan db:seed --force || true

echo "🔒 Setting permissions for www-data..."
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

if [ -f "/etc/nginx/nginx.conf" ]; then
    sed -i 's/user  nginx;/user www-data;/g' /etc/nginx/nginx.conf || true
    sed -i 's/user nginx;/user www-data;/g' /etc/nginx/nginx.conf || true
fi

echo "🌟 Starting PHP-FPM..."
php-fpm -D 2>&1

echo "⏳ Waiting for PHP-FPM socket on port 9000..."
while ! nc -z 127.0.0.1 9000; do
    sleep 0.1
done
echo "✅ PHP-FPM is ready!"

echo "🌐 Configuring Nginx to listen on port ${PORT}..."
mkdir -p /etc/nginx/http.d /etc/nginx/conf.d
rm -f /etc/nginx/http.d/*.conf /etc/nginx/conf.d/*.conf

sed "s/PORT_PLACEHOLDER/${PORT}/g" /var/www/html/docker/nginx.conf > /etc/nginx/http.d/default.conf

echo "🌐 Starting Nginx..."
exec nginx -g "daemon off;"