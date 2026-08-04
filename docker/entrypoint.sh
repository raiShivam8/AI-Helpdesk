#!/bin/sh
set -e

echo "🚀 Starting AI Helpdesk Production Container..."

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

# Run database migrations if RUN_MIGRATIONS=true
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "📦 Running database migrations..."
    php artisan migrate --force
fi

# Cache configuration, routes, and views for production performance
echo "⚡ Caching Laravel assets..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Execute supervisor or passed command
if [ "$#" -gt 0 ]; then
    exec "$@"
else
    echo "🌟 Starting Supervisord Services (Nginx, PHP-FPM, Queue Worker, Scheduler)..."
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi
