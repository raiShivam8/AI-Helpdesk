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
    public function index(\Illuminate\Http\Request $request): View
    {
        $selectedAgentId = $request->query('agent_id');
        $agents = User::whereIn('role', [\App\Enums\Role::Agent, \App\Enums\Role::Admin])->orderBy('name')->get();
        $selectedAgent = $selectedAgentId ? User::find($selectedAgentId) : null;

        // Base query with agent filter
        $baseQuery = Ticket::query();
        if ($selectedAgentId) {
            if ($selectedAgentId === 'unassigned') {
                $baseQuery->whereNull('assigned_agent_id');
            } else if ($selectedAgent && !$selectedAgent->isAdmin()) {
                // Filter specifically for non-admin agents; Admins oversee overall company view
                $baseQuery->where('assigned_agent_id', $selectedAgentId);
            }
        }

        // ── Core metrics ──────────────────────────────────────────────────────

        $totalTickets = (clone $baseQuery)->count();
        $openTickets  = (clone $baseQuery)->where('status', TicketStatus::Open)->count();

        $aiResolvedCount   = (clone $baseQuery)->whereNotNull('ai_resolved_at')->count();
        $aiResolvedTickets = (string) $aiResolvedCount;
        $aiResolutionPct   = $totalTickets > 0 ? round(($aiResolvedCount / $totalTickets) * 100, 1) . '%' : '0%';

        $resolvedTickets = (clone $baseQuery)->where(function ($q) {
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
        // Use DATE() SQL comparison instead of whereBetween with Carbon objects
        // (Carbon objects don't compare correctly to ISO-format timestamps in SQLite)

        $startDateStr = now()->subDays(13)->format('Y-m-d');
        $endDateStr   = now()->format('Y-m-d');

        $rawCounts = (clone $baseQuery)
            ->whereRaw("DATE(created_at) >= ?  AND DATE(created_at) <= ?", [$startDateStr, $endDateStr])
            ->selectRaw("DATE(created_at) AS ticket_date, COUNT(*) AS total")
            ->groupBy('ticket_date')
            ->orderBy('ticket_date')
            ->pluck('total', 'ticket_date')
            ->toArray();

        $chartLabels = [];
        $chartData   = [];

        for ($i = 13; $i >= 0; $i--) {
            $date          = now()->subDays($i)->format('Y-m-d');
            $chartLabels[] = now()->subDays($i)->format('M j');
            $chartData[]   = (int) ($rawCounts[$date] ?? 0);
        }

        // ── Category & Status Breakdown ──────────────────────────────────────
        $rawCategoryCounts = (clone $baseQuery)
            ->select('category', DB::raw('COUNT(*) AS total'))
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

        if (array_sum($categoryData) === 0) {
            $categoryLabels = ['Technical Question', 'Refund Request', 'General Support', 'Account Issues'];
            $categoryData   = [0, 0, 0, 0];
        }

        $rawStatusCounts = (clone $baseQuery)
            ->select('status', DB::raw('COUNT(*) AS total'))
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

        // ── Recent Tickets (Paginated 8 per page: Page 1 shows tickets 1 to 8) ──
        // Eager-load 'replies' to avoid N+1 queries from the attachment badge check in blade
        $tickets = (clone $baseQuery)
            ->with(['assignedAgent', 'replies'])
            ->orderBy('id', 'desc')
            ->paginate(8)
            ->withQueryString();

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
            'agents',
            'selectedAgentId',
            'selectedAgent'
        ));
    }
}
