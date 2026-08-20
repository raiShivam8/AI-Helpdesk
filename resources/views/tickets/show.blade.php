{{--
    tickets/show.blade.php
    ──────────────────────
    Ticket Detail page. Sections:
      1. Topbar header  — back button, ticket subject & meta
      2. Flash alert    — success (auto-dismiss) / validation error
      3. Two-column grid
         LEFT  (2/3): Sender card · Original message · Reply thread · Reply form
         RIGHT (1/3): Ticket Details form · Assigned Agent · Timeline

    Reply thread uses chronological order (oldest → newest).
    Each reply bubble is styled by SenderType:
      SenderType::Agent    → left-aligned indigo/brand thread (internal team)
      SenderType::Customer → right-aligned amber thread (customer voice)

    The reply form preserves old('body') after a validation failure so the
    user never loses their drafted reply.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3 w-full min-w-0">
            {{-- Back button --}}
            <a href="{{ route('tickets.index') }}"
               class="flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition-all duration-150 shrink-0"
               title="Back to Tickets">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>

            {{-- Subject + meta --}}
            <div class="min-w-0">
                <h1 class="text-[15px] font-bold text-slate-900 dark:text-white leading-tight truncate" title="{{ $ticket->subject }}">
                    {{ $ticket->subject }}
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    <span class="font-semibold text-slate-700 dark:text-slate-300">Ticket #{{ $ticket->id }}</span>
                    &middot;
                    Opened {{ $ticket->created_at->diffForHumans() }}
                    &middot;
                    <span class="font-medium">{{ $ticket->replies->count() }}
                        {{ Str::plural('reply', $ticket->replies->count()) }}</span>
                </p>
            </div>

            {{-- Try AI Resolve action / AI Resolved badge --}}
            <div class="ml-auto flex items-center gap-1.5 sm:gap-2 shrink-0">
                @if($ticket->ai_resolved_at === null && ($ticket->status === \App\Enums\TicketStatus::Open || $ticket->status === \App\Enums\TicketStatus::New))
                    <form method="POST" action="{{ route('tickets.try-ai-resolve', $ticket) }}">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-2.5 sm:px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white shadow-sm transition duration-150"
                                title="Try AI Auto-Resolve">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 21l-1.813-5.096L2.091 14.09 7.187 13.28 9 8.187l1.813 5.096 5.096 1.813-5.096 1.813z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 3v4m-2-2h4" />
                            </svg>
                            <span>AI Resolve</span>
                        </button>
                    </form>
                @elseif($ticket->ai_resolved_at !== null)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-purple-50 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 ring-1 ring-purple-200 dark:ring-purple-700/50">
                        <svg class="w-3.5 h-3.5 text-purple-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span class="hidden sm:inline">Resolved by AI</span>
                        <span class="sm:hidden">AI Done</span>
                    </span>
                @endif

                {{-- Delete Ticket button --}}
                <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" onsubmit="return confirm('Are you sure you want to permanently delete Ticket #{{ $ticket->id }}?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-50 hover:bg-red-100 text-red-600 dark:bg-red-900/30 dark:hover:bg-red-900/50 dark:text-red-300 border border-red-200 dark:border-red-800 transition duration-150"
                            title="Delete Ticket Permanently">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span class="hidden sm:inline">Delete</span>
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    {{-- ══════════════════════════════════════════════
         FLASH ALERT — success (auto-dismiss after 5 s)
    ══════════════════════════════════════════════ --}}
    @if (session('success'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            class="mb-5 flex items-center justify-between p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-800 shadow-sm"
            role="alert"
        >
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="font-semibold">{{ session('success') }}</span>
            </div>
            <button @click="show = false" class="text-emerald-600 hover:text-emerald-900 ml-4 text-lg font-bold leading-none">&times;</button>
        </div>
    @endif

    @if (session('error'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            class="mb-5 flex items-center justify-between p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-800 shadow-sm"
            role="alert"
        >
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="font-semibold">{{ session('error') }}</span>
            </div>
            <button @click="show = false" class="text-red-600 hover:text-red-900 ml-4 text-lg font-bold leading-none">&times;</button>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════
         MAIN LAYOUT — 2/3 left | 1/3 right
    ══════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- ══════════════════════════════════════════
             LEFT COLUMN
        ════════════════════════════════════════════ --}}
        <div
            class="lg:col-span-2 space-y-5"
            x-data="{
                summary: null,
                isSummarizing: false,
                summaryError: null,
                selectedImageModal: null,
                async generateSummary(force = false) {
                    if (this.isSummarizing) return;
                    this.isSummarizing = true;
                    this.summaryError = null;
                    try {
                        const response = await fetch('{{ route('tickets.summarize', $ticket) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ force: force })
                        });
                        
                        let data = null;
                        const contentType = response.headers.get('content-type');
                        if (contentType && contentType.includes('application/json')) {
                            data = await response.json();
                        }
                        
                        if (!response.ok) {
                            const errorMsg = data && (data.error || data.message)
                                ? (data.error || data.message)
                                : `Server returned error status ${response.status}`;
                            throw new Error(errorMsg);
                        }
                        
                        if (!data || !data.success || !data.summary) {
                            throw new Error('Invalid summary response received from server.');
                        }
                        
                        this.summary = data.summary;
                    } catch (e) {
                        this.summaryError = e.message;
                    } finally {
                        this.isSummarizing = false;
                    }
                }
            }"
        >

            {{-- ── Sender Card ── --}}
            <div class="card p-5">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">From</p>
                <div class="flex items-center gap-3.5">
                    <div class="avatar avatar-lg bg-amber-100 text-amber-700 font-bold">
                        {{ strtoupper(substr($ticket->sender_name, 0, 1)) }}
                    </div>
                    <div>
                        <p class="font-semibold text-slate-900 dark:text-white text-base leading-tight">{{ $ticket->sender_name }}</p>
                        <a href="mailto:{{ $ticket->sender_email }}" class="text-sm text-indigo-600 hover:underline mt-0.5 block">
                            {{ $ticket->sender_email }}
                        </a>
                        {{-- Customer badge --}}
                        <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 text-[11px] font-semibold rounded-full bg-amber-50 text-amber-700 ring-1 ring-amber-200">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Customer
                        </span>
                    </div>
                </div>
            </div>

            {{-- ── AI Summary Card ── --}}
            <div x-show="summary || isSummarizing || summaryError" x-cloak class="card p-5 border border-indigo-100 dark:border-indigo-900/50 bg-gradient-to-r from-indigo-50/30 dark:from-indigo-900/20 to-purple-50/30 dark:to-purple-900/20 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-indigo-100 dark:border-indigo-900/50 pb-3">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 21l-1.813-5.096L2.091 14.09 7.187 13.28 9 8.187l1.813 5.096 5.096 1.813-5.096 1.813z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 3v4m-2-2h4" />
                        </svg>
                        <h3 class="text-sm font-bold text-indigo-900 dark:text-indigo-300 tracking-wide uppercase">AI-Powered Ticket Summary</h3>
                    </div>
                    <button type="button" @click="summary = null; summaryError = null" class="text-slate-400 hover:text-slate-600 font-bold text-lg leading-none" title="Dismiss Summary">&times;</button>
                </div>

                {{-- Loading State spinner --}}
                <div x-show="isSummarizing" class="flex flex-col items-center justify-center py-6 space-y-3">
                    <svg class="animate-spin h-7 w-7 text-indigo-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Gemini is analyzing the conversation thread...</p>
                </div>

                {{-- Error Banner --}}
                <div x-show="summaryError" class="p-3 bg-red-50 border border-red-200 text-xs text-red-800 rounded-lg flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span x-text="summaryError"></span>
                    </div>
                    <button type="button" @click="summaryError = null" class="text-red-600 hover:text-red-900 font-bold leading-none">&times;</button>
                </div>

                {{-- Summary Sections --}}
                <div x-show="summary && !isSummarizing" class="space-y-4 text-sm text-slate-800 dark:text-slate-200">
                    {{-- Summary Paragraph --}}
                    <div class="bg-white dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700 rounded-xl p-4 shadow-sm">
                        <p class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-2">Concise Summary</p>
                        <p class="text-slate-700 dark:text-slate-300 leading-relaxed text-[13px]" x-text="summary.summary"></p>
                    </div>

                    {{-- Customer Issues and Actions Taken side-by-side --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-white dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700 rounded-xl p-4 shadow-sm">
                            <p class="text-xs font-semibold text-amber-500 uppercase tracking-wider mb-2 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                Important Issues
                            </p>
                            <ul class="list-disc pl-4 space-y-1 text-xs text-slate-600 dark:text-slate-400">
                                <template x-for="issue in summary.issues">
                                    <li x-text="issue"></li>
                                </template>
                            </ul>
                        </div>

                        <div class="bg-white dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700 rounded-xl p-4 shadow-sm">
                            <p class="text-xs font-semibold text-indigo-500 uppercase tracking-wider mb-2 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 022 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                                Actions Taken
                            </p>
                            <ul class="list-disc pl-4 space-y-1 text-xs text-slate-600 dark:text-slate-400">
                                <template x-for="action in summary.actions_taken">
                                    <li x-text="action"></li>
                                </template>
                            </ul>
                        </div>
                    </div>

                    {{-- Current Status and Suggested Next Step side-by-side --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-white dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700 rounded-xl p-4 shadow-sm">
                            <p class="text-xs font-semibold text-emerald-500 uppercase tracking-wider mb-2 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Current Status
                            </p>
                            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed" x-text="summary.status"></p>
                        </div>

                        <div class="bg-white dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700 rounded-xl p-4 shadow-sm bg-gradient-to-br from-purple-50/50 dark:from-purple-900/20 to-indigo-50/50 dark:to-indigo-900/20">
                            <p class="text-xs font-semibold text-purple-600 dark:text-purple-400 uppercase tracking-wider mb-2 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                                </svg>
                                Suggested Next Step
                            </p>
                            <p class="text-xs font-bold text-purple-800 dark:text-purple-300 leading-relaxed" x-text="summary.next_step"></p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Original Message (Customer bubble) ── --}}
            @php
                $firstReply = $ticket->replies->first();
            @endphp
            <div class="flex flex-col items-end">
                {{-- Right-aligned label --}}
                <p class="text-xs font-semibold text-amber-500 uppercase tracking-wider mb-2 mr-1">
                    Original Message · Customer
                </p>

                <div class="w-full bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 rounded-2xl rounded-tr-sm p-5 shadow-sm">
                    <div class="text-slate-800 dark:text-slate-200 leading-relaxed text-[15px] [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-xl [&_img]:shadow-sm [&_img]:my-3 [&_img]:border [&_img]:border-amber-200 dark:[&_img]:border-amber-700 [&_img]:cursor-pointer @unless($firstReply?->formatted_body_html) whitespace-pre-wrap @endunless" @click="if ($event.target.tagName === 'IMG') selectedImageModal = $event.target.src">
                        @if($firstReply?->formatted_body_html)
                            {!! $firstReply->formatted_body_html !!}
                        @else
                            {{ $ticket->body }}
                        @endif
                    </div>

                    {{-- Original Email Image Attachment Gallery --}}
                    @if($firstReply && $firstReply->hasAttachment())
                        <div class="mt-4 pt-4 border-t border-amber-200/80 dark:border-amber-800/80">
                            @if($firstReply->isImageAttachment())
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-1.5 text-xs font-bold text-amber-800 dark:text-amber-300 uppercase tracking-wider">
                                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            Customer Screenshot / Image Attachment
                                        </div>
                                        @if($firstReply->isImageProcessing())
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 text-[10px] font-bold rounded-full bg-amber-100 text-amber-800 ring-1 ring-amber-300 animate-pulse">
                                                <svg class="w-3 h-3 animate-spin text-amber-600" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                </svg>
                                                Optimizing Copy...
                                            </span>
                                        @elseif($firstReply->isImageOptimized())
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-300">
                                                ✓ Web Optimized (500KB-1MB)
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap gap-3">
                                        <div class="group relative max-w-sm rounded-xl overflow-hidden border border-amber-300 dark:border-amber-700 shadow-sm bg-white dark:bg-slate-900">
                                            <img src="{{ $firstReply->attachment_url }}" alt="{{ $firstReply->attachment_name }}" class="w-full h-auto object-cover max-h-64 cursor-pointer group-hover:scale-105 transition-transform duration-200" @click="selectedImageModal = '{{ $firstReply->attachment_url }}'" />
                                            <div class="p-2.5 bg-white/95 dark:bg-slate-900/95 border-t border-amber-100 dark:border-amber-800/50 flex items-center justify-between gap-2 text-xs">
                                                <span class="truncate font-semibold text-slate-800 dark:text-slate-200 max-w-[180px]" title="{{ $firstReply->attachment_name }}">{{ $firstReply->attachment_name }}</span>
                                                <div class="flex items-center gap-1 shrink-0">
                                                    <button type="button" @click="selectedImageModal = '{{ $firstReply->attachment_url }}'" class="px-2 py-1 text-[11px] font-bold rounded bg-amber-100 hover:bg-amber-200 text-amber-800 transition">View Full</button>
                                                    <a href="{{ $firstReply->attachment_original_url }}" download="{{ $firstReply->attachment_name }}" class="px-2 py-1 text-[11px] font-bold rounded bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition" title="Download original uncompressed file">↓ Original</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="space-y-1.5">
                                    <p class="text-[11px] font-semibold text-amber-700 dark:text-amber-300 uppercase tracking-wider">Attached Document</p>
                                    <a href="{{ $firstReply->attachment_url }}" download="{{ $firstReply->attachment_name }}" class="inline-flex items-center gap-2 px-3 py-2 bg-white dark:bg-slate-800 rounded-xl border border-amber-200 dark:border-amber-700 hover:border-amber-400 text-xs font-semibold text-slate-800 dark:text-slate-200 shadow-2xs transition-colors">
                                        <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <span class="truncate max-w-[200px]">{{ $firstReply->attachment_name }}</span>
                                        <span class="text-amber-600 dark:text-amber-400 font-bold ml-1">Download ↓</span>
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endif

                    <p class="text-xs text-amber-400 mt-4 pt-3 border-t border-amber-100 text-right">
                        {{ $ticket->sender_name }} &middot; {{ $ticket->created_at->format('M j, Y \a\t g:i a') }}
                    </p>
                </div>
            </div>

            {{-- ══════════════════════════════════════
                 REPLY THREAD
                 Chronological, oldest first.
                 Agent  → left-aligned indigo bubble
                 Customer → right-aligned amber bubble
            ════════════════════════════════════════ --}}
            @php
                // Filter replies: If the first reply is identical to the initial ticket message, don't duplicate it in the reply thread
                $threadReplies = $ticket->replies->filter(function($r, $idx) use ($ticket) {
                    if ($idx === 0 && $r->sender_type === \App\Enums\SenderType::Customer && $r->body === $ticket->body) {
                        return false;
                    }
                    return true;
                });
            @endphp

            @if($threadReplies->isNotEmpty())
            <div class="space-y-4">

                {{-- Section divider --}}
                <div class="flex items-center gap-3">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider whitespace-nowrap">
                        Conversation ({{ $threadReplies->count() }}
                        {{ Str::plural('reply', $threadReplies->count()) }})
                    </p>
                    <div class="flex-1 h-px bg-slate-200 dark:bg-slate-700"></div>
                </div>

                @foreach($threadReplies as $reply)

                    {{-- System Transfer Event Banner --}}
                    @if($reply->sender_type === \App\Enums\SenderType::System)
                        <div class="my-3 flex items-center justify-center w-full">
                            <div class="w-full max-w-xl p-3.5 rounded-2xl bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 shadow-2xs flex items-start gap-3">
                                <div class="p-2 rounded-xl bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 shrink-0 mt-0.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0 text-xs">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-bold text-amber-900 dark:text-amber-200">Ticket Reassigned</span>
                                        <span class="text-[10px] text-amber-600 dark:text-amber-400 font-medium">{{ $reply->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-amber-800 dark:text-amber-300 mt-1 leading-relaxed whitespace-pre-wrap">{{ $reply->body }}</p>
                                    @if($reply->transfer_reason && auth()->user()?->isAdmin())
                                        <div class="mt-2 p-2.5 rounded-xl bg-white/90 dark:bg-slate-900/70 border border-amber-200 dark:border-amber-800/50 text-slate-800 dark:text-slate-200">
                                            <span class="font-bold text-amber-700 dark:text-amber-400">Transfer Reason (Admin Only):</span> {{ $reply->transfer_reason }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                    {{-- Agent reply --}}
                    @elseif($reply->sender_type === \App\Enums\SenderType::Agent)
                    <div class="flex flex-col items-start">
                        {{-- Agent / AI label row --}}
                        <div class="flex items-center gap-2 mb-1.5 ml-1">
                            <div class="avatar avatar-sm bg-indigo-100 text-indigo-700 font-semibold shrink-0">
                                {{ $reply->user ? strtoupper(substr($reply->user->name, 0, 1)) : 'AI' }}
                            </div>
                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                {{ $reply->user?->name ?? 'AI Assistant' }}
                            </span>
                            @if($reply->user)
                                @if($reply->user->isAdmin())
                                    <span class="badge badge-admin">Admin</span>
                                @else
                                    <span class="badge badge-agent">Agent</span>
                                @endif
                            @else
                                <span class="badge bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200">{{ $reply->sender_type->label() }}</span>
                            @endif
                            <time
                                class="text-[11px] text-slate-400 font-medium"
                                datetime="{{ $reply->created_at->toIso8601String() }}"
                                title="{{ $reply->created_at->format('Y-m-d H:i:s') }}"
                            >· {{ $reply->created_at->diffForHumans() }}</time>
                        </div>

                        {{-- Agent bubble: left-aligned, indigo tint --}}
                        <div class="max-w-[90%] bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-800/50 rounded-2xl rounded-tl-sm p-4 shadow-sm">
                            <div class="text-slate-800 dark:text-slate-200 leading-relaxed text-sm [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-xl [&_img]:shadow-sm [&_img]:my-2 [&_img]:border [&_img]:border-indigo-200 dark:[&_img]:border-indigo-700 [&_img]:cursor-pointer @unless($reply->body_html) whitespace-pre-wrap @endunless">
                                @if($reply->body_html)
                                    {!! $reply->body_html !!}
                                @else
                                    {{ $reply->body }}
                                @endif
                            </div>

                            {{-- Reply Attachment Rendering --}}
                            @if($reply->hasAttachment())
                                <div class="mt-3 pt-3 border-t border-indigo-200/60 dark:border-indigo-800/60">
                                    @if($reply->isImageAttachment())
                                        <div class="space-y-1.5">
                                            <p class="text-[11px] font-semibold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider">Attached Image</p>
                                            <div class="group relative max-w-xs overflow-hidden rounded-xl border border-indigo-200 dark:border-indigo-700 bg-white dark:bg-slate-900 shadow-2xs">
                                                <img src="{{ $reply->attachment_url }}" alt="{{ $reply->attachment_name }}" class="w-full h-auto object-cover max-h-48 cursor-pointer group-hover:scale-105 transition-transform duration-200" @click="selectedImageModal = '{{ $reply->attachment_url }}'" />
                                                <div class="p-2 bg-white/95 dark:bg-slate-900/95 border-t border-indigo-100 dark:border-indigo-800/50 flex items-center justify-between text-xs">
                                                    <span class="truncate font-semibold text-slate-700 dark:text-slate-300 max-w-[150px]" title="{{ $reply->attachment_name }}">{{ $reply->attachment_name }}</span>
                                                    <button type="button" @click="selectedImageModal = '{{ $reply->attachment_url }}'" class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800">View</button>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="space-y-1.5">
                                            <p class="text-[11px] font-semibold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider">Attached Document</p>
                                            <a href="{{ $reply->attachment_url }}" download="{{ $reply->attachment_name }}" class="inline-flex items-center gap-2 px-3 py-2 bg-white dark:bg-slate-800 rounded-xl border border-indigo-200 dark:border-indigo-700 hover:border-indigo-400 text-xs font-semibold text-slate-800 dark:text-slate-200 shadow-2xs transition-colors">
                                                <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <span class="truncate max-w-[200px]">{{ $reply->attachment_name }}</span>
                                                <span class="text-indigo-600 dark:text-indigo-400 font-bold ml-1">Download ↓</span>
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- ╔════════════════════════════════════╗
                         ║  CUSTOMER reply — right-aligned    ║
                         ╚════════════════════════════════════╝ --}}
                    @else
                    <div class="flex flex-col items-end">
                        {{-- Customer label row --}}
                        <div class="flex items-center gap-2 mb-1.5 mr-1">
                            <time
                                class="text-[11px] text-slate-400 font-medium"
                                datetime="{{ $reply->created_at->toIso8601String() }}"
                                title="{{ $reply->created_at->format('Y-m-d H:i:s') }}"
                            >{{ $reply->created_at->diffForHumans() }} ·</time>
                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                {{ $ticket->sender_name }}
                            </span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-semibold rounded-full bg-amber-50 text-amber-700 ring-1 ring-amber-200">
                                Customer
                            </span>
                            <div class="avatar avatar-sm bg-amber-100 text-amber-700 font-semibold shrink-0">
                                {{ strtoupper(substr($ticket->sender_name, 0, 1)) }}
                            </div>
                        </div>

                        {{-- Customer bubble: right-aligned, amber tint --}}
                        <div class="max-w-[90%] bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 rounded-2xl rounded-tr-sm p-4 shadow-sm">
                            <div class="text-slate-800 dark:text-slate-200 leading-relaxed text-sm [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-xl [&_img]:shadow-sm [&_img]:my-2 [&_img]:border [&_img]:border-amber-200 dark:[&_img]:border-amber-700 [&_img]:cursor-pointer @unless($reply->body_html) whitespace-pre-wrap @endunless">
                                @if($reply->body_html)
                                    {!! $reply->body_html !!}
                                @else
                                    {{ $reply->body }}
                                @endif
                            </div>

                            {{-- Customer Attachment Rendering --}}
                            @if($reply->hasAttachment())
                                <div class="mt-3 pt-3 border-t border-amber-200/60 dark:border-amber-800/60">
                                    @if($reply->isImageAttachment())
                                        <div class="space-y-1.5">
                                            <p class="text-[11px] font-semibold text-amber-700 dark:text-amber-300 uppercase tracking-wider">Attached Image</p>
                                            <div class="group relative max-w-xs overflow-hidden rounded-xl border border-amber-200 dark:border-amber-700 bg-white dark:bg-slate-900 shadow-2xs">
                                                <img src="{{ $reply->attachment_url }}" alt="{{ $reply->attachment_name }}" class="w-full h-auto object-cover max-h-48 cursor-pointer group-hover:scale-105 transition-transform duration-200" @click="selectedImageModal = '{{ $reply->attachment_url }}'" />
                                                <div class="p-2 bg-white/95 dark:bg-slate-900/95 border-t border-amber-100 dark:border-amber-800/50 flex items-center justify-between text-xs">
                                                    <span class="truncate font-semibold text-slate-700 dark:text-slate-300 max-w-[150px]" title="{{ $reply->attachment_name }}">{{ $reply->attachment_name }}</span>
                                                    <button type="button" @click="selectedImageModal = '{{ $reply->attachment_url }}'" class="text-[11px] font-bold text-amber-700 hover:text-amber-900">View</button>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="space-y-1.5">
                                            <p class="text-[11px] font-semibold text-amber-700 dark:text-amber-300 uppercase tracking-wider">Attached Document</p>
                                            <a href="{{ $reply->attachment_url }}" download="{{ $reply->attachment_name }}" class="inline-flex items-center gap-2 px-3 py-2 bg-white dark:bg-slate-800 rounded-xl border border-amber-200 dark:border-amber-700 hover:border-amber-400 text-xs font-semibold text-slate-800 dark:text-slate-200 shadow-2xs transition-colors">
                                                <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <span class="truncate max-w-[200px]">{{ $reply->attachment_name }}</span>
                                                <span class="text-amber-600 dark:text-amber-400 font-bold ml-1">Download ↓</span>
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                    @endif

                @endforeach
            </div>
            @endif

            {{-- ── Summarize Button ── --}}
            <div class="flex justify-end mt-4 mb-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 px-4.5 py-2 text-sm font-semibold rounded-lg border border-indigo-200 dark:border-indigo-700/60 bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 disabled:opacity-50 disabled:cursor-not-allowed transition duration-150 shadow-sm"
                    :disabled="isSummarizing"
                    @click="generateSummary(false)"
                >
                    <svg x-show="isSummarizing" x-cloak class="animate-spin h-4 w-4 text-indigo-700" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{-- Heroicon sparkles icon --}}
                    <svg x-show="!isSummarizing" class="w-4 h-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 21l-1.813-5.096L2.091 14.09 7.187 13.28 9 8.187l1.813 5.096 5.096 1.813-5.096 1.813z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 3v4m-2-2h4" />
                    </svg>
                    <span x-text="isSummarizing ? 'Summarizing...' : 'Summarize'"></span>
                </button>
            </div>

            {{-- ══════════════════════════════════════
                 REPLY FORM (Agent)
                 Always visible. Preserves old('body').
                 Disabled submit when textarea is empty.
            ════════════════════════════════════════ --}}
            <div class="card p-5" id="reply-form">
                {{-- Who is posting --}}
                <div class="flex items-center gap-3 mb-5">
                    <div class="avatar avatar-md gradient-brand text-white font-semibold shrink-0">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ auth()->user()->name }}</p>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Replying as</p>
                            @if(auth()->user()->isAdmin())
                                <span class="badge badge-admin">Admin</span>
                            @else
                                <span class="badge badge-agent">Agent</span>
                            @endif
                            {{-- SenderType indicator --}}
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-semibold rounded-full bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                {{ \App\Enums\SenderType::Agent->label() }}
                            </span>
                        </div>
                    </div>
                </div>

                <form
                    method="POST"
                    action="{{ route('tickets.replies.store', $ticket) }}"
                    enctype="multipart/form-data"
                    x-data="{
                        body: '{{ old('body') ? addslashes(old('body')) : '' }}',
                        charCount: {{ strlen(old('body', '')) }},
                        fileName: null,
                        fileSize: null,
                        isImage: false,
                        isPolishing: false,
                        polishError: null,
                        handleFile(e) {
                            const file = e.target.files[0];
                            if (file) {
                                this.fileName = file.name;
                                this.fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                                this.isImage = file.type.startsWith('image/');
                            } else {
                                this.clearFile();
                            }
                        },
                        clearFile() {
                            this.fileName = null;
                            this.fileSize = null;
                            this.isImage = false;
                            $refs.fileInput.value = '';
                        },
                        async polish() {
                            if (this.body.trim().length === 0 || this.isPolishing) return;
                            this.isPolishing = true;
                            this.polishError = null;
                            try {
                                const response = await fetch('{{ route('tickets.polish-reply', $ticket) }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify({ body: this.body })
                                });
                                
                                let data = null;
                                const contentType = response.headers.get('content-type');
                                if (contentType && contentType.includes('application/json')) {
                                    data = await response.json();
                                }
                                
                                if (!response.ok) {
                                    const errorMsg = data && (data.error || data.message)
                                        ? (data.error || data.message)
                                        : `Server returned error status ${response.status}: ${response.statusText || 'Unknown error'}`;
                                    throw new Error(errorMsg);
                                }
                                
                                if (!data || !data.polished) {
                                    throw new Error('Invalid response received from the server.');
                                }
                                
                                this.body = data.polished;
                                this.charCount = this.body.length;
                            } catch (err) {
                                this.polishError = err.message;
                            } finally {
                                this.isPolishing = false;
                            }
                        }
                    }"
                >
                    @csrf

                    {{-- Error banner for API failure --}}
                    <div x-show="polishError" x-cloak class="mb-4 p-3.5 bg-red-50 border border-red-200 text-xs text-red-800 rounded-xl flex items-center justify-between shadow-sm">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <span x-text="polishError"></span>
                        </div>
                        <button type="button" @click="polishError = null" class="text-red-600 hover:text-red-900 font-bold ml-3 text-sm">&times;</button>
                    </div>

                    <div class="mb-4">
                        <label for="reply-body" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">
                            Reply Message <span class="text-red-500">*</span>
                        </label>

                        <textarea
                            id="reply-body"
                            name="body"
                            rows="5"
                            maxlength="2000"
                            x-model="body"
                            @input="charCount = body.length"
                            placeholder="Type your reply to the customer…"
                            class="w-full rounded-lg border text-sm text-slate-900 dark:text-slate-100 bg-white dark:bg-slate-800/60 placeholder-slate-400 dark:placeholder-slate-500 leading-relaxed transition duration-150 resize-y
                                   {{ $errors->has('body') ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/30' : 'border-slate-300 dark:border-slate-600 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30' }}"
                        >{{ old('body') }}</textarea>

                        {{-- Validation error --}}
                        @error('body')
                            <p class="mt-1.5 text-xs font-medium text-red-600 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror

                        {{-- Hidden File & Image Input --}}
                        <input
                            type="file"
                            name="attachment"
                            id="reply-attachment"
                            x-ref="fileInput"
                            @change="handleFile($event)"
                            class="hidden"
                            accept="image/*,.pdf,.doc,.docx,.zip,.txt"
                        />

                        {{-- Selected File Preview Badge --}}
                        <div x-show="fileName" x-cloak class="mb-3 p-2.5 bg-indigo-50/60 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-800/50 rounded-xl flex items-center justify-between">
                            <div class="flex items-center gap-2 min-w-0">
                                <template x-if="isImage">
                                    <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </template>
                                <template x-if="!isImage">
                                    <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                </template>
                                <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate" x-text="fileName"></span>
                                <span class="text-[10px] text-slate-400 font-mono" x-text="fileSize"></span>
                            </div>
                            <button type="button" @click="clearFile()" class="text-slate-400 hover:text-red-500 font-bold ml-2 text-sm">&times;</button>
                        </div>

                        {{-- Validation error for attachment --}}
                        @error('attachment')
                            <p class="mt-1 text-xs font-medium text-red-600 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror

                        {{-- Character counter --}}
                        <p class="mt-1.5 text-xs text-slate-400 text-right">
                            <span x-text="charCount"></span> / 2,000
                        </p>
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-700">
                        <p class="text-xs text-slate-400 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Stored as <strong class="font-semibold text-indigo-600">{{ \App\Enums\SenderType::Agent->label() }}</strong> reply.
                        </p>

                        <div class="flex items-center gap-2">
                            {{-- Single Unified Attachment Button (File & Image) --}}
                            <button
                                type="button"
                                @click="$refs.fileInput.click()"
                                class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700/60 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition duration-150"
                                title="Attach File or Image (JPG, PNG, PDF, DOC, ZIP)"
                            >
                                <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                </svg>
                                <span>Attach File / Image</span>
                            </button>

                            {{-- Polish Reply Button --}}
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 px-4.5 py-2 text-sm font-semibold rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700/60 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 hover:text-slate-800 dark:hover:text-white disabled:opacity-50 disabled:cursor-not-allowed transition duration-150"
                                :disabled="body.trim().length === 0 || isPolishing"
                                @click="polish()"
                            >
                                <svg x-show="isPolishing" x-cloak class="animate-spin h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg x-show="!isPolishing" class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 21l-1.813-5.096L2.091 14.09 7.187 13.28 9 8.187l1.813 5.096 5.096 1.813-5.096 1.813z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 3v4m-2-2h4" />
                                </svg>
                                <span x-text="isPolishing ? 'Polishing...' : 'Polish Reply'"></span>
                            </button>

                            {{-- Post Reply Button --}}
                            <button
                                type="submit"
                                class="btn-primary"
                                :disabled="body.trim().length === 0 || isPolishing"
                                :class="(body.trim().length === 0 || isPolishing) ? 'opacity-50 cursor-not-allowed' : ''"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                                Post Reply
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        </div>{{-- /left column --}}

        {{-- ══════════════════════════════════════════
             RIGHT COLUMN — metadata sidebar
        ════════════════════════════════════════════ --}}
        <div class="space-y-4">

            {{-- ── Ticket Details / Update Form ── --}}
            <div class="card p-5">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-4">Ticket Details</p>

                <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    {{-- Status --}}
                    <div>
                        <label for="status" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Status</label>
                        <select name="status" id="status" class="form-select">
                            @foreach(\App\Enums\TicketStatus::cases() as $st)
                                <option value="{{ $st->value }}" {{ old('status', $ticket->status->value) === $st->value ? 'selected' : '' }}>
                                    {{ ucfirst($st->value) }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Customer Email --}}
                    <div>
                        <label for="sender_email" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Customer Email</label>
                        <input
                            type="email"
                            name="sender_email"
                            id="sender_email"
                            value="{{ old('sender_email', $ticket->sender_email) }}"
                            placeholder="customer@example.com"
                            class="form-input text-xs"
                        />
                        @error('sender_email')
                            <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Category --}}
                    <div>
                        <label for="category" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Category</label>
                        <select name="category" id="category" class="form-select">
                            <option value="">Uncategorized</option>
                            @foreach(\App\Enums\TicketCategory::cases() as $cat)
                                <option value="{{ $cat->value }}" {{ old('category', $ticket->category?->value) === $cat->value ? 'selected' : '' }}>
                                    {{ $cat->value }}
                                </option>
                            @endforeach
                        </select>
                        @error('category')
                            <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="btn-primary w-full justify-center mt-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Save Changes
                    </button>
                </form>
            </div>

            {{-- ── Assigned Agent / Transfer Ticket ── --}}
            <div class="card p-5" x-data="{
                showReason: false,
                selectedAgent: '{{ $ticket->assigned_agent_id }}',
                originalAgent: '{{ $ticket->assigned_agent_id }}',
                checkAgentChange() {
                    this.showReason = (this.selectedAgent && this.selectedAgent != this.originalAgent);
                }
            }">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Assigned Agent</p>
                    <template x-if="showReason">
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                            Transfer Pending
                        </span>
                    </template>
                </div>

                @if(auth()->user()->isAdmin() || auth()->user()->isAgent())
                    @if($ticket->assignedAgent)
                        <div class="flex items-center gap-2.5 mb-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                            <div class="avatar avatar-sm gradient-brand text-white shrink-0">
                                {{ strtoupper(substr($ticket->assignedAgent->name, 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <span class="font-semibold text-slate-800 dark:text-slate-200 text-xs block truncate">{{ $ticket->assignedAgent->name }}</span>
                                <span class="text-[10px] text-slate-400">Currently Assigned</span>
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('tickets.assign', $ticket) }}" class="space-y-3">
                        @csrf
                        @method('PATCH')
                        <div>
                            <select
                                name="assigned_agent_id"
                                id="assigned_agent_id"
                                x-model="selectedAgent"
                                @change="checkAgentChange()"
                                class="form-select text-xs font-medium"
                            >
                                <option value="">Unassigned</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}" {{ $ticket->assigned_agent_id === $agent->id ? 'selected' : '' }}>
                                        {{ $agent->name }} {{ $agent->id === auth()->id() ? '(You)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('assigned_agent_id')
                                <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Transfer Reason Box (Shows when reassigning) --}}
                        <div x-show="showReason" x-cloak class="space-y-2 pt-1">
                            <label for="transfer_reason" class="block text-xs font-semibold text-slate-600 dark:text-slate-400">
                                Transfer Reason <span class="text-amber-500 font-normal">(Why are you transferring this ticket?)</span>
                            </label>
                            <textarea
                                name="transfer_reason"
                                id="transfer_reason"
                                rows="3"
                                placeholder="e.g. Customer needs specialized support / Agent handles billing..."
                                class="form-input text-xs"
                            ></textarea>
                            @error('transfer_reason')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror

                            <button type="submit" class="btn-primary w-full justify-center text-xs py-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                                Confirm Ticket Transfer
                            </button>
                        </div>
                    </form>
                @else
                    @if($ticket->assignedAgent)
                        <div class="flex items-center gap-2.5">
                            <div class="avatar avatar-sm gradient-brand text-white">
                                {{ strtoupper(substr($ticket->assignedAgent->name, 0, 1)) }}
                            </div>
                            <span class="font-medium text-slate-700 dark:text-slate-300 text-sm">{{ $ticket->assignedAgent->name }}</span>
                        </div>
                    @else
                        <span class="text-sm text-slate-400 italic">Not yet assigned</span>
                    @endif
                @endif
                {{-- Latest Transfer Reason Callout (Admin Only) --}}
                @php
                    $latestTransfer = $ticket->replies->where('sender_type', \App\Enums\SenderType::System)->filter(fn($r) => !empty($r->transfer_reason))->last();
                @endphp
                @if($latestTransfer && auth()->user()?->isAdmin())
                    <div class="mt-4 p-3 rounded-xl bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 text-xs">
                        <div class="flex items-center gap-1.5 font-bold text-amber-900 dark:text-amber-300 mb-1">
                            <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Latest Transfer Reason (Admin Only)
                        </div>
                        <p class="text-slate-800 dark:text-slate-200 leading-relaxed font-medium">"{{ $latestTransfer->transfer_reason }}"</p>
                        <p class="text-[10px] text-slate-400 mt-1.5 pt-1.5 border-t border-amber-200/50 dark:border-amber-800/40">
                            Transferred by <span class="font-semibold text-slate-600 dark:text-slate-300">{{ $latestTransfer->user?->name ?? 'Agent' }}</span> &middot; {{ $latestTransfer->created_at->diffForHumans() }}
                        </p>
                    </div>
                @endif
            </div>

            {{-- ── Conversation Stats ── --}}
            @if($ticket->replies->isNotEmpty())
            <div class="card p-5">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-4">Conversation Stats</p>
                <div class="space-y-3">
                    {{-- Agent replies count --}}
                    @php
                        $agentReplies    = $ticket->replies->filter(fn($r) => $r->sender_type === \App\Enums\SenderType::Agent)->count();
                        $customerReplies = $ticket->replies->filter(fn($r) => $r->sender_type === \App\Enums\SenderType::Customer)->count();
                    @endphp
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                            <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 inline-block shrink-0"></span>
                            Agent replies
                        </div>
                        <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $agentReplies }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-400 inline-block shrink-0"></span>
                            Customer replies
                        </div>
                        <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $customerReplies }}</span>
                    </div>
                    <div class="border-t border-slate-100 dark:border-slate-700 pt-3 flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">Total exchanges</span>
                        <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $ticket->replies->count() }}</span>
                    </div>
                </div>
            </div>
            @endif

            {{-- ── Timeline ── --}}
            <div class="card p-5">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-4">Timeline</p>
                <div class="space-y-4">
                    {{-- Created --}}
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Created</p>
                            <p class="text-sm text-slate-700 dark:text-slate-200 font-medium mt-0.5" title="{{ $ticket->created_at->format('Y-m-d H:i:s') }}">
                                {{ $ticket->created_at->format('M j, Y · g:i a') }}
                            </p>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $ticket->created_at->diffForHumans() }}</p>
                        </div>
                    </div>

                    <div class="w-px h-4 bg-slate-200 dark:bg-slate-700 ml-4"></div>

                    {{-- Last Updated --}}
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Last Updated</p>
                            <p class="text-sm text-slate-700 dark:text-slate-200 font-medium mt-0.5" title="{{ $ticket->updated_at->format('Y-m-d H:i:s') }}">
                                {{ $ticket->updated_at->format('M j, Y · g:i a') }}
                            </p>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $ticket->updated_at->diffForHumans() }}</p>
                        </div>
                    </div>

                    @if($ticket->replies->isNotEmpty())
                    <div class="w-px h-4 bg-slate-200 dark:bg-slate-700 ml-4"></div>

                    {{-- Last Reply --}}
                    @php $lastReply = $ticket->replies->last(); @endphp
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg {{ $lastReply->sender_type === \App\Enums\SenderType::Agent ? 'bg-indigo-50' : 'bg-amber-50' }} flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-4 h-4 {{ $lastReply->sender_type === \App\Enums\SenderType::Agent ? 'text-indigo-500' : 'text-amber-500' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Last Reply
                                <span class="ml-1 font-semibold {{ $lastReply->sender_type === \App\Enums\SenderType::Agent ? 'text-indigo-500' : 'text-amber-500' }}">
                                    · {{ $lastReply->sender_type->label() }}
                                </span>
                            </p>
                            <p class="text-sm text-slate-700 dark:text-slate-200 font-medium mt-0.5">
                                @if($lastReply->sender_type === \App\Enums\SenderType::Agent)
                                    {{ $lastReply->user?->name ?? 'Deleted User' }}
                                @else
                                    {{ $ticket->sender_name }}
                                @endif
                            </p>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $lastReply->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

        </div>{{-- /right column --}}
    </div>

    {{-- ══════════════════════════════════════════════
         IMAGE LIGHTBOX MODAL (Full screen preview)
    ══════════════════════════════════════════════ --}}
    <div x-show="selectedImageModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/80 backdrop-blur-sm p-4" @keydown.escape.window="selectedImageModal = null">
        <div class="relative max-w-4xl w-full bg-white dark:bg-slate-900 rounded-2xl shadow-2xl overflow-hidden border border-slate-200 dark:border-slate-800" @click.away="selectedImageModal = null">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Image & Screenshot Preview</span>
                <div class="flex items-center gap-2">
                    <a :href="selectedImageModal" download target="_blank" class="px-3 py-1 text-xs font-semibold rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300">
                        Download Original ↓
                    </a>
                    <button type="button" @click="selectedImageModal = null" class="text-slate-400 hover:text-slate-600 font-bold text-xl leading-none">&times;</button>
                </div>
            </div>
            <div class="p-4 flex items-center justify-center max-h-[80vh] bg-slate-950/90 overflow-auto">
                <img :src="selectedImageModal" alt="Full Image View" class="max-w-full max-h-[75vh] object-contain rounded-lg shadow-lg" />
            </div>
        </div>
    </div>
</x-app-layout>
