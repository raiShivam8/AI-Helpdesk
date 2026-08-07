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
        if (!$this->app->runningInConsole()) {
            try {
                $requestHost = request()->getHost();
                $isHttps = $this->app->environment('production')
                    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                    || request()->isSecure()
                    || str_starts_with((string) config('app.url'), 'https://');

                if ($isHttps) {
                    \Illuminate\Support\Facades\URL::forceScheme('https');
                }

                if (!empty($requestHost) && !in_array($requestHost, ['127.0.0.1', 'localhost'], true)) {
                    $scheme = $isHttps ? 'https' : 'http';
                    \Illuminate\Support\Facades\URL::forceRootUrl("{$scheme}://{$requestHost}");
                }
            } catch (\Throwable $e) {
                // Ignore URL binding exception in CLI/early boot
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
