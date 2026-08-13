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
            <a href="{{ route('dashboard') }}" class="px-3 py-1.5 text-xs font-semibold rounded-xl transition-all {{ empty($selectedAgentId) ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-xs border border-slate-200 dark:border-slate-600' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                🌐 Overall View
            </a>
            <a href="{{ route('dashboard', ['agent_id' => auth()->id()]) }}" class="px-3 py-1.5 text-xs font-semibold rounded-xl transition-all {{ $selectedAgentId == auth()->id() ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700' }}">
                👤 My View
            </a>
            @if(auth()->user()->isAdmin())
                <form method="GET" action="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5">
                    <select name="agent_id" onchange="this.form.submit()" class="form-select text-xs font-medium py-1 px-2.5 rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                        <option value="">-- Select Agent --</option>
                        @foreach($agents as $ag)
                            <option value="{{ $ag->id }}" {{ $selectedAgentId == $ag->id ? 'selected' : '' }}>
                                {{ $ag->name }} {{ $ag->id === auth()->id() ? '(You)' : '' }}
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
                    <span x-text="syncing ? 'Syncing...' : 'Sync Emails'">Sync Emails</span>
                </button>
            </form>
            <span class="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 text-xs font-semibold rounded-xl ring-1 ring-emerald-200 dark:ring-emerald-700/50">
                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                System Online
            </span>
        </div>
    </div>

    {{-- ═══ Stats Cards ═══ --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

        {{-- Total Tickets --}}
        <div class="card p-4 flex items-center gap-4 group hover:shadow-md transition-shadow duration-200">
            <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center shrink-0 group-hover:bg-slate-200 dark:group-hover:bg-slate-600 transition-colors">
                <svg class="w-6 h-6 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-2xl font-bold text-slate-900 dark:text-white leading-none">{{ number_format($totalTickets) }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Total Tickets</p>
            </div>
        </div>

        {{-- Open Tickets --}}
        <div class="card p-4 flex items-center gap-4 group hover:shadow-md transition-shadow duration-200">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/40 flex items-center justify-center shrink-0 group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/60 transition-colors">
                <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-2xl font-bold text-slate-900 dark:text-white leading-none">{{ number_format($openTickets) }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Open Tickets</p>
            </div>
        </div>

        {{-- AI Resolved --}}
        <div class="card p-4 flex items-center gap-4 group hover:shadow-md transition-shadow duration-200">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-900/40 flex items-center justify-center shrink-0 group-hover:bg-emerald-100 dark:group-hover:bg-emerald-900/60 transition-colors">
                <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-2xl font-bold text-slate-900 dark:text-white leading-none">{{ $aiResolvedTickets }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">AI Resolved</p>
                <p class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold">{{ $aiResolutionPct }} rate</p>
            </div>
        </div>

        {{-- Avg Resolution Time --}}
        <div class="card p-4 flex items-center gap-4 group hover:shadow-md transition-shadow duration-200">
            <div class="w-12 h-12 rounded-2xl bg-violet-50 dark:bg-violet-900/40 flex items-center justify-center shrink-0 group-hover:bg-violet-100 dark:group-hover:bg-violet-900/60 transition-colors">
                <svg class="w-6 h-6 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-2xl font-bold text-slate-900 dark:text-white leading-none">{{ $avgResolutionHours }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Avg Resolution</p>
            </div>
        </div>

    </div>

    {{-- ═══ Charts Section ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-5 mb-6">

        {{-- Left: 14-Day Line Trend Chart (wider) --}}
        <div class="card p-5 lg:col-span-3">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">14-Day Ticket Volume</h2>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Daily tickets created over the last 14 days</p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-200 dark:ring-indigo-700/50 shrink-0">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                    Trend
                </span>
            </div>
            {{-- Chart total summary --}}
            <div class="flex items-center gap-4 mb-3">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-indigo-500 inline-block"></span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Tickets Created</span>
                </div>
                <span class="text-xs font-bold text-slate-900 dark:text-white">
                    {{ array_sum($chartData) }} in last 14 days
                </span>
            </div>
            <div style="position: relative; height: 220px; width: 100%;">
                <canvas id="ticketsLineChart"></canvas>
            </div>
        </div>

        {{-- Right: Category / Status Donut Chart --}}
        <div class="card p-5 lg:col-span-2">
            <div class="flex items-center justify-between gap-2 mb-4">
                <div>
                    <h2 id="pieChartTitleText" class="text-sm font-semibold text-slate-700 dark:text-slate-200">Distribution</h2>
                    <p id="pieChartSubTitleText" class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">By category</p>
                </div>
                <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl ring-1 ring-slate-200 dark:ring-slate-700/60 shrink-0">
                    <button type="button" id="btnPieCategory" class="px-2.5 py-1 text-xs font-semibold rounded-lg transition-all bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs">
                        Category
                    </button>
                    <button type="button" id="btnPieStatus" class="px-2.5 py-1 text-xs font-semibold rounded-lg transition-all text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                        Status
                    </button>
                </div>
            </div>
            <div style="position: relative; height: 240px; width: 100%;">
                <canvas id="ticketsPieChart"></canvas>
            </div>
        </div>

    </div>

    {{-- ═══ Recent Tickets Table ═══ --}}
    <div class="card p-5 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Recent Support Tickets</h2>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">
                    {{ $tickets->total() }} total tickets — showing {{ $tickets->firstItem() }}–{{ $tickets->lastItem() }}
                </p>
            </div>
            <a href="{{ route('tickets.index') }}" class="text-xs font-semibold text-indigo-500 hover:text-indigo-400 flex items-center gap-1 transition-colors shrink-0">
                View All →
            </a>
        </div>

        @if($tickets->isEmpty())
            <div class="py-16 text-center">
                <svg class="w-12 h-12 text-slate-300 dark:text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <p class="text-slate-400 dark:text-slate-500 text-sm">No support tickets found</p>
            </div>
        @else
            <div class="overflow-x-auto border border-slate-100 dark:border-slate-700 rounded-xl">
                <table class="w-full text-left data-table">
                    <thead>
                        <tr class="bg-slate-50/70 dark:bg-slate-800/60 border-b border-slate-100 dark:border-slate-700">
                            <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center w-14">ID</th>
                            <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Subject</th>
                            <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sender</th>
                            <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden md:table-cell">Category</th>
                            <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Status</th>
                            <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden lg:table-cell">Agent</th>
                            <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right hidden sm:table-cell">Created</th>
                            <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center w-16">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($tickets as $ticket)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition-colors">
                            <td class="py-3 px-4 text-center">
                                <span class="text-xs font-bold text-slate-400 dark:text-slate-500">#{{ $ticket->id }}</span>
                            </td>

                            <td class="py-3 px-4 max-w-xs">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <a href="{{ route('tickets.show', $ticket) }}"
                                       class="font-semibold text-slate-900 dark:text-slate-100 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors text-sm truncate max-w-[200px]"
                                       title="{{ $ticket->subject }}">
                                        {{ $ticket->subject }}
                                    </a>
                                    @if($ticket->replies->contains(fn($r) => $r->hasAttachment()))
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-700 dark:text-amber-300 bg-amber-100 dark:bg-amber-900/50 px-1.5 py-0.5 rounded shadow-2xs shrink-0">
                                            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                            Img
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5 truncate max-w-xs" title="{{ $ticket->body }}">
                                    {{ Str::limit($ticket->body, 55) }}
                                </p>
                            </td>

                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 text-xs font-bold flex items-center justify-center shrink-0">
                                        {{ strtoupper(substr($ticket->sender_name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-slate-800 dark:text-slate-200 truncate max-w-[120px]">{{ $ticket->sender_name }}</p>
                                        <p class="text-[11px] text-slate-400 dark:text-slate-500 truncate max-w-[120px]">{{ $ticket->sender_email }}</p>
                                    </div>
                                </div>
                            </td>

                            <td class="py-3 px-4 whitespace-nowrap hidden md:table-cell">
                                @if($ticket->category)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-200 dark:ring-indigo-700/50">
                                        {{ $ticket->category->value }}
                                    </span>
                                @else
                                    <span class="text-slate-400 dark:text-slate-600 text-xs italic">—</span>
                                @endif
                            </td>

                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                @php
                                    $statusCls = match($ticket->status->value) {
                                        'open'       => 'bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-200 dark:ring-indigo-700/50',
                                        'closed'     => 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 ring-1 ring-slate-200 dark:ring-slate-600',
                                        'new'        => 'bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 ring-1 ring-blue-200 dark:ring-blue-700/50',
                                        'processing' => 'bg-purple-50 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 ring-1 ring-purple-200 dark:ring-purple-700/50',
                                        'resolved'   => 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-200 dark:ring-emerald-700/50',
                                        default      => 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
                                    };
                                @endphp
                                <span class="badge {{ $statusCls }} text-[11px]">{{ ucfirst($ticket->status->value) }}</span>
                            </td>

                            <td class="py-3 px-4 whitespace-nowrap text-xs text-slate-600 dark:text-slate-400 hidden lg:table-cell">
                                @if($ticket->assignedAgent)
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[10px] font-bold flex items-center justify-center shrink-0">
                                            {{ strtoupper(substr($ticket->assignedAgent->name, 0, 1)) }}
                                        </div>
                                        <span class="font-medium text-slate-800 dark:text-slate-200 truncate max-w-[80px]">{{ $ticket->assignedAgent->name }}</span>
                                    </div>
                                @else
                                    <span class="text-slate-400 dark:text-slate-500 italic text-[11px]">Unassigned</span>
                                @endif
                            </td>

                            <td class="py-3 px-4 text-right whitespace-nowrap text-xs text-slate-500 dark:text-slate-400 hidden sm:table-cell">
                                {{ $ticket->created_at->diffForHumans() }}
                            </td>

                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="p-1 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors" title="View Ticket">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" onsubmit="return confirm('Delete Ticket #{{ $ticket->id }}?');" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 text-slate-400 hover:text-red-600 dark:hover:text-red-400 rounded hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination Links --}}
            <div class="px-1 py-4 border-t border-slate-100 dark:border-slate-700 mt-1">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>

    {{-- ═══ Quick Actions + Account Info ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <div class="card p-5 lg:col-span-2">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-4">Quick Actions</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <a href="{{ route('tickets.index') }}"
                   class="flex items-center gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-indigo-300 dark:hover:border-indigo-600 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/20 transition-all duration-150 group">
                    <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900/40 rounded-xl flex items-center justify-center group-hover:bg-indigo-200 dark:group-hover:bg-indigo-900/60 transition-colors shrink-0">
                        <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
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
                    <div class="w-10 h-10 bg-violet-100 dark:bg-violet-900/40 rounded-xl flex items-center justify-center group-hover:bg-violet-200 dark:group-hover:bg-violet-900/60 transition-colors shrink-0">
                        <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
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
                    <div class="w-10 h-10 bg-sky-100 dark:bg-sky-900/40 rounded-xl flex items-center justify-center group-hover:bg-sky-200 dark:group-hover:bg-sky-900/60 transition-colors shrink-0">
                        <svg class="w-5 h-5 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
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

        {{-- Account Info --}}
        <div class="card p-5 flex flex-col gap-4">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Account Info</h2>
            <div class="flex items-center gap-3">
                <div class="avatar avatar-lg gradient-brand text-white shrink-0">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate">{{ Auth::user()->name }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ Auth::user()->email }}</p>
                </div>
            </div>
            <div class="border-t border-slate-100 dark:border-slate-700 pt-4 space-y-3">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500 dark:text-slate-400 text-xs">Role</span>
                    @if(Auth::user()->isAdmin())
                        <span class="badge badge-admin">Admin</span>
                    @else
                        <span class="badge badge-agent">Agent</span>
                    @endif
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500 dark:text-slate-400 text-xs">Status</span>
                    <span class="badge badge-open">Active</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500 dark:text-slate-400 text-xs">Member since</span>
                    <span class="text-slate-700 dark:text-slate-300 font-medium text-xs">{{ Auth::user()->created_at->format('M Y') }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500 dark:text-slate-400 text-xs">Total Tickets</span>
                    <span class="text-slate-700 dark:text-slate-300 font-bold text-sm">{{ number_format($totalTickets) }}</span>
                </div>
            </div>
        </div>

    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const lineCanvas = document.getElementById('ticketsLineChart');
            const pieCanvas  = document.getElementById('ticketsPieChart');
            if (!lineCanvas || !pieCanvas) return;

            // Data from PHP
            const dailyLabels    = @json($chartLabels);
            const dailyData      = @json($chartData);
            const categoryLabels = @json($categoryLabels);
            const categoryData   = @json($categoryData);
            const statusLabels   = @json($statusLabels);
            const statusData     = @json($statusData);

            const pieTitleEl    = document.getElementById('pieChartTitleText');
            const pieSubTitleEl = document.getElementById('pieChartSubTitleText');
            const btnCat        = document.getElementById('btnPieCategory');
            const btnStat       = document.getElementById('btnPieStatus');

            const COLORS = ['#6366f1', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6', '#06b6d4', '#14b8a6', '#f97316', '#84cc16'];
            const STATUS_COLORS = { 'Open': '#6366f1', 'Resolved': '#10b981', 'Closed': '#64748b', 'New': '#3b82f6', 'Processing': '#8b5cf6' };

            function isDark() {
                return document.documentElement.classList.contains('dark');
            }

            function theme() {
                const d = isDark();
                return {
                    tick:        d ? '#94a3b8' : '#64748b',
                    grid:        d ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)',
                    tooltipBg:   d ? '#1e293b' : '#ffffff',
                    tooltipText: d ? '#f1f5f9' : '#0f172a',
                    tooltipBdr:  d ? '#334155' : '#e2e8f0',
                    ring:        d ? '#1e293b' : '#ffffff',
                };
            }

            let lineChart = null;
            let pieChart  = null;
            let pieMode   = 'category';

            // ── Line Chart ──────────────────────────────────────────────────────────
            function buildLineChart() {
                if (lineChart) { lineChart.destroy(); lineChart = null; }
                const t   = theme();
                const ctx = lineCanvas.getContext('2d');

                // gradient fill
                const grad = ctx.createLinearGradient(0, 0, 0, 200);
                grad.addColorStop(0, isDark() ? 'rgba(99,102,241,0.4)' : 'rgba(99,102,241,0.2)');
                grad.addColorStop(1, 'rgba(99,102,241,0)');

                const maxVal = Math.max(...dailyData, 1);

                lineChart = new Chart(lineCanvas, {
                    type: 'line',
                    data: {
                        labels: dailyLabels,
                        datasets: [{
                            label: 'Tickets',
                            data: dailyData,
                            borderColor: '#6366f1',
                            borderWidth: 2.5,
                            backgroundColor: grad,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#6366f1',
                            pointBorderColor: t.ring,
                            pointBorderWidth: 2,
                            pointRadius: dailyData.map(v => v > 0 ? 4 : 2),
                            pointHoverRadius: 6,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 500, easing: 'easeInOutQuart' },
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            x: {
                                grid: { color: t.grid, drawTicks: false },
                                border: { display: false },
                                ticks: {
                                    color: t.tick,
                                    font: { size: 11, family: 'Inter, sans-serif' },
                                    maxRotation: 0,
                                    // Show fewer labels on small screens
                                    maxTicksLimit: 7,
                                }
                            },
                            y: {
                                beginAtZero: true,
                                suggestedMax: maxVal + Math.ceil(maxVal * 0.2) + 1,
                                grid: { color: t.grid, drawTicks: false },
                                border: { display: false },
                                ticks: {
                                    color: t.tick,
                                    precision: 0,
                                    font: { size: 11, family: 'Inter, sans-serif' },
                                    maxTicksLimit: 5,
                                }
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: t.tooltipBg,
                                titleColor: t.tooltipText,
                                bodyColor: t.tooltipText,
                                borderColor: t.tooltipBdr,
                                borderWidth: 1,
                                padding: 10,
                                cornerRadius: 8,
                                callbacks: {
                                    title: ctx => ctx[0].label,
                                    label: ctx => `  ${ctx.raw} ticket${ctx.raw !== 1 ? 's' : ''}`
                                }
                            }
                        }
                    }
                });
            }

            // ── Pie / Donut Chart ────────────────────────────────────────────────────
            const centerPlugin = {
                id: 'centerText',
                beforeDraw(chart) {
                    if (chart.config.type !== 'doughnut') return;
                    const { ctx, chartArea } = chart;
                    if (!chartArea) return;
                    const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                    const cx = chartArea.left + chartArea.width / 2;
                    const cy = chartArea.top  + chartArea.height / 2 - 4;
                    const d  = isDark();
                    ctx.save();
                    ctx.font = 'bold 20px Inter, sans-serif';
                    ctx.fillStyle = d ? '#f8fafc' : '#0f172a';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(total, cx, cy);
                    ctx.font = '500 10px Inter, sans-serif';
                    ctx.fillStyle = d ? '#94a3b8' : '#64748b';
                    ctx.fillText('tickets', cx, cy + 18);
                    ctx.restore();
                }
            };

            function updatePieTabs() {
                const on  = ['bg-white','dark:bg-slate-700','text-slate-900','dark:text-white','shadow-xs'];
                const off = ['text-slate-500','hover:text-slate-900','dark:text-slate-400','dark:hover:text-white'];
                [btnCat, btnStat].forEach(b => { if(b){ b.classList.remove(...on); b.classList.add(...off); } });
                const active = pieMode === 'category' ? btnCat : btnStat;
                if (active) { active.classList.remove(...off); active.classList.add(...on); }
            }

            function buildPieChart() {
                if (pieChart) { pieChart.destroy(); pieChart = null; }
                updatePieTabs();
                const t = theme();

                let labels, data, colors;
                if (pieMode === 'category') {
                    labels = categoryLabels;
                    data   = categoryData;
                    colors = COLORS.slice(0, labels.length);
                    if (pieTitleEl)    pieTitleEl.textContent    = 'Category Distribution';
                    if (pieSubTitleEl) pieSubTitleEl.textContent = 'Breakdown by ticket category';
                } else {
                    labels = statusLabels;
                    data   = statusData;
                    colors = labels.map(l => STATUS_COLORS[l] || COLORS[labels.indexOf(l) % COLORS.length]);
                    if (pieTitleEl)    pieTitleEl.textContent    = 'Status Breakdown';
                    if (pieSubTitleEl) pieSubTitleEl.textContent = 'Current status distribution';
                }

                pieChart = new Chart(pieCanvas, {
                    type: 'doughnut',
                    data: {
                        labels,
                        datasets: [{
                            data,
                            backgroundColor: colors,
                            borderColor: t.ring,
                            borderWidth: 3,
                            hoverOffset: 6,
                        }]
                    },
                    plugins: [centerPlugin],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '68%',
                        animation: { duration: 400, easing: 'easeInOutQuart' },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: {
                                    color: t.tick,
                                    font: { size: 11, family: 'Inter, sans-serif' },
                                    padding: 10,
                                    boxWidth: 10,
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    generateLabels(chart) {
                                        const orig  = Chart.defaults.plugins.legend.labels.generateLabels(chart);
                                        const total = data.reduce((a, b) => a + b, 0);
                                        return orig.map((item, i) => {
                                            const v = data[i] || 0;
                                            const p = total > 0 ? Math.round((v / total) * 100) : 0;
                                            item.text = `${item.text} — ${v} (${p}%)`;
                                            return item;
                                        });
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: t.tooltipBg,
                                titleColor: t.tooltipText,
                                bodyColor: t.tooltipText,
                                borderColor: t.tooltipBdr,
                                borderWidth: 1,
                                padding: 10,
                                cornerRadius: 8,
                                callbacks: {
                                    label: ctx => `  ${ctx.raw} ticket${ctx.raw !== 1 ? 's' : ''}`
                                }
                            }
                        }
                    }
                });
            }

            // Initial render
            buildLineChart();
            buildPieChart();

            // Tab buttons
            if (btnCat)  btnCat.addEventListener('click',  () => { pieMode = 'category'; buildPieChart(); });
            if (btnStat) btnStat.addEventListener('click', () => { pieMode = 'status';   buildPieChart(); });

            // Dark mode observer — rebuild on theme switch
            new MutationObserver(() => { buildLineChart(); buildPieChart(); })
                .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        });
        </script>
    @endpush

</x-app-layout>
