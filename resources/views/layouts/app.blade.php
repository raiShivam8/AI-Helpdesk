<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'AI Helpdesk') }} — Support Portal</title>
        <meta name="description" content="AI-powered helpdesk ticket management system">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- ⚡ Dark mode: apply class BEFORE paint to prevent flash -->
        <script>
            (function () {
                const stored = localStorage.getItem('theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (stored === 'dark' || (!stored && prefersDark)) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased transition-colors duration-300"
          style="background-color: var(--bg-page); color: var(--text-primary);"
          x-data="{ sidebarOpen: false }">

        <div class="flex min-h-screen relative overflow-x-hidden">

            {{-- ════════ SIDEBAR ════════ --}}
            @include('layouts.navigation')

            {{-- ════════ MAIN AREA ════════ --}}
            <div class="flex-1 flex flex-col min-w-0 w-full lg:ml-[260px] transition-all duration-300">

                {{-- Top header bar matching Image 1 --}}
                <header class="sticky top-0 z-20 backdrop-blur-sm border-b h-16 flex items-center px-4 sm:px-6 gap-3 shrink-0 bg-white/90 dark:bg-[#161B27]/90 border-slate-200/80 dark:border-slate-800">

                    {{-- Mobile Hamburger Menu Button --}}
                    <button
                        @click="sidebarOpen = !sidebarOpen"
                        type="button"
                        class="lg:hidden p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shrink-0"
                        aria-label="Toggle navigation menu"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    @if (isset($header))
                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 flex-1 min-w-0">
                            {{ $header }}
                        </div>
                    @else
                        {{-- Breadcrumb matching Image 1: headset icon > Dashboard --}}
                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 flex-1 min-w-0">
                            <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 100-6 3 3 0 000 6z" />
                            </svg>
                            <svg class="w-3.5 h-3.5 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                            <span class="text-slate-800 dark:text-slate-200 font-bold truncate">Dashboard</span>
                        </div>
                    @endif

                    {{-- Right Controls matching Image 1 --}}
                    <div class="flex items-center gap-2 sm:gap-3">
                        @auth
                        {{-- User Pill --}}
                        <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 bg-slate-50 dark:bg-slate-800/80 rounded-xl border border-slate-200/70 dark:border-slate-700/60">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ Auth::user()?->name }}</span>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-bold bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300">
                                {{ Auth::user()?->role?->value ?? 'User' }}
                            </span>
                        </div>
                        @endauth

                        @if (Auth::user()?->isAdmin())
                        <a href="{{ route('users.index') }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 shadow-2xs transition-colors">
                            <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span>Admin Panel</span>
                        </a>
                        @endif

                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 shadow-2xs transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                <span>Sign out</span>
                            </button>
                        </form>

                        {{-- Dark / Light Mode Toggle --}}
                        <button
                            id="theme-toggle-btn"
                            class="theme-toggle"
                            title="Toggle dark / light mode"
                            aria-label="Toggle dark mode"
                            onclick="
                                const html = document.documentElement;
                                if (html.classList.contains('dark')) {
                                    html.classList.remove('dark');
                                    localStorage.setItem('theme', 'light');
                                } else {
                                    html.classList.add('dark');
                                    localStorage.setItem('theme', 'dark');
                                }
                            "
                        >
                            <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="5"/>
                                <line x1="12" y1="1" x2="12" y2="3"/>
                                <line x1="12" y1="21" x2="12" y2="23"/>
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                                <line x1="1" y1="12" x2="3" y2="12"/>
                                <line x1="21" y1="12" x2="23" y2="12"/>
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                            </svg>
                            <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                            </svg>
                        </button>
                    </div>

                </header>

                {{-- Page Content --}}
                <main class="flex-1 p-4 sm:p-6 lg:p-8 animate-in">
                    {{ $slot }}
                </main>

            </div>
        </div>

        @stack('scripts')
    </body>
</html>
