@if ($paginator->hasPages())
<nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="pagi-nav">

    <style>
        .pagi-nav {
            display: flex;
            align-items: center;
            justify-content: between;
            gap: 0;
            font-family: 'Inter', sans-serif;
        }
        .pagi-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            gap: 16px;
            flex-wrap: wrap;
        }

        /* Info text */
        .pagi-info {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
            white-space: nowrap;
        }
        .pagi-info strong {
            color: var(--text-primary);
            font-weight: 700;
        }

        /* Controls row */
        .pagi-controls {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Base button */
        .pagi-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 6px;
            font-size: 13.5px;
            font-weight: 600;
            border-radius: 10px;
            border: 1.5px solid transparent;
            text-decoration: none;
            transition: all 0.15s ease;
            cursor: pointer;
            line-height: 1;
            color: var(--text-secondary);
            background: transparent;
            border-color: transparent;
            user-select: none;
        }
        .pagi-btn:hover {
            background: var(--bg-hover);
            border-color: var(--border-default);
            color: var(--text-primary);
        }

        /* Active / current page */
        .pagi-btn.active {
            background: linear-gradient(135deg, #4F46E5, #7C3AED);
            color: #ffffff;
            border-color: transparent;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35);
        }
        .pagi-btn.active:hover {
            background: linear-gradient(135deg, #4338CA, #6D28D9);
            color: #fff;
        }

        /* Disabled nav arrow */
        .pagi-btn.disabled {
            opacity: 0.35;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Prev / Next arrows */
        .pagi-arrow {
            min-width: 36px;
            height: 36px;
            background: var(--bg-card);
            border: 1.5px solid var(--border-default);
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .pagi-arrow:hover:not(.disabled) {
            background: var(--bg-hover);
            border-color: #C7D2FE;
            color: #4F46E5;
        }

        /* Dots separator */
        .pagi-dots {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 36px;
            font-size: 14px;
            color: var(--text-muted);
            letter-spacing: 1px;
            cursor: default;
            user-select: none;
        }

        /* Mobile simple nav */
        .pagi-mobile {
            display: none;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            gap: 12px;
        }
        .pagi-mobile-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            color: #4F46E5;
            background: var(--bg-hover);
            border: 1.5px solid var(--border-default);
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .pagi-mobile-btn:hover { opacity: 0.85; }
        .pagi-mobile-btn.disabled { opacity: 0.4; pointer-events: none; }

        @media (max-width: 640px) {
            .pagi-wrap { display: none; }
            .pagi-mobile { display: flex; }
        }
    </style>

    {{-- ── Mobile View ── --}}
    <div class="pagi-mobile">
        @if ($paginator->onFirstPage())
            <span class="pagi-mobile-btn disabled">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                Previous
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pagi-mobile-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                Previous
            </a>
        @endif

        <span style="font-size:13px;color:#64748B;font-weight:600;">
            Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
        </span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pagi-mobile-btn">
                Next
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        @else
            <span class="pagi-mobile-btn disabled">
                Next
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </span>
        @endif
    </div>

    {{-- ── Desktop View ── --}}
    <div class="pagi-wrap">

        {{-- Info text --}}
        <div class="pagi-info">
            @if ($paginator->firstItem())
                Showing <strong>{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</strong>
                of <strong>{{ $paginator->total() }}</strong> results
            @else
                {{ $paginator->count() }} results
            @endif
        </div>

        {{-- Page buttons --}}
        <div class="pagi-controls">

            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="pagi-btn pagi-arrow disabled" aria-label="{{ __('pagination.previous') }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pagi-btn pagi-arrow" aria-label="{{ __('pagination.previous') }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
                </a>
            @endif

            {{-- Page numbers --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagi-dots">···</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pagi-btn active" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="pagi-btn" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pagi-btn pagi-arrow" aria-label="{{ __('pagination.next') }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            @else
                <span class="pagi-btn pagi-arrow disabled" aria-label="{{ __('pagination.next') }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
                </span>
            @endif

        </div>
    </div>

</nav>
@endif
