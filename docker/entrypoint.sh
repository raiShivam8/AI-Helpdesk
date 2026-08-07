#!/bin/sh

set -e

echo "🚀 Starting AI Helpdesk Production Server..."

PORT="${PORT:-8080}"

echo "🌐 Server will run on 0.0.0.0:${PORT}"

# Create required Laravel directories
mkdir -p \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache

# Fix permissions
chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache || true

chmod -R 775 \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache || true

# Generate APP_KEY if missing
if [ -z "$APP_KEY" ]; then
    echo "🔑 APP_KEY is empty. Generating key..."
    php artisan key:generate --force || true
fi

echo "🔗 Creating storage link..."
php artisan storage:link --force || true

echo "⚡ Caching Laravel config..."
if [ ! -f "/var/www/html/public/build/manifest.json" ]; then
    echo "⚠️ Warning: public/build/manifest.json is missing! Vite assets might be missing."
fi
php artisan package:discover --ansi || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:clear || true

echo "📦 Running database migrations..."
php artisan migrate --force || true

echo "🌱 Seeding default users..."
php artisan db:seed --force || true

echo "⚙️ Starting Queue Worker..."
php artisan queue:work \
    --sleep=3 \
    --tries=3 \
    --timeout=120 \
    --max-time=3600 &

echo "⏰ Starting Scheduler..."
php artisan schedule:work &

echo "🌟 Starting PHP-FPM..."
php-fpm -D 2>&1

echo "⏳ Waiting for PHP-FPM socket on port 9000..."
while ! nc -z 127.0.0.1 9000; do
    sleep 0.1
done
echo "✅ PHP-FPM is ready!"

echo "🌐 Configuring Nginx to listen on port ${PORT}, 80, and 8080..."
mkdir -p /etc/nginx/http.d /etc/nginx/conf.d
rm -f /etc/nginx/http.d/*.conf /etc/nginx/conf.d/*.conf

# Listen on PORT, 80, and 8080 to guarantee compatibility regardless of Railway proxy settings
LISTEN_CONF="listen ${PORT};\n    listen 80;\n"
if [ "${PORT}" != "8080" ]; then
    LISTEN_CONF="${LISTEN_CONF}    listen 8080;\n"
fi

sed "s/listen PORT_PLACEHOLDER;/$(printf '%s' "$LISTEN_CONF")/g" /var/www/html/docker/nginx.conf > /etc/nginx/http.d/default.conf

echo "🌐 Starting Nginx..."
exec nginx -g "daemon off;"