<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full min-w-0">
            <div class="min-w-0">
                <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white truncate">Dashboard</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 truncate hidden sm:block">Welcome back, {{ Auth::user()->name }} 👋</p>
            </div>
        </div>
    </x-slot>

    {{-- ═══ Agent Dashboard View Switcher Bar ═══ --}}
    <div class="card p-3 mb-5 bg-slate-50/80 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">Dashboard View:</span>
            @if($selectedAgent && $selectedAgent->isAdmin())
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-700">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    Company Overall View ({{ $selectedAgent->name }})
                </span>
            @elseif($selectedAgent && !$selectedAgent->isAdmin())
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold rounded-lg bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-700">
                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                    Agent: {{ $selectedAgent->name }}
                </span>
            @elseif($selectedAgentId === 'unassigned')
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold rounded-lg bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-700">
                    Unassigned Queue
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold rounded-lg bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200">
                    Company Overall View
                </span>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{-- View Overall --}}
            <a href="{{ route('dashboard') }}" class="px-3 py-1.5 text-xs font-semibold rounded-xl transition-all {{ empty($selectedAgentId) ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-xs border border-slate-200 dark:border-slate-600' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                🌐 Overall View
            </a>

            {{-- View My Agent Dashboard --}}
            <a href="{{ route('dashboard', ['agent_id' => auth()->id()]) }}" class="px-3 py-1.5 text-xs font-semibold rounded-xl transition-all {{ $selectedAgentId == auth()->id() ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700' }}">
                👤 Agent Dashboard
            </a>

            {{-- Filter by any Agent (Admin control) --}}
            @if(auth()->user()->isAdmin())
                <form method="GET" action="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5">
                    <select name="agent_id" onchange="this.form.submit()" class="form-select text-xs font-medium py-1 px-2.5 rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                        <option value="">-- Select Agent Dashboard --</option>
                        @foreach($agents as $ag)
                            <option value="{{ $ag->id }}" {{ $selectedAgentId == $ag->id ? 'selected' : '' }}>
                                Agent: {{ $ag->name }} {{ $ag->id === auth()->id() ? '(You)' : '' }}
                            </option>
                        @endforeach
                        <option value="unassigned" {{ $selectedAgentId === 'unassigned' ? 'selected' : '' }}>Unassigned Queue</option>
                    </select>
                </form>
            @endif
        </div>
    </div>

    {{-- ═══ Page Action Header ═══ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white sm:hidden">Welcome back, {{ Auth::user()->name }} 👋</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Real-time support ticket operations overview</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form action="{{ route('tickets.sync-emails') }}" method="POST" class="inline-block" x-data="{ syncing: false }" @submit="syncing = true">
                @csrf
                <button type="submit" :disabled="syncing" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-75 text-white text-xs font-semibold rounded-xl shadow-sm transition-colors">
                    <svg x-show="!syncing" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <svg x-show="syncing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" style="display: none;">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="syncing ? 'Syncing Emails...' : 'Sync IMAP Emails'">Sync IMAP Emails</span>
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

    {{-- ═══ Dual Analytics Section: Side-by-Side Line Chart & Pie/Donut Chart ═══ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-8">

        {{-- Left: 14-Day Line Trend Chart --}}
        <div class="card p-5">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">14-Day Ticket Volume</h2>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Daily tickets created over the last 14 days</p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-200 dark:ring-indigo-700/50">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                    Line Trend
                </span>
            </div>
            <div class="relative h-64 sm:h-60">
                <canvas id="ticketsLineChart"></canvas>
            </div>
        </div>

        {{-- Right: Category / Status Pie Donut Chart --}}
        <div class="card p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div>
                    <h2 id="pieChartTitleText" class="text-sm font-semibold text-slate-700 dark:text-slate-200">Category Distribution</h2>
                    <p id="pieChartSubTitleText" class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Breakdown by ticket category</p>
                </div>
                <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl ring-1 ring-slate-200 dark:ring-slate-700/60 self-start sm:self-auto">
                    <button type="button" id="btnPieCategory" class="px-2.5 py-1 text-xs font-semibold rounded-lg transition-all bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs">
                        Category
                    </button>
                    <button type="button" id="btnPieStatus" class="px-2.5 py-1 text-xs font-semibold rounded-lg transition-all text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                        Status
                    </button>
                </div>
            </div>
            <div class="relative h-64 sm:h-60 flex items-center justify-center">
                <canvas id="ticketsPieChart"></canvas>
            </div>
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
                            <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Actions</th>
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
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <a href="{{ route('tickets.show', $ticket) }}"
                                       class="font-semibold text-slate-900 dark:text-slate-100 hover:text-indigo-600 dark:hover:text-indigo-400 truncate transition-colors text-sm"
                                       title="{{ $ticket->subject }}">
                                        {{ $ticket->subject }}
                                    </a>
                                    @if($ticket->replies->contains(fn($r) => $r->hasAttachment()))
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-700 dark:text-amber-300 bg-amber-100 dark:bg-amber-900/50 px-1.5 py-0.5 rounded shadow-2xs">
                                            <svg class="w-3 h-3 text-amber-600 dark:text-amber-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                            Image
                                        </span>
                                    @endif
                                </div>
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

                            {{-- Actions --}}
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" onsubmit="return confirm('Are you sure you want to permanently delete Ticket #{{ $ticket->id }}?');" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1 text-slate-400 hover:text-red-600 dark:hover:text-red-400 rounded hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors" title="Delete Ticket #{{ $ticket->id }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
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
                const lineCtx = document.getElementById('ticketsLineChart');
                const pieCtx  = document.getElementById('ticketsPieChart');
                if (!lineCtx || !pieCtx) return;

                const dailyLabels    = @json($chartLabels);
                const dailyData      = @json($chartData);
                const categoryLabels = @json($categoryLabels);
                const categoryData   = @json($categoryData);
                const statusLabels   = @json($statusLabels);
                const statusData     = @json($statusData);

                const pieTitleEl    = document.getElementById('pieChartTitleText');
                const pieSubTitleEl = document.getElementById('pieChartSubTitleText');
                const btnPieCategory = document.getElementById('btnPieCategory');
                const btnPieStatus   = document.getElementById('btnPieStatus');

                // Industrial Dark/Light Palette
                const industrialColors = ['#6366f1', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6', '#06b6d4', '#14b8a6'];

                function isDark() {
                    return document.documentElement.classList.contains('dark');
                }

                function getThemeColors() {
                    const dark = isDark();
                    return {
                        tickColor  : dark ? '#94a3b8'                : '#64748b',
                        gridColor  : dark ? 'rgba(255,255,255,0.06)' : '#f1f5f9',
                        tooltipBg  : dark ? '#1e293b'                : '#ffffff',
                        tooltipText: dark ? '#f1f5f9'                : '#0f172a',
                        tooltipBdr : dark ? '#334155'                : '#e2e8f0',
                        ringBorder : dark ? '#1e293b'                : '#ffffff',
                    };
                }

                let lineChartInstance = null;
                let pieChartInstance  = null;
                let currentPieView    = 'category';

                // Center Text Plugin for Doughnut chart
                const centerTextPlugin = {
                    id: 'centerText',
                    beforeDraw(chart) {
                        if (chart.config.type !== 'doughnut') return;
                        const { ctx, chartArea } = chart;
                        if (!chartArea) return;

                        const { width, height, top, left } = chartArea;
                        ctx.save();

                        const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                        const dark = isDark();

                        const centerX = left + width / 2;
                        const centerY = top + height / 2 - 6;

                        ctx.font = 'bold 22px Inter, sans-serif';
                        ctx.fillStyle = dark ? '#f8fafc' : '#0f172a';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(total, centerX, centerY);

                        ctx.font = '500 11px Inter, sans-serif';
                        ctx.fillStyle = dark ? '#94a3b8' : '#64748b';
                        ctx.fillText('Total Tickets', centerX, centerY + 22);

                        ctx.restore();
                    }
                };

                // ── 1. Render Left Line Chart ──────────────────────────────────────
                function renderLineChart() {
                    if (lineChartInstance) {
                        lineChartInstance.destroy();
                    }

                    const c = getThemeColors();
                    const dark = isDark();

                    const ctx2d = lineCtx.getContext('2d');
                    const gradient = ctx2d.createLinearGradient(0, 0, 0, 220);
                    gradient.addColorStop(0, dark ? 'rgba(99, 102, 241, 0.35)' : 'rgba(99, 102, 241, 0.25)');
                    gradient.addColorStop(1, dark ? 'rgba(99, 102, 241, 0.0)' : 'rgba(99, 102, 241, 0.0)');

                    lineChartInstance = new Chart(lineCtx, {
                        type: 'line',
                        data: {
                            labels: dailyLabels,
                            datasets: [{
                                label: 'Tickets Created',
                                data: dailyData,
                                borderColor: '#6366f1',
                                borderWidth: 3,
                                backgroundColor: gradient,
                                fill: true,
                                tension: 0.35,
                                pointBackgroundColor: '#6366f1',
                                pointBorderColor: c.ringBorder,
                                pointBorderWidth: 2,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: { duration: 350 },
                            scales: {
                                x: {
                                    grid: { color: c.gridColor },
                                    ticks: { color: c.tickColor, font: { size: 11, family: 'Inter, sans-serif' } }
                                },
                                y: {
                                    beginAtZero: true,
                                    grid: { color: c.gridColor },
                                    ticks: {
                                        color: c.tickColor,
                                        precision: 0,
                                        font: { size: 11, family: 'Inter, sans-serif' }
                                    }
                                }
                            },
                            plugins: {
                                legend: { display: false },
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
                                            return `  ${val} ticket${val !== 1 ? 's' : ''} created`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                // ── 2. Render Right Pie / Donut Chart ──────────────────────────────
                function updatePieTabUI() {
                    const activeCls   = ['bg-white', 'dark:bg-slate-700', 'text-slate-900', 'dark:text-white', 'shadow-xs'];
                    const inactiveCls = ['text-slate-500', 'hover:text-slate-900', 'dark:text-slate-400', 'dark:hover:text-white'];

                    [btnPieCategory, btnPieStatus].forEach(btn => {
                        if (btn) btn.classList.remove(...activeCls);
                        if (btn) btn.classList.add(...inactiveCls);
                    });

                    if (currentPieView === 'category' && btnPieCategory) {
                        btnPieCategory.classList.remove(...inactiveCls);
                        btnPieCategory.classList.add(...activeCls);
                    } else if (currentPieView === 'status' && btnPieStatus) {
                        btnPieStatus.classList.remove(...inactiveCls);
                        btnPieStatus.classList.add(...activeCls);
                    }
                }

                function renderPieChart() {
                    if (pieChartInstance) {
                        pieChartInstance.destroy();
                    }

                    const c = getThemeColors();
                    updatePieTabUI();

                    let labels = [];
                    let data = [];
                    let bgColors = [];

                    if (currentPieView === 'category') {
                        labels = categoryLabels;
                        data = categoryData;
                        bgColors = industrialColors.slice(0, labels.length);
                        if (pieTitleEl) pieTitleEl.textContent = 'Category Distribution';
                        if (pieSubTitleEl) pieSubTitleEl.textContent = 'Breakdown by ticket category';
                    } else {
                        labels = statusLabels;
                        data = statusData;
                        bgColors = ['#3b82f6', '#8b5cf6', '#10b981', '#64748b'].slice(0, labels.length);
                        if (pieTitleEl) pieTitleEl.textContent = 'Status Breakdown';
                        if (pieSubTitleEl) pieSubTitleEl.textContent = 'Current status distribution';
                    }

                    pieChartInstance = new Chart(pieCtx, {
                        type: 'doughnut',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Tickets',
                                data: data,
                                backgroundColor: bgColors,
                                borderColor: c.ringBorder,
                                borderWidth: 3,
                                hoverOffset: 6,
                            }]
                        },
                        plugins: [centerTextPlugin],
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '70%',
                            animation: { duration: 350 },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'bottom',
                                    labels: {
                                        color: c.tickColor,
                                        font: { size: 11, family: 'Inter, sans-serif' },
                                        padding: 12,
                                        boxWidth: 10,
                                        usePointStyle: true,
                                        pointStyle: 'circle',
                                        generateLabels: function(chart) {
                                            const orig = Chart.defaults.plugins.legend.labels.generateLabels(chart);
                                            const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                            return orig.map((item, idx) => {
                                                const val = chart.data.datasets[0].data[idx] || 0;
                                                const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                                                item.text = `${item.text} (${val} • ${pct}%)`;
                                                return item;
                                            });
                                        }
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
                            }
                        }
                    });
                }

                // Initial render of both charts
                renderLineChart();
                renderPieChart();

                // Button listeners for Right Pie Chart
                if (btnPieCategory) btnPieCategory.addEventListener('click', () => { currentPieView = 'category'; renderPieChart(); });
                if (btnPieStatus)   btnPieStatus.addEventListener('click', () => { currentPieView = 'status'; renderPieChart(); });

                // Dark mode observer
                const observer = new MutationObserver(function () {
                    renderLineChart();
                    renderPieChart();
                });

                observer.observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ['class']
                });
            });
        </script>
    @endpush


</x-app-layout>
