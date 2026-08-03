web: php artisan storage:link --force 2>/dev/null; php artisan migrate --force; php artisan config:cache; php artisan route:cache; php artisan view:cache; php-fpm -D && nginx -g "daemon off;"
worker: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
scheduler: php artisan schedule:work
