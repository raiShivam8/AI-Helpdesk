{{-- ═══════════════════════════════════════════════
     SIDEBAR NAVIGATION
     Fixed left-rail with logo, nav links, user footer
═══════════════════════════════════════════════ --}}
{{-- Mobile Backdrop Overlay --}}
<div
    x-show="sidebarOpen"
    x-transition:enter="transition-opacity ease-linear duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-linear duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @click="sidebarOpen = false"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-30 lg:hidden"
    x-cloak
></div>

<aside
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed inset-y-0 left-0 w-[260px] flex flex-col z-40 shadow-xl lg:shadow-sm transition-transform duration-300 ease-in-out"
    style="background-color: var(--bg-sidebar); border-right: 1px solid var(--border-default);"
>
    {{-- ── Logo ── --}}
    <div class="h-16 flex items-center justify-between gap-3 px-5 shrink-0" style="border-bottom: 1px solid var(--border-default);">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 min-w-0">
            <x-application-logo class="h-9 w-9 rounded-xl shadow-sm shrink-0" />
            <div class="min-w-0">
                <span class="block font-bold text-[15px] leading-tight truncate" style="color: var(--text-primary);">
                    {{ config('app.name', 'AI Helpdesk') }}
                </span>
                <span class="block text-[11px] font-medium tracking-wide" style="color: var(--text-muted);">Support Portal</span>
            </div>
        </a>
        <button @click="sidebarOpen = false" class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- ── Navigation ── --}}
    <nav class="flex-1 overflow-y-auto py-5 px-3 space-y-0.5">

        {{-- Section label --}}
        <p class="px-3 mb-2 text-[10px] font-semibold uppercase tracking-widest" style="color: var(--text-muted);">Main Menu</p>

        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
           class="nav-item {{ request()->routeIs('dashboard') ? 'nav-item-active' : 'nav-item-inactive' }}">
            <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span>Dashboard</span>
        </a>

        {{-- Tickets --}}
        <a href="{{ route('tickets.index') }}"
           class="nav-item {{ request()->routeIs('tickets.*') ? 'nav-item-active' : 'nav-item-inactive' }}">
            <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
            </svg>
            <span>Tickets</span>
        </a>

        {{-- Users (admin only) --}}
        @if (Auth::user()?->isAdmin())
            <a href="{{ route('users.index') }}"
               class="nav-item {{ request()->routeIs('users.*') ? 'nav-item-active' : 'nav-item-inactive' }}">
                <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>Users</span>
            </a>
        @endif

    </nav>

    {{-- Support Email Info --}}
    <div class="px-3 py-3 transition-colors" style="border-top: 1px solid var(--border-default); background-color: var(--bg-hover);">
        <x-contact-support variant="compact" class="text-[11px] leading-relaxed" style="color: var(--text-muted);" />
    </div>

    {{-- ── User Footer ── --}}
    <div class="shrink-0 p-3" style="border-top: 1px solid var(--border-default);">
        <x-dropdown align="top-right" width="48">
            <x-slot name="trigger">
                <button class="w-full flex items-center gap-3 p-2.5 rounded-xl transition-colors duration-150 text-left group"
                        style="color: var(--text-primary);"
                        onmouseover="this.style.backgroundColor='var(--bg-hover)'"
                        onmouseout="this.style.backgroundColor='transparent'">
                    {{-- Avatar --}}
                    <div class="avatar avatar-md gradient-brand text-white shrink-0">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold truncate" style="color: var(--text-primary);">{{ Auth::user()->name }}</div>
                        <div class="text-xs truncate" style="color: var(--text-muted);">{{ Auth::user()->email }}</div>
                    </div>
                    <svg class="w-4 h-4 shrink-0 transition-colors" style="color: var(--text-muted);" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
                    </svg>
                </button>
            </x-slot>

            <x-slot name="content">
                <x-dropdown-link :href="route('profile.edit')">
                    <svg class="w-4 h-4 mr-2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    {{ __('Profile') }}
                </x-dropdown-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-dropdown-link :href="route('logout')"
                            onclick="event.preventDefault(); this.closest('form').submit();">
                        <svg class="w-4 h-4 mr-2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        {{ __('Log Out') }}
                    </x-dropdown-link>
                </form>
            </x-slot>
        </x-dropdown>
    </div>
</aside>
