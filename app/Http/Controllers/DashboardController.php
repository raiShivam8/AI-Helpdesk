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
        if (Ticket::count() === 0) {
            try {
                (new \Database\Seeders\ProductionSyncSeeder())->run();
            } catch (\Throwable $e) {
                report($e);
            }
        }

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
        $totalTickets = 0;
        $openTickets = 0;
        $aiResolvedCount = 0;
        $resolvedTickets = collect([]);

        try {
            $totalTickets = (clone $baseQuery)->count();
            $openTickets  = (clone $baseQuery)->where(function($q) {
                $q->where('status', TicketStatus::Open)
                  ->orWhere('status', 'open')
                  ->orWhere('status', 'Open');
            })->count();

            $aiResolvedCount = (clone $baseQuery)->whereNotNull('ai_resolved_at')->count();

            $resolvedTickets = (clone $baseQuery)->where(function ($q) {
                $q->whereNotNull('resolved_at')
                  ->orWhereNotNull('ai_resolved_at')
                  ->orWhereIn('status', [TicketStatus::Resolved, TicketStatus::Closed, 'resolved', 'closed', 'Resolved', 'Closed']);
            })->get();
        } catch (\Throwable $e) {
            report($e);
        }

        $aiResolvedTickets = (string) $aiResolvedCount;
        $aiResolutionPct   = $totalTickets > 0 ? round(($aiResolvedCount / $totalTickets) * 100, 1) . '%' : '0%';

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
        $startDate = now()->subDays(13)->startOfDay();
        $rawCounts = [];
        try {
            $rawCounts = (clone $baseQuery)
                ->where('created_at', '>=', $startDate)
                ->pluck('created_at')
                ->groupBy(function ($dt) {
                    return $dt ? \Carbon\Carbon::parse($dt)->format('Y-m-d') : null;
                })
                ->map(fn($group) => count($group))
                ->toArray();
        } catch (\Throwable $e) {
            report($e);
            $rawCounts = [];
        }

        $chartLabels = [];
        $chartData   = [];

        for ($i = 13; $i >= 0; $i--) {
            $dateKey       = now()->subDays($i)->format('Y-m-d');
            $chartLabels[] = now()->subDays($i)->format('M j');
            $chartData[]   = (int) ($rawCounts[$dateKey] ?? 0);
        }

        // ── Category & Status Breakdown ──────────────────────────────────────
        $rawCategoryCounts = [];
        try {
            $rawCategoryCounts = (clone $baseQuery)
                ->pluck('category')
                ->groupBy(function ($cat) {
                    if (is_object($cat)) {
                        return $cat->value ?? (method_exists($cat, 'label') ? $cat->label() : (string)$cat);
                    }
                    return (string) ($cat ?? 'Uncategorized');
                })
                ->map(fn($group) => count($group))
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
                ->pluck('status')
                ->groupBy(function ($st) {
                    if (is_object($st)) {
                        return $st->value ?? 'open';
                    }
                    return (string) ($st ?? 'open');
                })
                ->map(fn($group) => count($group))
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
