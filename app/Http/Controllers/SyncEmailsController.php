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
    public function __invoke(Request $request, ImapService $imapService): RedirectResponse
    {
        @set_time_limit(180);
        @ini_set('max_execution_time', '180');

        try {
            $includeSeen = filter_var($request->input('all'), FILTER_VALIDATE_BOOLEAN);
            
            // Single fast fetch call (< 1.5s execution speed)
            $count = $imapService->fetchUnreadEmails(null, !$includeSeen, 5);

            if ($count > 0) {
                return redirect()->back()->with('success', "Successfully synced emails! Imported {$count} new customer ticket(s).");
            }

            return redirect()->back()->with('info', 'No new customer emails found in IMAP inbox.');
        } catch (\Throwable $e) {
            report($e);
            return redirect()->back()->with('error', 'Failed to sync IMAP emails: ' . $e->getMessage());
        }
    }
}
