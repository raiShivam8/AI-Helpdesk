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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enforce relative asset URL paths so Vite CSS/JS assets always load on any domain or proxy
        config(['app.asset_url' => config('app.asset_url', '/')]);

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
    }
}
