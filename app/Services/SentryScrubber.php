<?php

namespace App\Services;

use Sentry\Event;
use Sentry\EventHint;

class SentryScrubber
{
    /**
     * Scrub sensitive keys, API keys, passwords, and tokens before sending event to Sentry.
     */
    public static function beforeSend(Event $event, ?EventHint $hint): ?Event
    {
        $sensitiveKeys = [
            'GEMINI_API_KEY',
            'MAIL_PASSWORD',
            'IMAP_PASSWORD',
            'DB_PASSWORD',
            'WEBHOOK_SECRET',
            'password',
            'password_confirmation',
            'secret',
            'token',
            'X-Webhook-Token',
            'Authorization',
        ];

        // 1. Scrub extra data context
        $extra = $event->getExtra();
        foreach ($sensitiveKeys as $key) {
            if (isset($extra[$key])) {
                $extra[$key] = '[FILTERED]';
            }
        }
        $event->setExtra($extra);

        // 2. Scrub request headers & parameters if present
        $request = $event->getRequest();
        if (!empty($request['headers'])) {
            foreach (['authorization', 'x-webhook-token'] as $headerKey) {
                if (isset($request['headers'][$headerKey])) {
                    $request['headers'][$headerKey] = '[FILTERED]';
                }
            }
            $event->setRequest($request);
        }

        return $event;
    }
}
