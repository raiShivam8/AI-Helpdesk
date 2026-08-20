<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'AI Helpdesk') }} — Autonomous AI Customer Support</title>
        <meta name="description" content="AI-powered customer support portal. Auto-resolve tickets 10x faster with Google Gemini AI.">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800&display=swap" rel="stylesheet">

        <!-- Scripts & Styles (Relative Build Assets) -->
        @php
            $manifestPath = public_path('build/manifest.json');
            $cssFile = '/build/assets/app.css';
            $jsFile = '/build/assets/app.js';
            if (file_exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true);
                if (isset($manifest['resources/css/app.css']['file'])) {
                    $cssFile = '/build/' . $manifest['resources/css/app.css']['file'];
                }
                if (isset($manifest['resources/js/app.js']['file'])) {
                    $jsFile = '/build/' . $manifest['resources/js/app.js']['file'];
                }
            }
        @endphp
        <link rel="stylesheet" href="{{ $cssFile }}">
        <script type="module" src="{{ $jsFile }}"></script>
    </head>
    <body class="font-sans antialiased bg-slate-950 text-slate-100 min-h-screen flex flex-col justify-between selection:bg-indigo-500 selection:text-white relative overflow-x-hidden">

        <!-- Background Ambient Glow Blobs -->
        <div class="fixed top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[600px] pointer-events-none z-0 overflow-hidden">
            <div class="absolute -top-32 left-1/4 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl animate-pulse"></div>
            <div class="absolute top-20 right-1/4 w-96 h-96 bg-purple-600/20 rounded-full blur-3xl"></div>
            <div class="absolute top-60 left-1/3 w-80 h-80 bg-sky-500/15 rounded-full blur-3xl"></div>
        </div>

        <!-- Navigation Header -->
        <header class="w-full max-w-7xl mx-auto px-6 py-5 flex justify-between items-center z-10">
            <a href="/" class="flex items-center gap-3 group">
                <div class="p-1.5 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 shadow-lg shadow-indigo-500/25 group-hover:scale-105 transition-transform duration-200">
                    <x-application-logo class="w-9 h-9 text-white" />
                </div>
                <div>
                    <span class="text-lg font-extrabold tracking-tight text-white block leading-none">AI Helpdesk</span>
                    <span class="text-[11px] font-medium text-slate-400">Support Automation</span>
                </div>
            </a>
            
            @if (Route::has('login'))
                <nav class="flex items-center gap-3">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg shadow-indigo-500/25 transition-all duration-200 hover:-translate-y-0.5">
                            <span>Go to Dashboard</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-semibold text-slate-300 hover:text-white px-3 py-2 transition-colors">
                            Log in
                        </a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-semibold text-xs shadow-md shadow-indigo-600/30 transition-all duration-200">
                                Register
                            </a>
                        @endif
                    @endauth
                </nav>
            @endif
        </header>

        <!-- Hero Section -->
        <main class="flex-grow flex flex-col items-center justify-center px-6 py-12 z-10">
            <div class="max-w-4xl text-center">

                <!-- Badge -->
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-indigo-950/80 border border-indigo-500/30 text-indigo-300 text-xs font-semibold mb-8 shadow-inner">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span>AI Support Active • Powered by Gemini 2.0 Flash</span>
                </div>
                
                <!-- Headline -->
                <h1 class="text-4xl sm:text-6xl font-extrabold text-white tracking-tight mb-6 leading-[1.15]">
                    Resolve Support Tickets <br class="hidden sm:block" />
                    <span class="bg-gradient-to-r from-indigo-400 via-purple-300 to-pink-400 bg-clip-text text-transparent">10× Faster with AI</span>
                </h1>
                
                <!-- Tagline -->
                <p class="text-lg sm:text-xl text-slate-300 max-w-2xl mx-auto mb-10 leading-relaxed font-normal">
                    Every incoming customer email instantly becomes a smart ticket. Gemini AI reads, classifies, summarizes, and auto-resolves standard queries before your team even opens them.
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-16">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 bg-gradient-to-r from-indigo-600 via-purple-600 to-indigo-600 hover:bg-[length:200%_auto] text-white font-bold text-base rounded-2xl shadow-xl shadow-indigo-600/30 hover:shadow-indigo-600/50 transition-all duration-300 hover:-translate-y-1">
                            <span>Open Agent Dashboard</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-base rounded-2xl shadow-xl shadow-indigo-600/30 hover:shadow-indigo-600/50 transition-all duration-200 hover:-translate-y-1">
                            <span>Get Started — Sign In</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-7 py-4 border border-slate-700 bg-slate-900/80 hover:bg-slate-800 text-slate-200 font-semibold text-base rounded-2xl transition-all duration-200">
                                Create Account
                            </a>
                        @endif
                    @endauth
                </div>

                <!-- Feature Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-left max-w-4xl mx-auto">
                    <div class="p-6 rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md hover:border-indigo-500/40 transition-colors group">
                        <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 mb-4 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <h3 class="text-lg font-bold text-white mb-1">AI Auto-Resolution</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">Gemini AI queries your Knowledge Base to draft and resolve common inquiries automatically.</p>
                    </div>

                    <div class="p-6 rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md hover:border-purple-500/40 transition-colors group">
                        <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 mb-4 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <h3 class="text-lg font-bold text-white mb-1">IMAP Email Sync</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">Sync customer emails seamlessly into structured tickets with sanitization and real-time status updates.</p>
                    </div>

                    <div class="p-6 rounded-2xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-md hover:border-emerald-500/40 transition-colors group">
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 mb-4 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                        <h3 class="text-lg font-bold text-white mb-1">Smart Tone Polish</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">Refine support agent replies in 1-click for empathetic, precise, and professional customer communication.</p>
                    </div>
                </div>

                <!-- Contact Support Section -->
                <div class="mt-14 max-w-2xl mx-auto">
                    <x-contact-support class="text-slate-400 text-xs" />
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="w-full text-center py-6 border-t border-slate-900 text-xs text-slate-500 z-10">
            <div>
                &copy; {{ date('Y') }} {{ config('app.name', 'AI Helpdesk') }} — Powered by Google Gemini AI
            </div>
        </footer>

    </body>
</html>
