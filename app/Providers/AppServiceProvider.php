<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 1. Ensure Render bare postgres host is dynamically converted to reachable regional domain
        $region = env('RENDER_REGION', 'oregon');
        $host = config('database.connections.pgsql.host');
        if ($host && str_starts_with($host, 'dpg-') && !str_contains($host, '.')) {
            config(['database.connections.pgsql.host' => "{$host}.{$region}-postgres.render.com"]);
        }

        $url = config('database.connections.pgsql.url');
        if ($url && preg_match('/@(dpg-[a-z0-9]+-a)(:[0-9]+|\/|$)/i', $url, $m)) {
            $resolvedUrl = str_replace('@' . $m[1], "@{$m[1]}.{$region}-postgres.render.com", $url);
            config(['database.connections.pgsql.url' => $resolvedUrl]);
        }

        // 2. Safe defaults: Use file driver for session/cache if running on cloud container to prevent crash on unmigrated DB
        if (config('session.driver') === 'database' && env('FORCE_DB_SESSION', false) !== true) {
            config(['session.driver' => 'file']);
        }
        if (config('cache.default') === 'database' && env('FORCE_DB_CACHE', false) !== true) {
            config(['cache.default' => 'file']);
        }

        // 3. Enable debug mode if specified or default during setup
        if (env('APP_DEBUG', true)) {
            config(['app.debug' => true]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enforce relative asset URL paths so Vite CSS/JS assets always load on any domain or proxy
        config(['app.asset_url' => null]);

        if ($this->app->environment('production') || str_starts_with((string) config('app.url'), 'https://') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        if ($this->app->runningInConsole()) {
            @set_time_limit(0);
            @ini_set('max_execution_time', '0');
        }

        \Illuminate\Support\Facades\Gate::define('view-users', function (\App\Models\User $user) {
            return $user->isAdmin();
        });

        // High-availability Database connection check with automatic SQLite fallback (production/runtime only)
        if (!$this->app->runningUnitTests()) {
            $defaultConn = config('database.default');
            $sqlitePath = database_path('database.sqlite');
            if (!file_exists($sqlitePath)) {
                @touch($sqlitePath);
                @chmod($sqlitePath, 0777);
            }

            if ($defaultConn !== 'sqlite') {
                try {
                    \Illuminate\Support\Facades\DB::connection($defaultConn)->getPdo();
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Primary DB connection ({$defaultConn}) unreachable: " . $e->getMessage() . ". Switching to SQLite fallback.");
                    config([
                        'database.default' => 'sqlite',
                        'database.connections.sqlite.database' => $sqlitePath,
                    ]);
                    \Illuminate\Support\Facades\DB::purge();
                }
            }
        }

        // Ensure database tables and seed data exist (skip during automated tests)
        if (!$this->app->runningUnitTests()) {
            try {
                if (!\Illuminate\Support\Facades\Schema::hasTable('users') || \App\Models\User::count() === 0) {
                    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
                    \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AppServiceProvider auto-migration notice: ' . $e->getMessage());
            }
        }
    }
}
