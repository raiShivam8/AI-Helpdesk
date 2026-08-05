<?php

use Sentry\Event;
use Sentry\EventHint;

/**
 * Sentry Laravel SDK configuration file.
 *
 * @see https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/
 */
return [

    // DSN from environment variables
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // Release and Environment
    'release' => env('SENTRY_RELEASE'),
    'environment' => env('SENTRY_ENVIRONMENT'),

    // Sampling Rates
    'sample_rate' => env('SENTRY_SAMPLE_RATE') === null ? 1.0 : (float) env('SENTRY_SAMPLE_RATE'),
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE') === null ? 1.0 : (float) env('SENTRY_TRACES_SAMPLE_RATE'),
    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE') === null ? null : (float) env('SENTRY_PROFILES_SAMPLE_RATE'),

    // Do not send default PII unless explicitly allowed
    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),

    // Ignore Health check route
    'ignore_transactions' => [
        '/up',
    ],

    // Before Send Callback: Scrub sensitive keys, API keys, passwords, and tokens
    'before_send' => [\App\Services\SentryScrubber::class, 'beforeSend'],

    // Breadcrumbs configuration
    'breadcrumbs' => [
        'logs' => env('SENTRY_BREADCRUMBS_LOGS_ENABLED', true),
        'cache' => env('SENTRY_BREADCRUMBS_CACHE_ENABLED', true),
        'livewire' => env('SENTRY_BREADCRUMBS_LIVEWIRE_ENABLED', true),
        'sql_queries' => env('SENTRY_BREADCRUMBS_SQL_QUERIES_ENABLED', true),
        'sql_bindings' => env('SENTRY_BREADCRUMBS_SQL_BINDINGS_ENABLED', false),
        'queue_info' => env('SENTRY_BREADCRUMBS_QUEUE_INFO_ENABLED', true),
        'command_info' => env('SENTRY_BREADCRUMBS_COMMAND_JOBS_ENABLED', true),
        'http_client_requests' => env('SENTRY_BREADCRUMBS_HTTP_CLIENT_REQUESTS_ENABLED', true),
        'notifications' => env('SENTRY_BREADCRUMBS_NOTIFICATIONS_ENABLED', true),
    ],

    // Performance Tracing configuration
    'tracing' => [
        'queue_job_transactions' => env('SENTRY_TRACE_QUEUE_ENABLED', true),
        'queue_jobs' => env('SENTRY_TRACE_QUEUE_JOBS_ENABLED', true),
        'sql_queries' => env('SENTRY_TRACE_SQL_QUERIES_ENABLED', true),
        'sql_bindings' => env('SENTRY_TRACE_SQL_BINDINGS_ENABLED', false),
        'views' => env('SENTRY_TRACE_VIEWS_ENABLED', true),
        'http_client_requests' => env('SENTRY_TRACE_HTTP_CLIENT_REQUESTS_ENABLED', true),
        'cache' => env('SENTRY_TRACE_CACHE_ENABLED', true),
        'default_integrations' => env('SENTRY_TRACE_DEFAULT_INTEGRATIONS_ENABLED', true),
    ],

];
