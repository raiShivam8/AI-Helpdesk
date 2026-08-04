#!/bin/sh
set -e

echo "🚀 Starting AI Helpdesk Production Container..."

# Dynamically bind Nginx to Railway $PORT environment variable if provided
PORT="${PORT:-80}"
echo "🌐 Configuring Nginx to listen on port ${PORT}..."
if [ -f /etc/nginx/http.d/default.conf ]; then
    sed -i "s/listen 80;/listen ${PORT};/g" /etc/nginx/http.d/default.conf
    sed -i "s/listen \[::\]:80;/listen \[::\]:${PORT};/g" /etc/nginx/http.d/default.conf
fi

# Ensure storage directories exist and have proper permissions
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Create storage symlink if not existing
php artisan storage:link --force 2>/dev/null || true

# Run database migrations if RUN_MIGRATIONS=true or by default in deployment
if [ "$RUN_MIGRATIONS" != "false" ]; then
    echo "📦 Running database migrations..."
    php artisan migrate --force || true
fi

# Cache configuration, routes, and views for production performance
echo "⚡ Caching Laravel assets..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Execute supervisor or passed command
if [ "$#" -gt 0 ]; then
    exec "$@"
else
    echo "🌟 Starting Supervisord Services (Nginx, PHP-FPM, Queue Worker, Scheduler)..."
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi
