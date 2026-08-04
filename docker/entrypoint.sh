#!/bin/sh
set -e

echo "🚀 Starting AI Helpdesk Production Server..."

# Railway injects $PORT dynamically (defaults to 8080 if not set)
PORT="${PORT:-8080}"
echo "🌐 Web server binding to 0.0.0.0:${PORT}..."

# Ensure storage directories exist and have proper permissions
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Create storage symlink
php artisan storage:link --force 2>/dev/null || true

# Prepare Laravel environment
echo "⚡ Preparing Laravel environment..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan package:discover --ansi || true

# Run database migrations in background
if [ "$RUN_MIGRATIONS" != "false" ]; then
    echo "📦 Triggering background database migrations..."
    (sleep 2 && php artisan migrate --force) &
fi

# Run background Queue Worker
echo "⚙️ Launching background Queue Worker..."
(php -d max_execution_time=0 artisan queue:work --sleep=3 --tries=3 --timeout=120 --max-time=3600) &

# Run background Scheduler
echo "⏱️ Launching background Scheduler..."
(php artisan schedule:work) &

# Execute passed command or start Laravel Web Server on 0.0.0.0:$PORT
if [ "$#" -gt 0 ]; then
    exec "$@"
else
    echo "🌟 Web Server listening on http://0.0.0.0:${PORT}..."
    exec php artisan serve --host=0.0.0.0 --port="${PORT}"
fi
