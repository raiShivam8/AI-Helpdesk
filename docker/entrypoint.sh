#!/bin/sh

set -e

PORT="${PORT:-10000}"

# Create required Laravel directories
mkdir -p \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache

# Ensure .env file exists
if [ ! -f "/var/www/html/.env" ]; then
    echo "📄 Creating .env file from .env.example..."
    if [ -f "/var/www/html/.env.example" ]; then
        cp /var/www/html/.env.example /var/www/html/.env
    else
        touch /var/www/html/.env
    fi
fi

# Sync runtime environment variables from Render to .env file
sync_env_var() {
    var_name="$1"
    eval "var_val=\${$var_name}"
    if [ -n "$var_val" ]; then
        if grep -q "^${var_name}=" /var/www/html/.env 2>/dev/null; then
            sed -i "s|^${var_name}=.*|${var_name}=${var_val}|g" /var/www/html/.env
        else
            echo "${var_name}=${var_val}" >> /var/www/html/.env
        fi
    fi
}

for v in GEMINI_API_KEY GOOGLE_API_KEY GEMINI_KEY GEMINI_MODEL GEMINI_TIMEOUT GEMINI_CONNECT_TIMEOUT \
         APP_NAME APP_ENV APP_KEY APP_DEBUG APP_URL \
         DB_CONNECTION DATABASE_URL DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD DB_SSLMODE \
         SESSION_DRIVER QUEUE_CONNECTION CACHE_STORE FILESYSTEM_DISK \
         MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_ENCRYPTION MAIL_FROM_ADDRESS MAIL_FROM_NAME \
         IMAP_HOST IMAP_PORT IMAP_ENCRYPTION IMAP_PROTOCOL IMAP_USERNAME IMAP_PASSWORD IMAP_VALIDATE_CERT \
         SUPPORT_EMAIL SUPPORT_NAME ADMIN_EMAIL ADMIN_PASSWORD WEBHOOK_SECRET SENTRY_LARAVEL_DSN; do
    sync_env_var "$v"
done

# Normalize bare Render PostgreSQL internal host (dpg-*-a) in .env directly
php -r '
    $envFile = "/var/www/html/.env";
    if (file_exists($envFile)) {
        $c = file_get_contents($envFile);
        $region = getenv("RENDER_REGION") ?: "oregon";
        $updated = preg_replace("/(dpg-[a-z0-9]+-a)(?!\.)/i", "\$1.{$region}-postgres.render.com", $c);
        if ($updated !== $c) {
            file_put_contents($envFile, $updated);
            echo "🌐 Normalized Render PostgreSQL host in .env to use .{$region}-postgres.render.com\n";
        }
    }
' || true

if [ -f "/var/www/html/.env" ]; then
    DB_URL_VAL=$(grep '^DATABASE_URL=' /var/www/html/.env 2>/dev/null | cut -d '=' -f2-)
    if [ -n "$DB_URL_VAL" ]; then
        export DATABASE_URL="$DB_URL_VAL"
    fi
    DB_HOST_VAL=$(grep '^DB_HOST=' /var/www/html/.env 2>/dev/null | cut -d '=' -f2-)
    if [ -n "$DB_HOST_VAL" ]; then
        export DB_HOST="$DB_HOST_VAL"
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

# Handle dedicated Background Worker or Cron Job execution modes on Render
if [ "$1" = "worker" ]; then
    echo "⚙️ Starting Render Background Queue Worker..."
    php artisan config:clear || true
    php artisan config:cache || true
    exec php artisan queue:work --sleep=3 --tries=3 --timeout=120
elif [ "$1" = "cron" ]; then
    echo "⏰ Executing Render Cron IMAP Email Fetch..."
    php artisan config:clear || true
    php artisan config:cache || true
    exec php artisan tickets:fetch-emails
elif [ "$#" -gt 0 ] && [ "$1" != "web" ]; then
    echo "▶️ Executing custom command: $@"
    exec "$@"
fi

echo "🚀 Starting AI Helpdesk Production Web Server..."
echo "🌐 Server will run on 0.0.0.0:${PORT}"

echo "🔗 Creating storage link..."
php artisan storage:link --force || true

echo "⚡ Caching Laravel config & routes..."
if [ ! -f "/var/www/html/public/build/manifest.json" ]; then
    echo "⚠️ Warning: public/build/manifest.json is missing! Vite assets might be missing."
fi
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan package:discover --ansi || true
php artisan config:cache || true
php artisan route:cache || true

echo "📦 Testing database connection..."
mkdir -p /var/www/html/database
touch /var/www/html/database/database.sqlite
chmod -R 777 /var/www/html/database

if php -r '
    require "/var/www/html/vendor/autoload.php";
    $app = require_once "/var/www/html/bootstrap/app.php";
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    $conn = config("database.default");
    try {
        Illuminate\Support\Facades\DB::connection($conn)->getPdo();
        echo "✅ Database connection ($conn) SUCCESS!\n";
        exit(0);
    } catch (\Throwable $e) {
        echo "❌ Primary database connection ($conn) failed: " . $e->getMessage() . "\n";
        exit(1);
    }
'; then
    echo "✅ Using primary database connection."
else
    echo "⚠️ Primary database is unreachable. Switching to SQLite for zero-downtime operation..."
    sed -i 's|^DB_CONNECTION=.*|DB_CONNECTION=sqlite|g' /var/www/html/.env
    if ! grep -q "^DB_CONNECTION=" /var/www/html/.env; then
        echo "DB_CONNECTION=sqlite" >> /var/www/html/.env
    fi
    sed -i 's|^DB_DATABASE=.*|DB_DATABASE=/var/www/html/database/database.sqlite|g' /var/www/html/.env
    if ! grep -q "^DB_DATABASE=" /var/www/html/.env; then
        echo "DB_DATABASE=/var/www/html/database/database.sqlite" >> /var/www/html/.env
    fi
    export DB_CONNECTION=sqlite
    export DB_DATABASE=/var/www/html/database/database.sqlite
    php artisan config:clear || true
    php artisan config:cache || true
fi

echo "📦 Running database migrations and seeders..."
MAX_RETRIES=5
RETRY_COUNT=0
MIGRATION_SUCCESS=0

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    if php artisan migrate --force; then
        MIGRATION_SUCCESS=1
        php artisan db:seed --force || true
        break
    else
        RETRY_COUNT=$((RETRY_COUNT + 1))
        echo "⚠️ Database migration attempt $RETRY_COUNT/$MAX_RETRIES failed. Retrying in 4 seconds..."
        sleep 4
    fi
done

if [ $MIGRATION_SUCCESS -eq 0 ]; then
    echo "⚠️ Warning: Database migrations could not be completed after $MAX_RETRIES attempts."
fi

echo "🔒 Setting permissions for www-data and Nginx temp dirs..."
mkdir -p /var/lib/nginx/tmp /var/log/nginx /var/tmp/nginx /tmp /var/www/html/database
chown -R www-data:www-data /var/www/html /var/lib/nginx /var/log/nginx /var/tmp/nginx /tmp /var/www/html/database || true
chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database /var/lib/nginx /var/log/nginx /var/tmp/nginx /tmp || true

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