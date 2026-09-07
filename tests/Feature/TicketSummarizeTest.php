<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature Test: TicketSummarizeTest
 *
 * Verifies security, caching, output structuring, and error handling
 * for the AI Summarize feature.
 */
class TicketSummarizeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Guests (unauthenticated users) cannot access the summarize endpoint.
     */
    public function test_guests_cannot_summarize_ticket(): void
    {
        $ticket = Ticket::factory()->create();

        $response = $this->postJson(route('tickets.summarize', $ticket));

        $response->assertStatus(302)
            ->assertRedirect('/login');
    }

    /**
     * Authenticated agent can summarize a ticket successfully.
     */
    public function test_authenticated_agent_can_summarize_ticket(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create([
            'subject' => 'Cannot reset password',
            'body' => 'I did not receive the reset link in my inbox.',
        ]);

        $mockJson = json_encode([
            'summary' => 'Customer is unable to reset password due to missing email.',
            'issues' => ['Password reset link not received'],
            'actions_taken' => [],
            'status' => 'Open',
            'next_step' => 'Verify email delivery and resend reset link.',
        ]);

        $this->mock(GeminiService::class, function ($mock) use ($mockJson) {
            $mock->shouldReceive('summarizeTicket')
                ->once()
                ->andReturn($mockJson);
        });

        $response = $this->actingAs($user)
            ->postJson(route('tickets.summarize', $ticket));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'summary' => [
                    'summary' => 'Customer is unable to reset password due to missing email.',
                    'issues' => ['Password reset link not received'],
                    'actions_taken' => [],
                    'status' => 'Open',
                    'next_step' => 'Verify email delivery and resend reset link.',
                ],
                'cached' => false,
            ]);
    }

    /**
     * Handles markdown code-block wrapped JSON cleanly.
     */
    public function test_handles_markdown_wrapped_json(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create([
            'subject' => 'Billing inquiry',
            'body' => 'I was charged twice for subscription.',
        ]);

        $wrappedJson = "```json\n" . json_encode([
            'summary' => 'Customer reports a double charge.',
            'issues' => ['Double billing'],
            'actions_taken' => [],
            'status' => 'Open',
            'next_step' => 'Issue refund for duplicate charge.',
        ]) . "\n```";

        $this->mock(GeminiService::class, function ($mock) use ($wrappedJson) {
            $mock->shouldReceive('summarizeTicket')
                ->once()
                ->andReturn($wrappedJson);
        });

        $response = $this->actingAs($user)
            ->postJson(route('tickets.summarize', $ticket));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'summary' => [
                    'summary' => 'Customer reports a double charge.',
                    'issues' => ['Double billing'],
                    'status' => 'Open',
                    'next_step' => 'Issue refund for duplicate charge.',
                ],
            ]);
    }

    /**
     * Summaries are cached and served from cache on subsequent calls.
     */
    public function test_summary_is_cached_and_served(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create();

        $mockJson = json_encode([
            'summary' => 'Initial summary',
            'issues' => ['Issue 1'],
            'actions_taken' => [],
            'status' => 'Open',
            'next_step' => 'Next step 1',
        ]);

        $this->mock(GeminiService::class, function ($mock) use ($mockJson) {
            // Should only be called once because the second request uses cache
            $mock->shouldReceive('summarizeTicket')
                ->once()
                ->andReturn($mockJson);
        });

        // 1st request - uncached
        $response1 = $this->actingAs($user)
            ->postJson(route('tickets.summarize', $ticket));

        $response1->assertStatus(200)
            ->assertJson(['cached' => false]);

        // 2nd request - cached
        $response2 = $this->actingAs($user)
            ->postJson(route('tickets.summarize', $ticket));

        $response2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'cached' => true,
                'summary' => [
                    'summary' => 'Initial summary',
                ],
            ]);
    }

    /**
     * Passing force=true bypasses cache and regenerates summary.
     */
    public function test_force_param_bypasses_cache(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create();

        $mockJson = json_encode([
            'summary' => 'Refreshed summary',
            'issues' => [],
            'actions_taken' => [],
            'status' => 'Open',
            'next_step' => 'Follow up',
        ]);

        $this->mock(GeminiService::class, function ($mock) use ($mockJson) {
            // Called twice because second request forces regeneration
            $mock->shouldReceive('summarizeTicket')
                ->twice()
                ->andReturn($mockJson);
        });

        $this->actingAs($user)->postJson(route('tickets.summarize', $ticket));

        $response = $this->actingAs($user)
            ->postJson(route('tickets.summarize', $ticket), ['force' => true]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'cached' => false,
            ]);
    }

    /**
     * Gemini API failure returns structured 500 error response.
     */
    public function test_gemini_api_error_returns_clean_500(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create();

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('summarizeTicket')
                ->once()
                ->andThrow(new \RuntimeException('Gemini API Error: Rate limit exceeded.'));
        });

        $response = $this->actingAs($user)
            ->postJson(route('tickets.summarize', $ticket));

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'error' => 'Gemini API Error: Rate limit exceeded.',
            ]);
    }
}
