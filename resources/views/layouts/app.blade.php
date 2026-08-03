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
          style="background-color: var(--bg-page); color: var(--text-primary);">

        <div class="flex min-h-screen">

            {{-- ════════ SIDEBAR ════════ --}}
            @include('layouts.navigation')

            {{-- ════════ MAIN AREA ════════ --}}
            <div class="flex-1 flex flex-col min-w-0 ml-[260px]">

                {{-- Top header bar --}}
                <header class="sticky top-0 z-20 backdrop-blur-sm border-b h-16 flex items-center px-6 gap-4 shrink-0"
                        style="background-color: var(--bg-header); border-color: var(--border-default);">

                    {{-- Page title slot --}}
                    <div class="flex-1 min-w-0">
                        @isset($header)
                            {{ $header }}
                        @endisset
                    </div>

                    {{-- ── Dark / Light Mode Toggle ── --}}
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
                        {{-- Sun icon (shown in dark mode) --}}
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
                        {{-- Moon icon (shown in light mode) --}}
                        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                        </svg>
                    </button>

                </header>

                {{-- Page Content --}}
                <main class="flex-1 p-6 lg:p-8 animate-in">
                    {{ $slot }}
                </main>

            </div>
        </div>

        @stack('scripts')
    </body>
</html>
