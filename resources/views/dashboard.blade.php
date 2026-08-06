<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full min-w-0">
            <div class="min-w-0">
                <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white truncate">Dashboard</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 truncate hidden sm:block">Welcome back, {{ Auth::user()->name }} 👋</p>
            </div>
        </div>
    </x-slot>

    {{-- ═══ Page Action Header ═══ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white sm:hidden">Welcome back, {{ Auth::user()->name }} 👋</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Real-time support ticket operations overview</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form action="{{ route('tickets.sync-emails') }}" method="POST" class="inline-block">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Sync IMAP Emails</span>
                </button>
            </form>
            <span class="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 text-xs font-semibold rounded-xl ring-1 ring-emerald-200 dark:ring-emerald-700/50">
                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                <span>System Online</span>
            </span>
        </div>
    </div>

    {{-- ═══ Stats Overview ═══ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

        {{-- Total Tickets --}}
        <div class="stat-card group hover:shadow-md transition-shadow duration-200">
            <div class="w-11 h-11 rounded-2xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center shrink-0 group-hover:bg-slate-200 dark:group-hover:bg-slate-600 transition-colors">
                <svg class="w-5 h-5 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-900 dark:text-white leading-none">{{ number_format($totalTickets) }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-medium">Total Tickets</p>
            </div>
        </div>

        {{-- Open Tickets --}}
        <div class="stat-card group hover:shadow-md transition-shadow duration-200">
            <div class="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-900/40 flex items-center justify-center shrink-0 group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/60 transition-colors">
                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-900 dark:text-white leading-none">{{ number_format($openTickets) }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-medium">Open Tickets</p>
            </div>
        </div>

        {{-- AI Resolved --}}
        <div class="stat-card group hover:shadow-md transition-shadow duration-200">
            <div class="w-11 h-11 rounded-2xl bg-emerald-50 dark:bg-emerald-900/40 flex items-center justify-center shrink-0 group-hover:bg-emerald-100 dark:group-hover:bg-emerald-900/60 transition-colors">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-900 dark:text-white leading-none">{{ $aiResolvedTickets }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-medium">AI Resolved</p>
            </div>
        </div>

        {{-- AI Resolution % --}}
        <div class="stat-card group hover:shadow-md transition-shadow duration-200">
            <div class="w-11 h-11 rounded-2xl bg-violet-50 dark:bg-violet-900/40 flex items-center justify-center shrink-0 group-hover:bg-violet-100 dark:group-hover:bg-violet-900/60 transition-colors">
                <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-900 dark:text-white leading-none">{{ $aiResolutionPct }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-medium">AI Resolution %</p>
            </div>
        </div>

    </div>

    {{-- ═══ Chart: Tickets per Day (Responsive Bar on Desktop / Pie on Mobile) ═══ --}}
    <div class="card p-5 mb-8">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 id="chartTitleText" class="text-sm font-semibold text-slate-700 dark:text-slate-200">Tickets Created</h2>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Last 30 days overview</p>
            </div>
            <span id="chartBadgeText" class="text-xs text-slate-400 dark:text-slate-400 bg-slate-50 dark:bg-slate-700/60 px-2.5 py-1 rounded-full ring-1 ring-slate-200 dark:ring-slate-600">Daily</span>
        </div>
        <div class="relative h-64 sm:h-52">
            <canvas id="ticketsBarChart"></canvas>
        </div>
    </div>

    {{-- ═══ Recent Tickets Section (Paginated) ═══ --}}
    <div class="card p-5 mb-8">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Recent Support Tickets</h2>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Showing latest tickets in the system</p>
            </div>
            <a href="{{ route('tickets.index') }}" class="text-xs font-semibold text-indigo-500 hover:text-indigo-400 flex items-center gap-1 transition-colors">
                View All Queue →
            </a>
        </div>

        @if($tickets->isEmpty())
            <div class="py-12 text-center text-slate-400 text-sm">
                No support tickets found.
            </div>
        @else
            <div class="overflow-x-auto border border-slate-100 dark:border-slate-700 rounded-xl">
                <table class="w-full text-left data-table">
                    <thead>
                        <tr class="bg-slate-50/70 dark:bg-slate-800/60 border-b border-slate-100 dark:border-slate-700">
                            <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center w-16">ID</th>
                            <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Subject</th>
                            <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sender</th>
                            <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Category</th>
                            <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Status</th>
                            <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Assigned Agent</th>
                            <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($tickets as $ticket)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition-colors">
                            {{-- ID --}}
                            <td class="py-3 px-4 text-center">
                                <span class="text-xs font-bold text-slate-400 dark:text-slate-500">#{{ $ticket->id }}</span>
                            </td>

                            {{-- Subject --}}
                            <td class="py-3 px-4 max-w-[280px]">
                                <a href="{{ route('tickets.show', $ticket) }}"
                                   class="block font-semibold text-slate-900 dark:text-slate-100 hover:text-indigo-600 dark:hover:text-indigo-400 truncate transition-colors text-sm"
                                   title="{{ $ticket->subject }}">
                                    {{ $ticket->subject }}
                                </a>
                                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5 truncate" title="{{ $ticket->body }}">
                                    {{ Str::limit($ticket->body, 60) }}
                                </p>
                            </td>

                            {{-- Sender --}}
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-bold flex items-center justify-center">
                                        {{ strtoupper(substr($ticket->sender_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-slate-800 dark:text-slate-200">{{ $ticket->sender_name }}</p>
                                        <p class="text-[11px] text-slate-400 dark:text-slate-500">{{ $ticket->sender_email }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Category --}}
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($ticket->category)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-200 dark:ring-indigo-700/50">
                                        {{ $ticket->category->value }}
                                    </span>
                                @else
                                    <span class="text-slate-400 dark:text-slate-600 text-xs italic">—</span>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                @php
                                    $statusCls = match($ticket->status->value) {
                                        'open'       => 'badge-open',
                                        'closed'     => 'badge-closed',
                                        'new'        => 'bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 ring-1 ring-blue-200 dark:ring-blue-700/50',
                                        'processing' => 'bg-purple-50 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 ring-1 ring-purple-200 dark:ring-purple-700/50',
                                        'resolved'   => 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-200 dark:ring-emerald-700/50',
                                        default      => 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 ring-1 ring-slate-200 dark:ring-slate-600',
                                    };
                                @endphp
                                <span class="badge {{ $statusCls }} text-xs">
                                    {{ ucfirst($ticket->status->value) }}
                                </span>
                            </td>

                            {{-- Assigned Agent --}}
                            <td class="py-3 px-4 whitespace-nowrap text-xs text-slate-600 dark:text-slate-400">
                                @if($ticket->assignedAgent)
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ $ticket->assignedAgent->name }}</span>
                                @else
                                    <span class="text-slate-400 dark:text-slate-500 italic">Unassigned</span>
                                @endif
                            </td>

                            {{-- Created --}}
                            <td class="py-3 px-4 text-right whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                                {{ $ticket->created_at->diffForHumans() }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination Links --}}
            <div class="px-1 py-4 border-t border-slate-100 dark:border-slate-700 bg-white dark:bg-transparent mt-1">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>

    {{-- ═══ Quick Actions + Info ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Quick Links --}}
        <div class="card p-5 lg:col-span-2">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-4">Quick Actions</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <a href="{{ route('tickets.index') }}"
                   class="flex items-center gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-indigo-300 dark:hover:border-indigo-600 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/20 transition-all duration-150 group">
                    <div class="w-9 h-9 bg-indigo-100 dark:bg-indigo-900/40 rounded-xl flex items-center justify-center group-hover:bg-indigo-200 dark:group-hover:bg-indigo-900/60 transition-colors">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 group-hover:text-indigo-700 dark:group-hover:text-indigo-400 transition-colors">View All Tickets</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Browse &amp; manage support queue</p>
                    </div>
                </a>

                @if (Auth::user()?->isAdmin())
                <a href="{{ route('users.index') }}"
                   class="flex items-center gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-violet-300 dark:hover:border-violet-600 hover:bg-violet-50/50 dark:hover:bg-violet-900/20 transition-all duration-150 group">
                    <div class="w-9 h-9 bg-violet-100 dark:bg-violet-900/40 rounded-xl flex items-center justify-center group-hover:bg-violet-200 dark:group-hover:bg-violet-900/60 transition-colors">
                        <svg class="w-4 h-4 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 group-hover:text-violet-700 dark:group-hover:text-violet-400 transition-colors">Manage Users</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Add agents and admins</p>
                    </div>
                </a>
                @endif

                <a href="{{ route('profile.edit') }}"
                   class="flex items-center gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-sky-300 dark:hover:border-sky-600 hover:bg-sky-50/50 dark:hover:bg-sky-900/20 transition-all duration-150 group">
                    <div class="w-9 h-9 bg-sky-100 dark:bg-sky-900/40 rounded-xl flex items-center justify-center group-hover:bg-sky-200 dark:group-hover:bg-sky-900/60 transition-colors">
                        <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 group-hover:text-sky-700 dark:group-hover:text-sky-400 transition-colors">My Profile</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Update account settings</p>
                    </div>
                </a>
            </div>
        </div>

        {{-- Session Info --}}
        <div class="card p-5 flex flex-col gap-4">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Account Info</h2>

            <div class="flex items-center gap-3">
                <div class="avatar avatar-lg gradient-brand text-white">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate">{{ Auth::user()->name }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ Auth::user()->email }}</p>
                </div>
            </div>

            <div class="border-t border-slate-100 dark:border-slate-700 pt-4 space-y-2.5">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500 dark:text-slate-400">Role</span>
                    @if(Auth::user()->isAdmin())
                        <span class="badge badge-admin">Admin</span>
                    @else
                        <span class="badge badge-agent">Agent</span>
                    @endif
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500 dark:text-slate-400">Status</span>
                    <span class="badge badge-open">Active</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500 dark:text-slate-400">Member since</span>
                    <span class="text-slate-700 dark:text-slate-300 font-medium">{{ Auth::user()->created_at->format('M Y') }}</span>
                </div>
            </div>
        </div>

    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const ctx = document.getElementById('ticketsBarChart');
                if (!ctx) return;

                const labels = @json($chartLabels);
                const data   = @json($chartData);
                const chartTitleEl = document.getElementById('chartTitleText');
                const chartBadgeEl = document.getElementById('chartBadgeText');

                const pieColors = [
                    '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e', '#f97316',
                    '#eab308', '#10b981', '#06b6d4', '#3b82f6', '#14b8a6',
                    '#a855f7', '#d946ef', '#fb923c', '#facc15', '#4ade80'
                ];

                function isDark() {
                    return document.documentElement.classList.contains('dark');
                }

                function isMobile() {
                    return window.innerWidth < 640;
                }

                function getColors() {
                    const dark = isDark();
                    return {
                        barBg      : dark ? 'rgba(99, 102, 241, 0.65)' : 'rgba(99, 102, 241, 0.80)',
                        barBorder  : dark ? 'rgba(129, 140, 248, 1)'   : 'rgba(79, 70, 229, 1)',
                        tickColor  : dark ? '#94a3b8'                   : '#64748b',
                        gridColor  : dark ? 'rgba(255,255,255,0.06)'    : '#f1f5f9',
                        tooltipBg  : dark ? '#1e293b'                   : '#ffffff',
                        tooltipText: dark ? '#f1f5f9'                   : '#0f172a',
                        tooltipBdr : dark ? '#334155'                   : '#e2e8f0',
                    };
                }

                let chartInstance = null;

                function buildChart() {
                    if (chartInstance) {
                        chartInstance.destroy();
                    }

                    const mobile = isMobile();
                    const c = getColors();

                    if (chartTitleEl) {
                        chartTitleEl.textContent = mobile ? 'Tickets Distribution (Pie Chart)' : 'Tickets Created';
                    }
                    if (chartBadgeEl) {
                        chartBadgeEl.textContent = mobile ? 'Pie Chart' : 'Daily';
                    }

                    const chartType = mobile ? 'pie' : 'bar';

                    chartInstance = new Chart(ctx, {
                        type: chartType,
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Tickets Created',
                                data: data,
                                backgroundColor: mobile ? pieColors.slice(0, data.length) : c.barBg,
                                borderColor: mobile ? (isDark() ? '#1e293b' : '#ffffff') : c.barBorder,
                                borderWidth: mobile ? 2 : 1.5,
                                borderRadius: mobile ? 0 : 5,
                                borderSkipped: false,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: { duration: 300 },
                            plugins: {
                                legend: {
                                    display: mobile,
                                    position: 'bottom',
                                    labels: {
                                        color: c.tickColor,
                                        font: { size: 10, family: 'Inter, sans-serif' },
                                        padding: 10,
                                        boxWidth: 10,
                                    }
                                },
                                tooltip: {
                                    backgroundColor: c.tooltipBg,
                                    titleColor: c.tooltipText,
                                    bodyColor: c.tooltipText,
                                    borderColor: c.tooltipBdr,
                                    borderWidth: 1,
                                    padding: 10,
                                    cornerRadius: 8,
                                    callbacks: {
                                        label: function(context) {
                                            const val = context.raw || 0;
                                            return `  ${val} ticket${val !== 1 ? 's' : ''}`;
                                        }
                                    }
                                }
                            },
                            scales: mobile ? {} : {
                                x: {
                                    grid: { display: false },
                                    border: { display: false },
                                    ticks: {
                                        font: { size: 11, family: 'Inter, sans-serif' },
                                        color: c.tickColor,
                                        maxTicksLimit: 10,
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    border: { display: false, dash: [4, 4] },
                                    ticks: {
                                        precision: 0,
                                        font: { size: 11, family: 'Inter, sans-serif' },
                                        color: c.tickColor,
                                    },
                                    grid: {
                                        color: c.gridColor,
                                    }
                                }
                            }
                        }
                    });
                }

                buildChart();

                // ── Dynamic resize listener for mobile <-> desktop toggle ──
                let resizeTimer;
                let currentIsMobile = isMobile();

                window.addEventListener('resize', function () {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(function () {
                        const newIsMobile = isMobile();
                        if (newIsMobile !== currentIsMobile) {
                            currentIsMobile = newIsMobile;
                            buildChart();
                        }
                    }, 150);
                });

                // ── Dark mode observer ──
                const observer = new MutationObserver(function () {
                    buildChart();
                });

                observer.observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ['class']
                });
            });
        </script>
    @endpush


</x-app-layout>
