<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Enums\TicketStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with analytics metrics and chart data.
     */
    public function index(): View
    {
        // ── Core metrics ──────────────────────────────────────────────────────

        $totalTickets = Ticket::count();
        $openTickets  = Ticket::where('status', TicketStatus::Open)->count();

        $aiResolvedCount   = Ticket::whereNotNull('ai_resolved_at')->count();
        $aiResolvedTickets = (string) $aiResolvedCount;
        $aiResolutionPct   = $totalTickets > 0 ? round(($aiResolvedCount / $totalTickets) * 100, 1) . '%' : '0%';

        $resolvedTickets = Ticket::where(function ($q) {
            $q->whereNotNull('resolved_at')
              ->orWhereNotNull('ai_resolved_at')
              ->orWhereIn('status', [TicketStatus::Resolved, TicketStatus::Closed]);
        })->get();

        if ($resolvedTickets->count() > 0) {
            $totalSeconds = $resolvedTickets->sum(function ($t) {
                $endTime = $t->resolved_at ?? $t->ai_resolved_at ?? $t->updated_at;
                return $t->created_at ? max(300, $t->created_at->diffInSeconds($endTime)) : 0;
            });
            $avgSeconds = $totalSeconds / $resolvedTickets->count();

            if ($avgSeconds < 3600) {
                $avgResolutionHours = max(1, round($avgSeconds / 60)) . ' mins';
            } else {
                $avgResolutionHours = round($avgSeconds / 3600, 1) . ' hrs';
            }
        } else {
            $avgResolutionHours = '0 hrs';
        }

        // ── Chart data: tickets created per day for last 14 days ──────────────

        // Generate a complete series of the last 14 days (including gaps with 0)
        $today = now()->startOfDay();
        $start = now()->subDays(13)->startOfDay();

        // Fetch actual counts from DB grouped by date
        $rawCounts = Ticket::whereBetween('created_at', [$start, $today->copy()->endOfDay()])
            ->selectRaw("DATE(created_at) AS ticket_date, COUNT(*) AS total")
            ->groupBy('ticket_date')
            ->orderBy('ticket_date')
            ->pluck('total', 'ticket_date')
            ->toArray();

        // Build a zero-filled 14-day series
        $chartLabels = [];
        $chartData   = [];

        for ($i = 13; $i >= 0; $i--) {
            $date           = now()->subDays($i)->format('Y-m-d');
            $chartLabels[]  = now()->subDays($i)->format('M j');
            $chartData[]    = (int) ($rawCounts[$date] ?? 0);
        }

        // ── Category & Status Breakdown (For Industrial Doughnut/Pie Analytics) ──────
        $rawCategoryCounts = Ticket::select('category', DB::raw('COUNT(*) AS total'))
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();

        $categoryLabels = [];
        $categoryData   = [];

        foreach (\App\Enums\TicketCategory::cases() as $cat) {
            $val   = $cat->value;
            $count = (int) ($rawCategoryCounts[$val] ?? 0);
            if ($count > 0 || empty($rawCategoryCounts)) {
                $categoryLabels[] = $val;
                $categoryData[]   = $count;
            }
        }

        // If no categorized tickets yet, add fallback categories for empty state visualization
        if (array_sum($categoryData) === 0) {
            $categoryLabels = ['Technical Question', 'Refund Request', 'General Support', 'Account Issues'];
            $categoryData   = [0, 0, 0, 0];
        }

        $rawStatusCounts = Ticket::select('status', DB::raw('COUNT(*) AS total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $statusLabels = [];
        $statusData   = [];

        foreach (\App\Enums\TicketStatus::cases() as $st) {
            $val   = $st->value;
            $count = (int) ($rawStatusCounts[$val] ?? 0);
            $statusLabels[] = ucfirst($val);
            $statusData[]   = $count;
        }

        // ── Recent Tickets (Paginated 10 per page, up to 100 total) ─────────────
        $tickets = Ticket::with('assignedAgent')
            ->latest()
            ->paginate(10);

        return view('dashboard', compact(
            'totalTickets',
            'openTickets',
            'aiResolvedTickets',
            'aiResolutionPct',
            'avgResolutionHours',
            'chartLabels',
            'chartData',
            'categoryLabels',
            'categoryData',
            'statusLabels',
            'statusData',
            'tickets',
        ));
    }
}
