web: php artisan storage:link --force 2>/dev/null; php artisan migrate --force || true; php artisan db:seed --force || true; php artisan config:cache || true; php artisan route:cache || true; php artisan view:cache || true; php-fpm -D && nginx -g "daemon off;"
worker: php -d max_execution_time=0 artisan queue:work --sleep=3 --tries=3 --timeout=120 --max-time=3600
scheduler: php artisan schedule:work
