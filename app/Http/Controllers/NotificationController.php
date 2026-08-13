<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get recent notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // If user has 0 notifications, auto-populate notifications from recent tickets so feed is ready
        if ($user->appNotifications()->count() === 0) {
            $recentTickets = \App\Models\Ticket::latest()->take(10)->get();
            foreach ($recentTickets as $ticket) {
                $user->appNotifications()->create([
                    'title'      => "Ticket #{$ticket->id}: {$ticket->subject}",
                    'message'    => "Customer {$ticket->sender_name} ({$ticket->sender_email})",
                    'link'       => route('tickets.show', $ticket),
                    'type'       => 'ticket_created',
                    'created_at' => $ticket->created_at,
                ]);
            }
        }

        $notifications = $user->appNotifications()
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($n) {
                $typeLabel = match($n->type) {
                    'ticket_created' => 'New Ticket',
                    'ticket_reply'   => 'Ticket Reply',
                    'ticket_transfer'=> 'Transfer',
                    'ai_resolved'    => 'AI Resolved',
                    default          => 'Notification'
                };

                return [
                    'id'               => $n->id,
                    'title'            => $n->title,
                    'message'          => $n->message,
                    'link'             => $n->link,
                    'type'             => $n->type,
                    'type_label'       => $typeLabel,
                    'read_at'          => $n->read_at,
                    'created_at'       => $n->created_at->toIso8601String(),
                    'created_at_human' => $n->created_at->diffForHumans(),
                ];
            });

        $unreadCount = $user->appNotifications()
            ->unread()
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, AppNotification $notification): RedirectResponse|JsonResponse
    {
        if ($notification->user_id === $request->user()->id) {
            $notification->markAsRead();
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }

    /**
     * Mark all notifications for the authenticated user as read.
     */
    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()
            ->appNotifications()
            ->unread()
            ->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'All notifications marked as read.');
    }
}
