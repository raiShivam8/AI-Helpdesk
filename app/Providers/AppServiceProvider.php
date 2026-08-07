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
        if (!$this->app->runningInConsole() && !$this->app->runningUnitTests()) {
            try {
                $host = request()->header('x-forwarded-host') ?? request()->header('host') ?? request()->getHost();
                $host = explode(':', (string) $host)[0];

                if (!empty($host) && !in_array($host, ['127.0.0.1', 'localhost'], true)) {
                    $isHttps = $this->app->environment('production')
                        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                        || request()->header('x-forwarded-proto') === 'https'
                        || request()->isSecure();

                    $scheme = $isHttps ? 'https' : 'http';
                    $currentUrl = "{$scheme}://{$host}";

                    config(['app.url' => $currentUrl]);
                    \Illuminate\Support\Facades\URL::forceRootUrl($currentUrl);
                    if ($isHttps) {
                        \Illuminate\Support\Facades\URL::forceScheme('https');
                    }
                }
            } catch (\Throwable $e) {
                // Ignore URL binding exception
            }
        } elseif ($this->app->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
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
