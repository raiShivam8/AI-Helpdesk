<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full min-w-0">
            <div class="min-w-0">
                <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white truncate">Tickets</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 truncate hidden sm:block">Support queue — all incoming requests</p>
            </div>
        </div>
    </x-slot>

    {{-- ═══ Page Action Header ═══ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white sm:hidden">Support Queue</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Manage and respond to all customer tickets</p>
        </div>
        <div class="flex items-center gap-2">
            <form action="{{ route('tickets.sync-emails') }}" method="POST">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Sync IMAP Emails</span>
                </button>
            </form>
        </div>
    </div>

    @php
        $hasActiveFilters = !empty($status) || !empty($category) || !empty($agent) || !empty($search);
        $showFilters = !$tickets->isEmpty() || $hasActiveFilters;
    @endphp

    {{-- ═══ Filter Bar ═══ --}}
    @if($showFilters)
    <div class="card mb-5">
        <form method="GET" action="{{ route('tickets.index') }}" class="p-4" x-data="{ search: '{{ old('search', $search ?? '') }}' }" x-ref="filterForm" id="ticket-filter-form">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">

            {{-- ── Search Bar ── --}}
            <div class="relative mb-4">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    id="search"
                    type="search"
                    name="search"
                    x-model="search"
                    value="{{ $search ?? '' }}"
                    placeholder="Search by subject, sender, email or message…"
                    autocomplete="off"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 pl-10 pr-10 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 shadow-sm transition duration-150 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none"
                >
                {{-- Clear search X button --}}
                <button
                    type="button"
                    x-show="search.length > 0"
                    x-cloak
                    @click="search = ''; $nextTick(() => $refs.filterForm.submit())"
                    class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-700 transition-colors"
                    title="Clear search"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- ── Filter Dropdowns ── --}}

            <div class="flex flex-wrap items-end gap-3">
                {{-- Status --}}
                <div class="flex-1 min-w-[160px]">
                    <label for="status" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Status</label>
                    <select name="status" id="status" @change="$refs.filterForm.submit()" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach(\App\Enums\TicketStatus::cases() as $st)
                            <option value="{{ $st->value }}" {{ $status === $st->value ? 'selected' : '' }}>
                                {{ ucfirst($st->value) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Category --}}
                <div class="flex-1 min-w-[160px]">
                    <label for="category" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Category</label>
                    <select name="category" id="category" @change="$refs.filterForm.submit()" class="form-select">
                        <option value="">All Categories</option>
                        @foreach(\App\Enums\TicketCategory::cases() as $cat)
                            <option value="{{ $cat->value }}" {{ $category === $cat->value ? 'selected' : '' }}>
                                {{ ucfirst($cat->value) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Agent --}}
                <div class="flex-1 min-w-[160px]">
                    <label for="agent" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Assigned Agent</label>
                    <select name="agent" id="agent" @change="$refs.filterForm.submit()" class="form-select">
                        <option value="">All Agents</option>
                        <option value="unassigned" {{ $agent === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
                        @foreach($agents as $a)
                            <option value="{{ $a->id }}" {{ (string)$agent === (string)$a->id ? 'selected' : '' }}>
                                {{ $a->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-2 shrink-0">
                    <button type="submit" class="btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Filter
                    </button>
                    @if($hasActiveFilters)
                        <a href="{{ route('tickets.index', ['sort' => $sort, 'direction' => $direction]) }}" class="btn-secondary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Clear
                        </a>
                    @endif
                </div>
            </div>

            @if($hasActiveFilters)
            <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-slate-100 dark:border-slate-700">
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Active filters:</span>
                @if(!empty($search))
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 text-emerald-700 text-xs font-semibold rounded-full ring-1 ring-emerald-200">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        &ldquo;{{ Str::limit($search, 40) }}&rdquo;
                    </span>
                @endif
                @if(!empty($status))
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-50 text-indigo-700 text-xs font-semibold rounded-full ring-1 ring-indigo-200">
                        Status: {{ ucfirst($status) }}
                    </span>
                @endif
                @if(!empty($category))
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-violet-50 text-violet-700 text-xs font-semibold rounded-full ring-1 ring-violet-200">
                        Category: {{ ucfirst($category) }}
                    </span>
                @endif
                @if(!empty($agent))
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-sky-50 text-sky-700 text-xs font-semibold rounded-full ring-1 ring-sky-200">
                        Agent: {{ $agent === 'unassigned' ? 'Unassigned' : ($agents->firstWhere('id', $agent)?->name ?? $agent) }}
                    </span>
                @endif
            </div>
            @endif
        </form>
    </div>
    @endif

    {{-- ═══ Tickets Table ═══ --}}
    @if($tickets->isEmpty())
        @if($hasActiveFilters)
            {{-- Filtered / Search Empty State --}}
            <div class="card flex flex-col items-center justify-center py-20 px-6 text-center">
                <div class="w-16 h-16 bg-slate-100 dark:bg-slate-700/60 rounded-2xl flex items-center justify-center text-slate-400 dark:text-slate-500 mb-5">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                @if(!empty($search))
                    <h3 class="text-base font-bold text-slate-900 dark:text-white mb-1.5">No tickets found for &ldquo;{{ $search }}&rdquo;</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 max-w-sm mb-6">Try different keywords, or clear your search to browse all tickets.</p>
                @else
                    <h3 class="text-base font-bold text-slate-900 dark:text-white mb-1.5">No tickets match your filters</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 max-w-sm mb-6">Try adjusting or clearing the active filters to see more results.</p>
                @endif
                <a href="{{ route('tickets.index', ['sort' => $sort, 'direction' => $direction]) }}" class="btn-secondary">
                    Clear All Filters
                </a>
            </div>
        @else
            {{-- Global Empty State --}}
            <div class="card flex flex-col items-center justify-center py-24 px-6 text-center">
                <div class="w-16 h-16 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center text-indigo-500 dark:text-indigo-400 mb-5">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-1.5">Queue is empty</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 max-w-sm">All caught up! No tickets in the queue. Incoming support emails will automatically appear here as new tickets.</p>
            </div>
        @endif
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left data-table">
                    <thead>
                        <tr>
                            <x-sortable-header column="id"          title="ID"             :current-sort="$sort" :current-direction="$direction" align="center" class="w-20" />
                            <x-sortable-header column="subject"     title="Subject"         :current-sort="$sort" :current-direction="$direction" />
                            <x-sortable-header column="sender_name" title="Sender"          :current-sort="$sort" :current-direction="$direction" />
                            <x-sortable-header column="category"    title="Category"        :current-sort="$sort" :current-direction="$direction" />
                            <x-sortable-header column="status"      title="Status"          :current-sort="$sort" :current-direction="$direction" align="center" />
                            <th class="py-3.5 px-5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Assigned Agent</th>
                            <x-sortable-header column="created_at"  title="Created"         :current-sort="$sort" :current-direction="$direction" align="right" />
                            <th class="py-3.5 px-5 text-xs font-semibold text-slate-500 uppercase tracking-wider text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tickets as $ticket)
                        <tr>
                            {{-- ID --}}
                            <td class="text-center">
                                <span class="text-xs font-bold text-slate-400">#{{ $ticket->id }}</span>
                            </td>

                            {{-- Subject --}}
                            <td class="max-w-[280px]">
                                <a href="{{ route('tickets.show', $ticket) }}"
                                   class="block font-semibold text-slate-900 dark:text-slate-100 hover:text-indigo-600 dark:hover:text-indigo-400 truncate transition-colors duration-150"
                                   title="{{ $ticket->subject }}">
                                    {{ $ticket->subject }}
                                </a>
                                <p class="text-xs text-slate-400 mt-0.5 truncate" title="{{ $ticket->body }}">
                                    {{ Str::limit($ticket->body, 65) }}
                                </p>
                            </td>

                            {{-- Sender --}}
                            <td class="whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <div class="avatar avatar-sm bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                        {{ strtoupper(substr($ticket->sender_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $ticket->sender_name }}</p>
                                        <p class="text-xs text-slate-400 dark:text-slate-500">{{ $ticket->sender_email }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Category --}}
                            <td class="whitespace-nowrap">
                                @if($ticket->category)
                                    @php
                                        $catCls = match($ticket->category) {
                                            \App\Enums\TicketCategory::TechnicalQuestion => 'bg-violet-50 text-violet-700 ring-violet-200',
                                            \App\Enums\TicketCategory::RefundRequest     => 'bg-rose-50 text-rose-700 ring-rose-200',
                                            default => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ring-1 {{ $catCls }}">
                                        {{ $ticket->category->value }}
                                    </span>
                                @else
                                    <span class="text-slate-400 text-xs italic">—</span>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td class="text-center whitespace-nowrap">
                                @php
                                    $statusCls = match($ticket->status->value) {
                                        'open'       => 'badge-open',
                                        'closed'     => 'badge-closed',
                                        'new'        => 'bg-blue-50 text-blue-700 ring-1 ring-blue-200',
                                        'processing' => 'bg-purple-50 text-purple-700 ring-1 ring-purple-200',
                                        'resolved'   => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
                                        default      => 'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
                                    };
                                @endphp
                                <span class="badge {{ $statusCls }}">
                                    {{ ucfirst($ticket->status->value) }}
                                </span>
                            </td>

                            {{-- Assigned Agent --}}
                            <td class="whitespace-nowrap">
                                @if($ticket->assignedAgent)
                                    <div class="flex items-center gap-2">
                                        <div class="avatar avatar-sm gradient-brand text-white">
                                            {{ strtoupper(substr($ticket->assignedAgent->name, 0, 1)) }}
                                        </div>
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $ticket->assignedAgent->name }}</span>
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs text-slate-400 italic">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        Unassigned
                                    </span>
                                @endif
                            </td>

                            {{-- Created At --}}
                            <td class="text-right whitespace-nowrap">
                                <span class="text-xs text-slate-500 font-medium" title="{{ $ticket->created_at->format('Y-m-d H:i:s') }}">
                                    {{ $ticket->created_at->diffForHumans() }}
                                </span>
                            </td>

                            {{-- Actions --}}
                            <td class="text-center whitespace-nowrap">
                                <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" onsubmit="return confirm('Are you sure you want to permanently delete Ticket #{{ $ticket->id }}?');" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-slate-400 hover:text-red-600 dark:hover:text-red-400 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors" title="Delete Ticket #{{ $ticket->id }}">
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

            {{-- Pagination --}}
            @if($tickets->hasPages())
            <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700 bg-white dark:bg-transparent">
                {{ $tickets->links() }}
            </div>
            @endif
        </div>
    @endif

</x-app-layout>
