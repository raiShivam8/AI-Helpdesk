<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Enums\Role;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature Test: GeminiReplyPolishTest
 *
 * Verifies the security, validation, integration, and error handling
 * of the AI Polish Reply feature using container mocking.
 */
class GeminiReplyPolishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Guests (unauthenticated users) cannot access the polish endpoint.
     */
    public function test_guests_cannot_polish_reply(): void
    {
        $ticket = Ticket::factory()->create();

        $response = $this->postJson(route('tickets.polish-reply', $ticket), [
            'body' => 'Original text',
        ]);

        $response->assertStatus(302)
            ->assertRedirect('/login');
    }

    /**
     * The reply body must not be empty.
     */
    public function test_body_is_required_to_polish_reply(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('tickets.polish-reply', $ticket), [
                'body' => '   ',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    /**
     * The reply body must not exceed the 2,000 character limit.
     */
    public function test_body_cannot_exceed_max_length(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create();

        $longBody = str_repeat('a', 2001);

        $response = $this->actingAs($user)
            ->postJson(route('tickets.polish-reply', $ticket), [
                'body' => $longBody,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    /**
     * An authenticated agent can successfully polish a reply when GeminiService is OK.
     */
    public function test_authenticated_agent_can_polish_reply(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create();

        // Mock GeminiService to verify it is called with correct text and returns the expected result
        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('polishReply')
                ->once()
                ->with('i checked your account.')
                ->andReturn('Dear customer, I have checked your account.');
        });

        $response = $this->actingAs($user)
            ->postJson(route('tickets.polish-reply', $ticket), [
                'body' => 'i checked your account.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'polished' => 'Dear customer, I have checked your account.',
            ]);
    }

    /**
     * An appropriate error is returned if the Gemini API key is missing.
     */
    public function test_missing_gemini_api_key_returns_error(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create();

        // Mock GeminiService to simulate configuration error
        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('polishReply')
                ->once()
                ->with('Draft text to polish')
                ->andThrow(new \RuntimeException('Gemini API key is not configured. Please add GEMINI_API_KEY to your .env file.'));
        });

        $response = $this->actingAs($user)
            ->postJson(route('tickets.polish-reply', $ticket), [
                'body' => 'Draft text to polish',
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Gemini API key is not configured. Please add GEMINI_API_KEY to your .env file.',
            ]);
    }

    /**
     * An API failure response from Gemini is handled gracefully.
     */
    public function test_api_error_returns_clean_500_response(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create();

        // Mock GeminiService to simulate general API error
        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('polishReply')
                ->once()
                ->with('Draft text to polish')
                ->andThrow(new \RuntimeException('Gemini API Error: API key not valid. Please pass a valid API key.'));
        });

        $response = $this->actingAs($user)
            ->postJson(route('tickets.polish-reply', $ticket), [
                'body' => 'Draft text to polish',
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Gemini API Error: API key not valid. Please pass a valid API key.',
            ]);
    }

    /**
     * A rate limit error (429) from Gemini API is returned as a standard API error.
     */
    public function test_rate_limit_error_returns_raw_api_error(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create();

        // Mock GeminiService to throw general API error for rate limits
        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('polishReply')
                ->once()
                ->with('Draft text to polish')
                ->andThrow(new \RuntimeException('Gemini API Error: Resource has been exhausted (e.g. queries per minute).'));
        });

        $response = $this->actingAs($user)
            ->postJson(route('tickets.polish-reply', $ticket), [
                'body' => 'Draft text to polish',
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Gemini API Error: Resource has been exhausted (e.g. queries per minute).',
            ]);
    }
}
