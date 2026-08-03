<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'AI Helpdesk') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900 min-h-screen flex flex-col justify-between">
        
        <!-- Navigation Header -->
        <header class="w-full max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <x-application-logo class="w-10 h-10" />
                <span class="text-xl font-bold tracking-tight text-gray-900">{{ config('app.name', 'AI Helpdesk') }}</span>
            </div>
            
            @if (Route::has('login'))
                <nav class="flex items-center gap-4">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-flex items-center px-4 py-2 border border-indigo-600 rounded-md font-semibold text-xs text-indigo-600 uppercase tracking-widest hover:bg-indigo-50 active:bg-indigo-100 transition ease-in-out duration-150">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900 font-medium">
                            Log in
                        </a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Register
                            </a>
                        @endif
                    @endauth
                </nav>
            @endif
        </header>

        <!-- Hero Section -->
        <main class="flex-grow flex items-center justify-center px-6 py-12">
            <div class="max-w-3xl text-center">
                <div class="flex justify-center mb-6">
                    <div class="p-3 bg-indigo-100 rounded-2xl">
                        <x-application-logo class="w-20 h-20" />
                    </div>
                </div>
                
                <h1 class="text-4xl sm:text-6xl font-extrabold text-gray-900 tracking-tight mb-4">
                    Modern Support, <span class="text-indigo-600">AI Powered</span>
                </h1>
                
                <p class="text-lg sm:text-xl text-gray-600 max-w-2xl mx-auto mb-8">
                    Automatically classify, summarize, route, and respond to incoming customer support tickets using Gemini AI integration. Keep your customer success team efficient.
                </p>

                <div class="flex justify-center gap-4">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-flex items-center px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white hover:bg-indigo-700 transition ease-in-out duration-150 shadow-md">
                            Go to Dashboard &rarr;
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white hover:bg-indigo-700 transition ease-in-out duration-150 shadow-md">
                            Get Started
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center px-6 py-3 border border-gray-300 bg-white rounded-md font-semibold text-sm text-gray-700 hover:bg-gray-50 transition ease-in-out duration-150 shadow-sm">
                                Create Account
                            </a>
                        @endif
                </div>

                <!-- Contact Support Section -->
                <div class="mt-10">
                    <x-contact-support class="max-w-2xl mx-auto" />
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="w-full text-center py-6 border-t border-gray-200 text-sm text-gray-500 space-y-2">
            <div>
                &copy; {{ date('Y') }} {{ config('app.name', 'AI Helpdesk') }}. All rights reserved.
            </div>
        </footer>

    </body>
</html>
