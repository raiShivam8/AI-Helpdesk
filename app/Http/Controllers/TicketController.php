<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Enums\Role;
use App\Http\Requests\UpdateTicketAssignmentRequest;
use App\Http\Requests\UpdateTicketRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use App\Services\GeminiService;
use App\Jobs\AutoResolveTicketJob;
use Illuminate\Support\Facades\Cache;

class TicketController extends Controller
{
    /**
     * Display a listing of the tickets.
     */
    public function index(Request $request): View
    {
        $validatedFilters = $request->validate([
            'status'   => ['sometimes', 'nullable', 'string', Rule::enum(TicketStatus::class)],
            'category' => ['sometimes', 'nullable', 'string', Rule::enum(TicketCategory::class)],
            'search'   => ['sometimes', 'nullable', 'string', 'max:200'],
            'agent'    => [
                'sometimes',
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if ($value !== '' && $value !== null && $value !== 'unassigned' && !User::where('id', $value)->exists()) {
                        $fail('The selected agent is invalid.');
                    }
                }
            ],
        ]);

        $status   = $validatedFilters['status']   ?? null;
        $category = $validatedFilters['category'] ?? null;
        $agent    = $validatedFilters['agent']    ?? null;
        $search   = trim($validatedFilters['search'] ?? '');
        $search   = $search === '' ? null : $search;

        $sort = $request->query('sort', 'created_at');
        $direction = $request->query('direction', 'desc');

        // Strict fallback for SQL injection prevention and robustness
        if (!in_array($sort, ['id', 'subject', 'sender_name', 'status', 'category', 'created_at'])) {
            $sort = 'created_at';
        }
        if (!in_array($direction, ['asc', 'desc'])) {
            $direction = 'desc';
        }

        $query = Ticket::with('assignedAgent');

        // Apply filters if not empty
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        if ($category !== null && $category !== '') {
            $query->where('category', $category);
        }

        if ($agent !== null && $agent !== '') {
            if ($agent === 'unassigned') {
                $query->whereNull('assigned_agent_id');
            } else {
                $query->where('assigned_agent_id', $agent);
            }
        }

        // Full-text search across subject, body, sender name, and sender email.
        // Uses LIKE with a leading wildcard so it matches anywhere in the field.
        if ($search !== null) {
            $query->where(function ($q) use ($search) {
                $term = '%' . $search . '%';
                $q->where('subject',      'like', $term)
                  ->orWhere('body',        'like', $term)
                  ->orWhere('sender_name', 'like', $term)
                  ->orWhere('sender_email','like', $term);
            });
        }

        $tickets = $query->orderBy($sort, $direction)->paginate(10)->withQueryString();

        // Get all users who can be assigned (to populate agent filter)
        $agents = User::orderBy('name')->get();

        return view('tickets.index', compact('tickets', 'sort', 'direction', 'status', 'category', 'agent', 'agents', 'search'));
    }

    /**
     * Display the specified ticket.
     */
    public function show(Ticket $ticket): View
    {
        // Eager-load the assigned agent and the full reply thread (with author)
        // in a single query each to avoid N+1 problems.
        $ticket->load([
            'assignedAgent',
            'replies.user',   // loads all replies + each reply's author
        ]);

        $agents = [];
        if (auth()->user()->isAdmin()) {
            $agents = User::where('role', Role::Agent)->orderBy('name')->get();
        }

        return view('tickets.show', compact('ticket', 'agents'));
    }

    /**
     * Assign the ticket to an agent.
     */
    public function assign(UpdateTicketAssignmentRequest $request, Ticket $ticket): RedirectResponse
    {
        $ticket->update([
            'assigned_agent_id' => $request->validated()['assigned_agent_id'],
        ]);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket assignment updated successfully.');
    }

    /**
     * Update the specified ticket's status and category.
     */
    public function update(UpdateTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validated();

        if (isset($data['status'])) {
            if ($data['status'] === TicketStatus::Resolved->value && !$ticket->resolved_at) {
                $data['resolved_at'] = now();
            } elseif ($data['status'] !== TicketStatus::Resolved->value && $ticket->resolved_at) {
                $data['resolved_at'] = null;
            }
        }

        $ticket->update($data);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket updated successfully.');
    }

    /**
     * Generate an AI-powered summary for the specified ticket.
     *
     * @param  \App\Models\Ticket          $ticket
     * @param  \App\Services\GeminiService  $geminiService
     * @return \Illuminate\Http\JsonResponse
     */
    public function summarize(Request $request, Ticket $ticket, GeminiService $geminiService): \Illuminate\Http\JsonResponse
    {
        // Eager load the replies and their authors to avoid N+1 queries
        $ticket->load('replies.user');

        // Form conversation array from ticket replies in chronological order
        $conversation = $ticket->replies->map(function ($reply) use ($ticket) {
            return [
                'sender' => $reply->sender_type === \App\Enums\SenderType::Agent
                    ? ($reply->user?->name ?? 'Agent')
                    : $ticket->sender_name,
                'role' => $reply->sender_type->value,
                'body' => $reply->body,
            ];
        })->toArray();

        // Compute state-based cache key
        $ticketId = $ticket->id;
        $ticketUpdated = $ticket->updated_at?->timestamp ?? 0;
        $repliesCount = $ticket->replies->count();
        $lastReplyUpdated = $ticket->replies->max('updated_at')?->timestamp ?? 0;

        $cacheKey = "gemini:summary:{$ticketId}:{$ticketUpdated}:{$repliesCount}:{$lastReplyUpdated}";
        $cacheStore = config('services.gemini.cache_store', 'file');
        $cacheTtl = (int) config('services.gemini.cache_ttl', 86400);
        $force = filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN);

        try {
            // Check cache if not forcing regeneration
            if (!$force && $cacheTtl > 0) {
                try {
                    if (Cache::store($cacheStore)->has($cacheKey)) {
                        $cachedSummary = Cache::store($cacheStore)->get($cacheKey);
                        if (is_array($cachedSummary)) {
                            return response()->json([
                                'success' => true,
                                'summary' => $cachedSummary,
                                'cached' => true,
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    // Fall back to direct regeneration on cache read failure
                    \Illuminate\Support\Facades\Log::warning('Summary cache read failed, regenerating', [
                        'exception' => $e->getMessage()
                    ]);
                }
            }

            // Call the summarizeTicket method on the GeminiService
            $summaryJson = $geminiService->summarizeTicket(
                $ticket->subject,
                $ticket->body,
                $conversation
            );

            // Decode the response to ensure it is valid JSON
            $decodedSummary = json_decode($summaryJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('AI returned invalid structured output.');
            }

            // Store summary in cache
            if ($cacheTtl > 0) {
                try {
                    Cache::store($cacheStore)->put($cacheKey, $decodedSummary, $cacheTtl);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Summary cache write failed', [
                        'exception' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'summary' => $decodedSummary,
                'cached' => false,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Trigger AI auto-resolution for an existing open ticket.
     */
    public function tryAiResolve(Ticket $ticket): RedirectResponse
    {
        if ($ticket->ai_resolved_at !== null) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'This ticket has already been resolved by AI.');
        }

        if ($ticket->status !== TicketStatus::Open && $ticket->status !== TicketStatus::New && $ticket->status !== TicketStatus::Processing) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'AI auto-resolution can only be attempted for open or new tickets.');
        }

        // Execute auto-resolution immediately using knowledge-base.md
        AutoResolveTicketJob::dispatchSync($ticket);

        $ticket->refresh();
        if ($ticket->ai_resolved_at !== null) {
            return redirect()->route('tickets.show', $ticket)
                ->with('success', 'Ticket successfully auto-resolved by AI using knowledge-base.md! The customer has been notified via email.');
        }

        return redirect()->route('tickets.show', $ticket)
            ->with('info', 'AI evaluated the ticket against knowledge-base.md, but the query requires human agent review.');
    }
}
