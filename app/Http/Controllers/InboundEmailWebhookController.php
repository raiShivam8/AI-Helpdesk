<?php

namespace App\Http\Controllers;

use App\Actions\CreateTicketFromInboundEmailAction;
use App\Http\Requests\StoreInboundEmailRequest;
use Illuminate\Http\JsonResponse;

class InboundEmailWebhookController extends Controller
{
    public function __construct(
        protected CreateTicketFromInboundEmailAction $createTicketAction
    ) {}

    /**
     * Handle the inbound email webhook.
     */
    public function handle(StoreInboundEmailRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $ticket = $this->createTicketAction->execute($validated);

            return response()->json([
                'message' => 'Ticket created successfully.',
                'ticket'  => $ticket,
            ], 201);
        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
