<?php

namespace App\Http\Controllers;

use App\Enums\SenderType;
use App\Http\Requests\StoreTicketReplyRequest;
use App\Models\Ticket;
use App\Services\GeminiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller: TicketReplyController
 *
 * Handles storing new replies against an existing ticket.
 *
 * Route binding:
 *   POST /tickets/{ticket}/replies  →  store()
 *
 * This controller is intentionally narrow — it does one thing (store a reply)
 * and delegates all body validation to StoreTicketReplyRequest, keeping the
 * controller lean and following the Single Responsibility Principle.
 *
 * sender_type determination:
 *   Every reply submitted through the support portal is always by an
 *   authenticated agent or admin, so sender_type is always SenderType::Agent
 *   here.  Customer replies (SenderType::Customer) are reserved for future
 *   inbound-email or customer-portal integrations and would be set in their
 *   own dedicated controller/action.
 */
class TicketReplyController extends Controller
{
    /**
     * Store a new reply for the given ticket.
     *
     * Flow:
     *  1. StoreTicketReplyRequest validates the body field before this
     *     method is entered (Laravel's form-request pipeline).
     *  2. sender_type is derived automatically from context (portal login
     *     → always Agent). No user input is trusted for this field.
     *  3. The reply is created via the ticket's replies() relationship so
     *     ticket_id is set automatically — no risk of mismatching.
     *  4. Redirect back to the ticket detail page with a success flash.
     *
     * @param  StoreTicketReplyRequest  $request  — already validated
     * @param  Ticket                  $ticket   — route-model-bound ticket
     */
    public function store(StoreTicketReplyRequest $request, Ticket $ticket, \App\Services\TicketEmailService $ticketEmailService): RedirectResponse
    {
        // SenderType is resolved from context, NOT from user input.
        // Authenticated portal users are always agents/admins → SenderType::Agent.
        $reply = $ticket->replies()->create([
            'user_id'     => $request->user()->id,
            'body'        => $request->validated()['body'],
            'sender_type' => SenderType::Agent,
        ]);

        // Send email response to customer automatically via Laravel Mail SMTP
        $ticketEmailService->sendTicketReplyEmail($ticket, $reply);

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Your reply has been posted successfully.');
    }

    /**
     * Polish a draft reply using the Gemini API.
     *
     * @param  Request        $request
     * @param  Ticket         $ticket
     * @param  GeminiService  $geminiService
     * @return JsonResponse
     */
    public function polish(Request $request, Ticket $ticket, GeminiService $geminiService): JsonResponse
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'body' => ['required', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first('body'),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $polished = $geminiService->polishReply($request->input('body'));

            return response()->json([
                'polished' => $polished,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
