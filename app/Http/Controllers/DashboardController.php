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
                $aiAgent = User::withTrashed()->where('email', \Database\Seeders\AiAgentSeeder::EMAIL)->first();
                if ($aiAgent && (int) $selectedAgentId === (int) $aiAgent->id) {
                    $baseQuery->where(function ($q) use ($selectedAgentId) {
                        $q->where('assigned_agent_id', $selectedAgentId)
                          ->orWhereNotNull('ai_resolved_at');
                    });
                } else {
                    $baseQuery->where('assigned_agent_id', $selectedAgentId);
                }
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
        $startDateStr = now()->subDays(13)->format('Y-m-d');
        $endDateStr   = now()->format('Y-m-d');

        $rawCounts = [];
        try {
            $driverName = DB::connection()->getDriverName();
            if ($driverName === 'pgsql') {
                $rawCounts = (clone $baseQuery)
                    ->whereRaw("created_at::date >= ? AND created_at::date <= ?", [$startDateStr, $endDateStr])
                    ->selectRaw("TO_CHAR(created_at, 'YYYY-MM-DD') AS ticket_date, COUNT(*) AS total")
                    ->groupBy(DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD')"))
                    ->orderBy(DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD')"))
                    ->pluck('total', 'ticket_date')
                    ->toArray();
            } else {
                $rawCounts = (clone $baseQuery)
                    ->whereRaw("DATE(created_at) >= ? AND DATE(created_at) <= ?", [$startDateStr, $endDateStr])
                    ->selectRaw("DATE(created_at) AS ticket_date, COUNT(*) AS total")
                    ->groupBy(DB::raw("DATE(created_at)"))
                    ->orderBy(DB::raw("DATE(created_at)"))
                    ->pluck('total', 'ticket_date')
                    ->toArray();
            }
        } catch (\Throwable $e) {
            report($e);
            $rawCounts = [];
        }

        $chartLabels = [];
        $chartData   = [];

        for ($i = 13; $i >= 0; $i--) {
            $date          = now()->subDays($i)->format('Y-m-d');
            $chartLabels[] = now()->subDays($i)->format('M j');
            $chartData[]   = (int) ($rawCounts[$date] ?? 0);
        }

        // ── Category & Status Breakdown ──────────────────────────────────────
        $rawCategoryCounts = [];
        try {
            $rawCategoryCounts = (clone $baseQuery)
                ->select('category', DB::raw('COUNT(*) AS total'))
                ->groupBy('category')
                ->pluck('total', 'category')
                ->toArray();
        } catch (\Throwable $e) {
            report($e);
        }

        $categoryEnumMap = [];
        foreach (\App\Enums\TicketCategory::cases() as $cat) {
            $categoryEnumMap[$cat->value] = $cat->label();
        }

        $aggregatedCategories = [];
        foreach ($rawCategoryCounts as $rawCategory => $count) {
            $count = (int) $count;
            if ($count <= 0) continue;

            $rawCategoryStr = trim((string) $rawCategory);
            if ($rawCategory === null || $rawCategoryStr === '' || strtolower($rawCategoryStr) === 'undefined' || strtolower($rawCategoryStr) === 'null') {
                $label = 'Uncategorized';
            } elseif (isset($categoryEnumMap[$rawCategoryStr])) {
                $label = $categoryEnumMap[$rawCategoryStr];
            } else {
                $label = ucwords(str_replace(['_', '-'], ' ', $rawCategoryStr));
            }

            $aggregatedCategories[$label] = ($aggregatedCategories[$label] ?? 0) + $count;
        }

        $categoryLabels = [];
        $categoryData   = [];
        foreach ($aggregatedCategories as $label => $count) {
            $categoryLabels[] = $label;
            $categoryData[]   = $count;
        }

        if (empty($categoryLabels)) {
            $categoryLabels = ['Technical Question', 'Refund Request', 'General Support', 'Account Issues'];
            $categoryData   = [0, 0, 0, 0];
        }

        $rawStatusCounts = [];
        try {
            $rawStatusCounts = (clone $baseQuery)
                ->select('status', DB::raw('COUNT(*) AS total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();
        } catch (\Throwable $e) {
            report($e);
        }

        $statusLabels = [];
        $statusData   = [];

        foreach (\App\Enums\TicketStatus::cases() as $st) {
            $val   = $st->value;
            $count = (int) ($rawStatusCounts[$val] ?? 0);
            if ($count > 0) {
                $statusLabels[] = $st->label();
                $statusData[]   = $count;
            }
        }

        if (empty($statusLabels)) {
            foreach (\App\Enums\TicketStatus::cases() as $st) {
                $statusLabels[] = $st->label();
                $statusData[]   = 0;
            }
        }

        // ── Recent Tickets (Paginated 8 per page: Page 1 shows tickets 1 to 8) ──
        // Eager-load 'replies' to avoid N+1 queries from the attachment badge check in blade
        try {
            $tickets = (clone $baseQuery)
                ->with(['assignedAgent', 'replies'])
                ->orderBy('id', 'desc')
                ->paginate(8)
                ->withQueryString();
        } catch (\Throwable $e) {
            report($e);
            $tickets = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 8);
        }

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
