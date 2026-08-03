@props([
    'email' => config('mail.support_email', env('SUPPORT_EMAIL', 'raishivamrai837@gmail.com')),
    'variant' => 'card', // 'card', 'compact', or 'banner'
])

@php
    $supportEmail = $email ?: env('SUPPORT_EMAIL', 'raishivamrai837@gmail.com');
@endphp

@if ($variant === 'compact')
    <div {{ $attributes->merge(['class' => 'text-xs sm:text-sm text-slate-600']) }}>
        Need help? Email us at
        <a href="mailto:{{ $supportEmail }}" class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline transition-colors">
            {{ $supportEmail }}
        </a>. Your email will automatically create a support ticket, and our AI or support team will respond as soon as possible.
    </div>
@elseif ($variant === 'banner')
    <div {{ $attributes->merge(['class' => 'w-full bg-gradient-to-r from-indigo-900 via-indigo-800 to-slate-900 text-white py-4 px-6 rounded-xl shadow-sm border border-indigo-700/50']) }}>
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3 text-center sm:text-left">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-500/20 rounded-lg shrink-0">
                    <svg class="w-5 h-5 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 002-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <p class="text-sm text-indigo-100">
                    Need help? Email us at
                    <a href="mailto:{{ $supportEmail }}" class="font-bold text-white hover:text-indigo-200 underline underline-offset-2">
                        {{ $supportEmail }}
                    </a>. Your email will automatically create a support ticket, and our AI or support team will respond as soon as possible.
                </p>
            </div>
        </div>
    </div>
@else
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-indigo-100 bg-indigo-50/70 p-5 text-slate-800 shadow-sm text-left']) }}>
        <div class="flex items-start gap-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-white shadow-sm">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 002-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <h4 class="font-semibold text-slate-900 text-base">Need Help & Support?</h4>
                <p class="mt-1 text-sm text-slate-600 leading-relaxed">
                    Need help? Email us at
                    <a href="mailto:{{ $supportEmail }}" class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">
                        {{ $supportEmail }}
                    </a>. Your email will automatically create a support ticket, and our AI or support team will respond as soon as possible.
                </p>
            </div>
        </div>
    </div>
@endif
