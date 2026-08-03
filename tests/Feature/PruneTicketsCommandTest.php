<?php

namespace Tests\Feature;

use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneTicketsCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that tickets:prune keeps only the specified number of latest tickets.
     */
    public function test_prune_tickets_command_removes_excess_tickets(): void
    {
        // Create 105 tickets
        Ticket::factory()->count(105)->create();

        $this->assertEquals(105, Ticket::count());

        // Run the prune command
        $this->artisan('tickets:prune', ['--keep' => 100])
            ->expectsOutputToContain('Successfully pruned 5 older ticket(s). Exactly 100 tickets remain in the ticket section.')
            ->assertExitCode(0);

        // Assert exactly 100 tickets remain
        $this->assertEquals(100, Ticket::count());
    }
}
