<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more.
    |
    */


    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'inbound_email' => [
        'secret' => env('WEBHOOK_SECRET'),
        'api_base_url' => env('API_BASE_URL'),
    ],


    'test_recipient_email' => env('TEST_RECIPIENT_EMAIL'),

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
        'timeout' => env('GEMINI_TIMEOUT', 30),
        'connect_timeout' => env('GEMINI_CONNECT_TIMEOUT', 10),
        'proxy' => env('GEMINI_PROXY'),
        'ip_resolve' => env('GEMINI_IP_RESOLVE'),
        'cache_store' => env('GEMINI_CACHE_STORE', 'file'),
        'cache_ttl' => (int) env('GEMINI_CACHE_TTL', 86400),
    ],

];