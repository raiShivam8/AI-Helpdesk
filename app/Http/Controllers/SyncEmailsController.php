<?php

namespace App\Http\Controllers;

use App\Services\ImapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SyncEmailsController extends Controller
{
    /**
     * Instantly trigger IMAP email sync from dashboard/ticket queue.
     */
    public function __invoke(Request $request, ImapService $imapService): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        @set_time_limit(180);
        @ini_set('max_execution_time', '180');

        try {
            $includeSeen = filter_var($request->input('all'), FILTER_VALIDATE_BOOLEAN);

            if ($request->has('reset')) {
                $imapService->resetLastProcessedUid();
            }

            // Fast web sync: Ticket and reply DB records are created instantly; AI classification runs asynchronously in background
            $count = $imapService->fetchUnreadEmails(null, !$includeSeen, 20, true);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'count'   => $count,
                    'message' => $count > 0 ? "Successfully synced emails! Imported {$count} new customer ticket(s)." : "No new customer emails found in IMAP inbox.",
                ]);
            }

            if ($count > 0) {
                return redirect()->back()->with('success', "Successfully synced emails! Imported {$count} new customer ticket(s).");
            }

            return redirect()->back()->with('info', 'No new customer emails found in IMAP inbox.');
        } catch (\Throwable $e) {
            report($e);
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'count'   => 0,
                    'error'   => $e->getMessage(),
                ], 500);
            }
            return redirect()->back()->with('error', 'Failed to sync IMAP emails: ' . $e->getMessage());
        }
    }
}
