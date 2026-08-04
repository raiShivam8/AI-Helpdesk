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


echo "🔗 Creating storage link..."
php artisan storage:link --force || true


echo "⚡ Clearing Laravel cache..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true


echo "📦 Running database migrations..."
php artisan migrate --force || true


echo "⚙️ Starting Queue Worker..."

php artisan queue:work \
    --sleep=3 \
    --tries=3 \
    --timeout=120 \
    --max-time=3600 &


echo "⏰ Starting Scheduler..."

php artisan schedule:work &


echo "🌟 Starting Laravel server..."

exec php artisan serve \
    --host=0.0.0.0 \
    --port="${PORT}"