<x-guest-layout>
    {{-- ── Form Heading ── --}}
    <div class="mb-4">
        <h2 style="font-size:19px;font-weight:800;color:#111827;line-height:1.2;">Reset password 🔑</h2>
        <p style="font-size:13px;color:#6B7280;margin-top:3px;">
            Forgot your password? Enter your email address and we'll send you a link to reset your password.
        </p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" novalidate autocomplete="off">
        @csrf

        <!-- Email Address -->
        <div style="margin-bottom:16px;">
            <x-input-label for="email" :value="__('Email Address')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus placeholder="you@example.com" :is-error="$errors->has('email')" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <div style="margin-bottom:14px;">
            <x-primary-button style="width:100%;justify-content:center;padding:10px 0;font-size:14px;font-weight:700;border-radius:10px;">
                Email Password Reset Link →
            </x-primary-button>
        </div>

        <div class="text-center text-xs text-slate-600">
            Remembered your password?
            <a class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline transition-colors" href="{{ route('login') }}">
                Back to Sign in
            </a>
        </div>
    </form>

    {{-- Contact Support --}}
    <div style="margin-top:16px;padding-top:14px;border-top:1px solid #F3F4F6;">
        <x-contact-support variant="compact" class="text-center" />
    </div>
</x-guest-layout>
