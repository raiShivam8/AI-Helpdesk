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
        if ($this->app->environment('production') || str_starts_with((string) config('app.url'), 'https://') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        if (request()->hasHeader('x-forwarded-host')) {
            $proxyHost = request()->header('x-forwarded-host');
            if (!empty($proxyHost) && !in_array($proxyHost, ['127.0.0.1', 'localhost'], true)) {
                $scheme = (request()->header('x-forwarded-proto') === 'https' || $this->app->environment('production')) ? 'https' : 'http';
                \Illuminate\Support\Facades\URL::forceRootUrl("{$scheme}://{$proxyHost}");
            }
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
