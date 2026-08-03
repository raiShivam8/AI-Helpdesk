<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;

use Sentry\Event;
use Sentry\EventHint;

use Tests\TestCase;

class SentryMonitoringTest extends TestCase
{
    /**
     * Test Sentry configuration loading.
     */
    public function test_sentry_config_is_loaded(): void
    {
        $this->assertNotEmpty(config('sentry.dsn'));
        $this->assertEquals(1.0, config('sentry.traces_sample_rate'));
        $this->assertFalse(config('sentry.send_default_pii'));
    }

    /**
     * Test Sentry service provider registration in Laravel container.
     */
    public function test_sentry_service_is_bound_in_container(): void
    {
        $this->assertTrue(app()->bound('sentry'));
    }

    /**
     * Test Sentry before_send callback filters sensitive parameters.
     */
    public function test_sentry_before_send_callback_scrubs_sensitive_data(): void
    {
        $beforeSend = config('sentry.before_send');

        $this->assertIsCallable($beforeSend);

        $event = Event::createEvent();
        $event->setExtra([
            'GEMINI_API_KEY' => 'secret-gemini-key-12345',
            'DB_PASSWORD' => 'super-secret-db-pass',
            'safe_param' => 'public_value',
        ]);

        $event->setRequest([
            'headers' => [
                'authorization' => 'Bearer token-123',
                'x-webhook-token' => 'secret-webhook-key',
                'content-type' => 'application/json',
            ],
        ]);

        /** @var Event $filteredEvent */
        $filteredEvent = $beforeSend($event, null);

        $extra = $filteredEvent->getExtra();
        $this->assertEquals('[FILTERED]', $extra['GEMINI_API_KEY']);
        $this->assertEquals('[FILTERED]', $extra['DB_PASSWORD']);
        $this->assertEquals('public_value', $extra['safe_param']);

        $request = $filteredEvent->getRequest();
        $this->assertEquals('[FILTERED]', $request['headers']['authorization']);
        $this->assertEquals('[FILTERED]', $request['headers']['x-webhook-token']);
        $this->assertEquals('application/json', $request['headers']['content-type']);
    }

    /**
     * Test exception reporting via report() helper.
     */
    public function test_exceptions_can_be_reported_without_crashing(): void
    {
        try {
            throw new \RuntimeException('Sentry test exception for monitoring verification');
        } catch (\Throwable $e) {
            report($e);
            $this->assertTrue(true);
        }
    }
}
