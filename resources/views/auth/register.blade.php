<x-guest-layout>
    {{-- ── Form Heading ── --}}
    <div class="mb-5">
        <h2 style="font-size:19px;font-weight:800;color:#111827;line-height:1.2;">Create your agent account ✨</h2>
        <p style="font-size:13px;color:#6B7280;margin-top:3px;">Join the AI-powered support team</p>
    </div>

    <form method="POST" action="{{ route('register') }}" novalidate autocomplete="off">
        @csrf

        <!-- Name -->
        <div style="margin-bottom:14px;">
            <x-input-label for="name" :value="__('Full Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" :is-error="$errors->has('name')" placeholder="John Doe" />
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>

        <!-- Email Address -->
        <div style="margin-bottom:14px;">
            <x-input-label for="email" :value="__('Email Address')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" :is-error="$errors->has('email')" placeholder="you@example.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <!-- Password -->
        <div style="margin-bottom:14px;">
            <x-input-label for="password" :value="__('Password')" />
            <div style="position:relative; margin-top:4px;">
                <x-text-input id="password" class="block w-full" style="padding-right: 44px;" type="password" name="password" required autocomplete="new-password" :is-error="$errors->has('password')" placeholder="••••••••" />
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <!-- Confirm Password -->
        <div style="margin-bottom:18px;">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <div style="position:relative; margin-top:4px;">
                <x-text-input id="password_confirmation" class="block w-full" style="padding-right: 44px;" type="password" name="password_confirmation" required autocomplete="new-password" :is-error="$errors->has('password_confirmation')" placeholder="••••••••" />
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
        </div>

        <div style="margin-bottom:16px;">
            <x-primary-button style="width:100%;justify-content:center;padding:10px 0;font-size:14px;font-weight:700;border-radius:10px;letter-spacing:0.01em;">
                Create Account →
            </x-primary-button>
        </div>

        <div class="text-center text-xs text-slate-600">
            Already have an account?
            <a class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline transition-colors" href="{{ route('login') }}">
                Sign in
            </a>
        </div>
    </form>

    {{-- Contact Support --}}
    <div style="margin-top:16px;padding-top:14px;border-top:1px solid #F3F4F6;">
        <x-contact-support variant="compact" class="text-center" />
    </div>
</x-guest-layout>
